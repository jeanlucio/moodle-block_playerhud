<?php
namespace block_playerhud\ai;

defined('MOODLE_INTERNAL') || die();

class generator {

    protected $instanceid;
    protected $config;

    public function __construct($instanceid) {
        global $DB;
        $this->instanceid = $instanceid;
        $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
        $this->config = unserialize(base64_decode($bi->configdata));
        if (!$this->config) {
            $this->config = new \stdClass();
        }
    }

    public function generate($mode, $theme, $xp, $createdrop, $extraoptions = []) {
        
        // 1. Get Keys
        $geminikey = trim($this->config->apikey_gemini ?? '');
        $groqkey   = trim($this->config->apikey_groq ?? '');

        if (empty($geminikey)) {
            $geminikey = get_config('block_playerhud', 'apikey_gemini');
        }

        if (empty($geminikey) && empty($groqkey)) {
            throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
        }

        // 2. Lógica Inteligente de XP
        $balance = $extraoptions['balance_context'] ?? null;
        
        if ($xp <= 0 && $balance) {
            // Se falta muito XP (Gap grande), sugere itens valiosos (100-300).
            // Se falta pouco ou já passou (Gap <= 0), sugere itens decorativos/bônus (10-50).
            if ($balance['gap'] > 2000) {
                $xp = rand(150, 300); // Item Épico
            } elseif ($balance['gap'] > 500) {
                $xp = rand(80, 150);  // Item Raro
            } elseif ($balance['gap'] > 0) {
                $xp = rand(30, 80);   // Item Comum
            } else {
                $xp = rand(10, 30);   // Item Bônus (Jogo já está zerável)
            }
        } elseif ($xp <= 0) {
            $xp = rand(10, 200); // Fallback aleatório
        }

        // 3. Build Prompt (Com contexto de economia)
        $prompt = $this->build_prompt($mode, $theme, $xp, $balance);

        // 4. Call API
        $result = ['success' => false, 'message' => 'Init'];

        if (!empty($geminikey)) {
            $result = $this->call_gemini($prompt, $geminikey);
        }
        if ((!$result['success']) && !empty($groqkey)) {
            $result = $this->call_groq($prompt, $groqkey);
        }

        if (!$result['success']) {
            throw new \moodle_exception('error_ai_offline', 'block_playerhud', '', $result['message']);
        }

        // 5. Parse JSON
        $jsonraw = $result['data'];
        $cleanjson = preg_replace('/^`{3}json|`{3}$/m', '', $jsonraw);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata) {
            throw new \moodle_exception('error_ai_parsing', 'block_playerhud');
        }

        // 6. Save & Analyze Balance Impact
        if ($mode === 'item') {
            $saveResult = $this->save_item($aidata, $xp, $createdrop, $result['provider'], $extraoptions);
            
            // Adiciona aviso de balanceamento no retorno
            if ($balance) {
                $new_total = $balance['current_xp'] + $xp; // Aproximação (considerando drop x1)
                $ratio = ($balance['target_xp'] > 0) ? ($new_total / $balance['target_xp']) * 100 : 0;
                
                if ($ratio > 120) { // Se passar de 120% da meta
                    $excess = round($ratio - 100);
                    $saveResult['warning_msg'] = get_string('ai_warn_overflow', 'block_playerhud', $excess);
                } else {
                    $saveResult['info_msg'] = get_string('ai_tip_balanced', 'block_playerhud');
                }
            }
            
            return $saveResult;
        }

        return ['success' => false, 'message' => 'Unknown mode'];
    }

    protected function save_item($data, $targetxp, $createdrop, $provider, $options = []) {
        global $DB;

        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = $data['name'];
        $item->description = $data['description'];
        $item->image = $data['emoji'];
        $item->xp = $targetxp;
        
        $item->enabled = 1;
        $item->maxusage = 1;
        $item->respawntime = 0;
        $item->tradable = 1;
        $item->secret = 0;
        $item->required_class_id = 0;
        $item->timecreated = time();
        $item->timemodified = time();

        $itemid = $DB->insert_record('block_playerhud_items', $item);

        $dropcode = null;
        if ($createdrop) {
            $dropcode = strtoupper(substr(md5(time() . $itemid), 0, 6));
            $drop = new \stdClass();
            $drop->blockinstanceid = $this->instanceid;
            $drop->itemid = $itemid;
            $drop->code = $dropcode;
            $drop->name = !empty($options['drop_location']) ? $options['drop_location'] : ($data['location_name'] ?? 'Drop Gerado');
            $drop->maxusage = (int)($options['drop_max'] ?? 0);
            $minutes = (int)($options['drop_time'] ?? 0);
            $drop->respawntime = $minutes * 60;
            $drop->timecreated = time();
            $drop->timemodified = time(); 
            $DB->insert_record('block_playerhud_drops', $drop);
        }

        return [
            'success' => true,
            'item_name' => $item->name,
            'drop_code' => $dropcode,
            'provider' => $provider
        ];
    }

    protected function build_prompt($mode, $theme, $xp, $balance = null) {
        if ($mode === 'item') {
            $context = "";
            if ($balance) {
                if ($balance['gap'] > 0) {
                    $context = "Context: This is an educational RPG. We need items to help students reach the level cap.";
                } else {
                    $context = "Context: The game is already full of XP. This should be a bonus/flavor item, not essential for progression.";
                }
            }

            $prompt = "Act as a Gamification Designer. Theme: '{$theme}'. Create an item. {$context}
            Return ONLY JSON: 
            {
                \"name\": \"Item Name\", 
                \"description\": \"A short description related to the theme (educational fact, trivia or historical context).\", 
                \"emoji\": \"🔮\", 
                \"xp\": {$xp}, 
                \"location_name\": \"Location Suggestion\"
            }. 
            Reply in Portuguese.";
            
            return $prompt;
        }
        return "";
    }

    protected function call_gemini($prompt, $key) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $key;
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        return $this->curl_request($url, json_encode($data), ['Content-Type: application/json'], 'Gemini');
    }

    protected function call_groq($prompt, $key) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [["role" => "user", "content" => $prompt]],
            "response_format" => ["type" => "json_object"],
        ];
        return $this->curl_request($url, json_encode($data), ["Authorization: Bearer $key", "Content-Type: application/json"], 'Groq');
    }

    protected function curl_request($url, $payload, $headers, $source) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            return ['success' => false, 'message' => "$source error $code"];
        }

        $decoded = json_decode($res, true);
        $content = '';
        if ($source === 'Gemini') {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $content = $decoded['choices'][0]['message']['content'] ?? '';
        }
        return ['success' => true, 'data' => $content, 'provider' => $source];
    }
}
