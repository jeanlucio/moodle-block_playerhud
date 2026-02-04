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

    /**
     * Gera itens via IA.
     */
    public function generate($mode, $theme, $xp, $createdrop, $extraoptions = [], $amount = 1) {
        
        // 1. Get Keys
        $geminikey = trim($this->config->apikey_gemini ?? '');
        $groqkey   = trim($this->config->apikey_groq ?? '');

        if (empty($geminikey)) {
            $geminikey = get_config('block_playerhud', 'apikey_gemini');
        }

        if (empty($geminikey) && empty($groqkey)) {
            throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
        }

        // 2. REGRA DO INFINITO (CORRIGIDA)
        // Só é infinito SE estiver criando drop (createdrop=1) E a quantidade for 0 (ilimitado).
        // Se createdrop for falso, ignoramos o valor de drop_max (pois o formulário manda 0 por padrão).
        $is_infinite_drop = $createdrop && isset($extraoptions['drop_max']) && ((int)$extraoptions['drop_max'] === 0);
        
        if ($is_infinite_drop) {
            $xp = 0; // Força XP zero apenas se for drop infinito real
        }

        // 3. Lógica Inteligente de XP
        $balance = $extraoptions['balance_context'] ?? null;
        
        if ($xp <= 0 && !$is_infinite_drop && $balance) {
            // Sugestão baseada no Gap
            if ($balance['gap'] > 2000) { $xp = rand(150, 300); } 
            elseif ($balance['gap'] > 500) { $xp = rand(80, 150); } 
            elseif ($balance['gap'] > 0) { $xp = rand(30, 80); } 
            else { $xp = rand(10, 30); }
        } elseif ($xp <= 0 && !$is_infinite_drop) {
            $xp = rand(10, 200);
        }

        // 4. Build Prompt
        $prompt = $this->build_prompt($mode, $theme, $xp, $balance, $amount);

        // 5. Call API
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

        // 6. Parse JSON
        $jsonraw = $result['data'];
        $cleanjson = preg_replace('/^`{3}json|`{3}$/m', '', $jsonraw);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata) {
            throw new \moodle_exception('error_ai_parsing', 'block_playerhud');
        }

        // Normalização
        if ($amount == 1 && isset($aidata['name'])) {
            $items_to_save = [$aidata];
        } elseif (isset($aidata['items'])) {
            $items_to_save = $aidata['items']; 
        } elseif (is_array($aidata) && isset($aidata[0]['name'])) {
            $items_to_save = $aidata; 
        } else {
            $items_to_save = [$aidata]; 
        }

        // 7. Save Loop & Analyze
        if ($mode === 'item') {
            $created_names = [];
            $last_drop_code = '';

            foreach ($items_to_save as $single_item_data) {
                if (count($created_names) >= $amount) break;

                $saved = $this->save_item($single_item_data, $xp, $createdrop, $result['provider'], $extraoptions);
                $created_names[] = $saved['item_name'];
                $last_drop_code = $saved['drop_code'];
            }
            
            $response = [
                'success' => true,
                'created_items' => $created_names,
                'item_name' => $created_names[0],
                'drop_code' => (count($created_names) == 1) ? $last_drop_code : null, 
                'provider' => $result['provider']
            ];

            // ANÁLISE DE BALANCEAMENTO (Lógica Refinada)
            if ($balance && !$is_infinite_drop) {
                $total_added_xp = $xp * count($created_names);
                
                // Se criou drop...
                if ($createdrop) {
                    $drop_mult = (int)($extraoptions['drop_max'] ?? 1);
                    
                    if ($drop_mult <= 0) {
                        // MUDANÇA: Se for infinito, zera o impacto no saldo (não conta para a meta)
                        $total_added_xp = 0; 
                    } else {
                        $total_added_xp *= $drop_mult;
                    }
                }
                
                // Soma ao total atual
                $new_total = $balance['current_xp'] + $total_added_xp;
                $target = $balance['target_xp'];

                // Só exibe aviso se realmente adicionou XP à economia (> 0) e passou da meta
                if ($total_added_xp > 0 && $target > 0 && $new_total > $target) { 
                    $ratio = ($new_total / $target) * 100;
                    $excess = round($ratio - 100);
                    $response['warning_msg'] = get_string('ai_warn_overflow', 'block_playerhud', $excess);
                } else {
                    $response['info_msg'] = get_string('ai_tip_balanced', 'block_playerhud');
                }
            } else if ($is_infinite_drop) {
                $response['info_msg'] = 'Itens infinitos foram criados com 0 XP para manter o equilíbrio.';
            }
            
            return $response;
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
            $dropcode = strtoupper(substr(md5(time() . $itemid . rand()), 0, 6));
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

    protected function build_prompt($mode, $theme, $xp, $balance = null, $amount = 1) {
        if ($mode === 'item') {
            $context = "";
            if ($balance) {
                if ($balance['gap'] > 0) {
                    $context = "Context: Educational RPG. Items needed to reach level cap.";
                } else {
                    $context = "Context: Game full of XP. Create flavor/bonus items.";
                }
            }

            if ($amount > 1) {
                $json_struct = "{ \"items\": [ { \"name\": \"Name 1\", \"description\": \"Desc...\", \"emoji\": \"🔮\", \"location_name\": \"Loc\" }, ... ] }";
                $task = "Create {$amount} DISTINCT items";
            } else {
                $json_struct = "{ \"name\": \"Name\", \"description\": \"Desc...\", \"emoji\": \"🔮\", \"location_name\": \"Loc\" }";
                $task = "Create ONE item";
            }

            $prompt = "Act as a Gamification Designer. Theme: '{$theme}'. {$task}. {$context}
            XP Value will be {$xp} (do not include in JSON if 0).
            Return ONLY JSON: 
            {$json_struct}
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
