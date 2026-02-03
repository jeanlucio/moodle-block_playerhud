<?php
namespace block_playerhud\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * AI Generation Logic for PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
     * Main entry point to generate content.
     */
    public function generate($mode, $theme, $xp, $createdrop, $extraoptions = []) {
        global $DB;

        // 1. Get Keys
        $geminikey = trim($this->config->apikey_gemini ?? '');
        $groqkey   = trim($this->config->apikey_groq ?? '');

        if (empty($geminikey)) {
            $geminikey = get_config('block_playerhud', 'apikey_gemini');
        }

        if (empty($geminikey) && empty($groqkey)) {
            throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
        }

        // CORREÇÃO: Se o XP for 0 ou vazio, gera um valor aleatório entre 10 e 500
        if ($xp <= 0) {
            $xp = rand(10, 500); 
        }

        // 2. Build Prompt
        $prompt = $this->build_prompt($mode, $theme, $xp);

        // 3. Call API
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

        // 4. Parse JSON
        $jsonraw = $result['data'];
        // Limpa blocos de código markdown se houver
        $cleanjson = preg_replace('/^`{3}json|`{3}$/m', '', $jsonraw);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata) {
            throw new \moodle_exception('error_ai_parsing', 'block_playerhud');
        }

        // 5. Save Item
        if ($mode === 'item') {
            // Passamos as opções extras (local, limites) para a função de salvar
            return $this->save_item($aidata, $xp, $createdrop, $result['provider'], $extraoptions);
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
        $item->xp = $targetxp; // Usa o XP definido (ou o aleatório gerado no PHP)
        
        // Defaults do item
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
            
            // LÓGICA DO DROP: Prioriza o que o professor digitou
            $drop->name = !empty($options['drop_location']) ? $options['drop_location'] : ($data['location_name'] ?? 'Drop Gerado');
            
            // Configurações de limite
            $drop->maxusage = (int)($options['drop_max'] ?? 0);
            
            // Tempo de recarga: O form envia MINUTOS, convertemos para SEGUNDOS
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

    protected function build_prompt($mode, $theme, $xp) {
        if ($mode === 'item') {
            // Prompt ajustado para pedir descrições reais/curiosidades
            $prompt = "Act as a Gamification Designer. Theme: '{$theme}'. Create an item. 
            Return ONLY JSON: 
            {
                \"name\": \"Item Name\", 
                \"description\": \"A short description related to the theme (educational fact, trivia or historical context). Avoid generic RPG fantasy lore.\", 
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Prod: true
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
