<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_playerhud\ai;

/**
 * AI Content Generator class.
 *
 * Handles interactions with Generative AI APIs to create game content.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var int The block instance ID. */
    protected $instanceid;

    /** @var \stdClass The block configuration. */
    protected $config;

    /**
     * Constructor.
     *
     * @param int $instanceid The block instance ID.
     */
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
     * Generates items via AI.
     *
     * @param string $mode Generation mode (e.g., 'item').
     * @param string $theme The theme for generation.
     * @param int $xp Target XP value.
     * @param bool $createdrop Whether to create a drop location.
     * @param array $extraoptions Additional options for drops and balancing.
     * @param int $amount Number of items to generate.
     * @return array Result array with success status and data.
     * @throws \moodle_exception If API keys are missing or parsing fails.
     */
    public function generate($mode, $theme, $xp, $createdrop, $extraoptions = [], $amount = 1) {

        // 1. Get Keys.
        $geminikey = trim($this->config->apikey_gemini ?? '');
        $groqkey   = trim($this->config->apikey_groq ?? '');

        if (empty($geminikey)) {
            $geminikey = get_config('block_playerhud', 'apikey_gemini');
        }

        if (empty($geminikey) && empty($groqkey)) {
            // Error: correct language file key used.
            throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
        }

        // 2. Infinite Rule.
        $isinfinitedrop = $createdrop &&
            isset($extraoptions['drop_max']) &&
            ((int)$extraoptions['drop_max'] === 0);

        if ($isinfinitedrop) {
            $xp = 0;
        }

        // 3. XP Logic.
        $balance = $extraoptions['balance_context'] ?? null;

        if ($xp <= 0 && !$isinfinitedrop && $balance) {
            if ($balance['gap'] > 2000) {
                $xp = rand(150, 300);
            } else if ($balance['gap'] > 500) {
                $xp = rand(80, 150);
            } else if ($balance['gap'] > 0) {
                $xp = rand(30, 80);
            } else {
                $xp = rand(10, 30);
            }
        } else if ($xp <= 0 && !$isinfinitedrop) {
            $xp = rand(10, 200);
        }

        // 4. Build Prompt.
        $prompt = $this->build_prompt($mode, $theme, $xp, $balance, $amount);

        // 5. Call API.
        $result = ['success' => false, 'message' => ''];

        if (!empty($geminikey)) {
            $result = $this->call_gemini($prompt, $geminikey);
        }

        // Try Groq only if Gemini failed or was not configured.
        if ((!$result['success']) && !empty($groqkey)) {
            $result = $this->call_groq($prompt, $groqkey);
        }

        if (!$result['success']) {
            // The message comes translated from curl_request or is generic if no key is configured.
            $errormsg = !empty($result['message']) ?
                $result['message'] :
                get_string('ai_error_no_keys', 'block_playerhud');
            throw new \moodle_exception('ai_error_offline', 'block_playerhud', '', $errormsg);
        }

        // 6. Parse JSON.
        $jsonraw = $result['data'];
        // Backticks in strings are not recommended, using hex code \x60 instead.
        $cleanjson = preg_replace('/^\x60{3}json|\x60{3}$/m', '', $jsonraw);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata) {
            // Error: Parsing failed.
            throw new \moodle_exception('ai_error_parsing', 'block_playerhud');
        }

        // Normalization.
        if ($amount == 1 && isset($aidata['name'])) {
            $itemstosave = [$aidata];
        } else if (isset($aidata['items'])) {
            $itemstosave = $aidata['items'];
        } else if (is_array($aidata) && isset($aidata[0]['name'])) {
            $itemstosave = $aidata;
        } else {
            $itemstosave = [$aidata];
        }

        // 7. Save Loop.
        if ($mode === 'item') {
            $creatednames = [];
            $lastdropcode = '';

            foreach ($itemstosave as $singleitemdata) {
                if (count($creatednames) >= $amount) {
                    break;
                }

                $saved = $this->save_item(
                    $singleitemdata,
                    $xp,
                    $createdrop,
                    $result['provider'],
                    $extraoptions
                );
                $creatednames[] = $saved['item_name'];
                $lastdropcode = $saved['drop_code'];
            }

            $response = [
                'success' => true,
                'created_items' => $creatednames,
                'item_name' => $creatednames[0],
                'drop_code' => (count($creatednames) == 1) ? $lastdropcode : null,
                'provider' => $result['provider'],
            ];

            // Balance Analysis.
            if ($balance && !$isinfinitedrop) {
                $totaladdedxp = $xp * count($creatednames);

                if ($createdrop) {
                    $dropmult = (int)($extraoptions['drop_max'] ?? 1);
                    if ($dropmult <= 0) {
                        $totaladdedxp = 0;
                    } else {
                        $totaladdedxp *= $dropmult;
                    }
                }

                $newtotal = $balance['current_xp'] + $totaladdedxp;
                $target = $balance['target_xp'];

                if ($totaladdedxp > 0 && $target > 0 && $newtotal > $target) {
                    $ratio = ($newtotal / $target) * 100;
                    $excess = round($ratio - 100);
                    $response['warning_msg'] = get_string('ai_warn_overflow', 'block_playerhud', $excess);
                } else {
                    $response['info_msg'] = get_string('ai_tip_balanced', 'block_playerhud');
                }
            } else if ($isinfinitedrop) {
                $response['info_msg'] = get_string('ai_info_infinite_xp', 'block_playerhud');
            }

            return $response;
        }

        // Unknown mode error.
        return ['success' => false, 'message' => get_string('error_unknown_mode', 'block_playerhud')];
    }

    /**
     * Saves a generated item to the database.
     *
     * @param array $data Item data from AI.
     * @param int $targetxp Target XP value.
     * @param bool $createdrop Whether to create a drop.
     * @param string $provider AI Provider name.
     * @param array $options Additional options.
     * @return array Result with item name and drop code.
     */
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

            // Use default_drop_name if location_name is not provided by AI.
            $fallbackname = $data['location_name'] ?? get_string('default_drop_name', 'block_playerhud');
            $drop->name = !empty($options['drop_location']) ? $options['drop_location'] : $fallbackname;

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
            'provider' => $provider,
        ];
    }

    /**
     * Builds the AI prompt string.
     *
     * @param string $mode Generation mode.
     * @param string $theme The theme.
     * @param int $xp XP value.
     * @param array|null $balance Balance context.
     * @param int $amount Amount to generate.
     * @return string The constructed prompt.
     */
    protected function build_prompt($mode, $theme, $xp, $balance = null, $amount = 1) {
        $currentlang = get_string('thislanguage', 'langconfig');

        if ($mode === 'item') {
            $contextstr = "";
            if ($balance) {
                if ($balance['gap'] > 0) {
                    $contextstr = get_string('ai_prompt_ctx_hard', 'block_playerhud');
                } else {
                    $contextstr = get_string('ai_prompt_ctx_easy', 'block_playerhud');
                }
            }

            // Strings for JSON example.
            $exname = get_string('ai_ex_name', 'block_playerhud');
            $exdesc = get_string('ai_ex_desc', 'block_playerhud');
            $exloc  = get_string('ai_ex_loc', 'block_playerhud');

            if ($amount > 1) {
                $a = new \stdClass();
                $a->count = $amount;
                $a->theme = $theme;
                $taskstr = get_string('ai_task_multi', 'block_playerhud', $a);

                // JSON structure example constructed with strings.
                $jsonstruct = '{ "items": [ { "name": "' . $exname . '", "description": "' . $exdesc .
                    '", "emoji": "📦", "location_name": "' . $exloc . '" }, ... ] }';
            } else {
                $taskstr = get_string('ai_task_single', 'block_playerhud', $theme);

                // JSON structure example constructed with strings.
                $jsonstruct = '{ "name": "' . $exname . '", "description": "' . $exdesc .
                    '", "emoji": "📦", "location_name": "' . $exloc . '" }';
            }

            $rolestr = get_string('ai_role_item', 'block_playerhud');
            $rulesstr = get_string('ai_rules_item', 'block_playerhud');
            $techxpstr = get_string('ai_prompt_tech_xp', 'block_playerhud', $xp);
            $jsoninst = get_string('ai_json_instruction', 'block_playerhud');
            $langinst = get_string('ai_reply_lang', 'block_playerhud', $currentlang);

            $prompt = implode("\n\n", [
                $rolestr,
                $taskstr,
                $rulesstr,
                $contextstr,
                $techxpstr,
                $jsoninst,
                $jsonstruct,
                $langinst,
            ]);

            return $prompt;
        }

        return "";
    }

    /**
     * Calls the Gemini API.
     *
     * @param string $prompt The prompt text.
     * @param string $key The API key.
     * @return array Response array.
     */
    protected function call_gemini($prompt, $key) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $key;
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        return $this->curl_request(
            $url,
            json_encode($data),
            ['Content-Type: application/json'],
            'Gemini'
        );
    }

    /**
     * Calls the Groq API.
     *
     * @param string $prompt The prompt text.
     * @param string $key The API key.
     * @return array Response array.
     */
    protected function call_groq($prompt, $key) {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [["role" => "user", "content" => $prompt]],
            "response_format" => ["type" => "json_object"],
        ];
        return $this->curl_request(
            $url,
            json_encode($data),
            ["Authorization: Bearer $key", "Content-Type: application/json"],
            'Groq'
        );
    }

    /**
     * Executes a HTTP request using Moodle's curl class.
     *
     * @param string $url The target URL.
     * @param string $payload The POST data.
     * @param array $headers Request headers.
     * @param string $source The source identifier.
     * @return array Result array with success status and data.
     */
    protected function curl_request($url, $payload, $headers, $source) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Moodle core curl class handles Proxy and SSL settings automatically.
        $curl = new \curl();

        // Sets the headers using the Moodle API (array).
        $curl->setHeader($headers);

        // Executes the POST request.
        // The post() method returns the response body directly.
        $response = $curl->post($url, $payload);

        // Get HTTP status code and errors from the object properties.
        $info = $curl->get_info();
        $code = isset($info['http_code']) ? (int)$info['http_code'] : 0;
        $curlerror = $curl->get_errno();

        // 1. Check for cURL level errors (e.g., DNS, Timeout).
        if ($curlerror) {
            // 1.1 $curl->error contains the error message.
            return ['success' => false, 'message' => 'cURL Error: ' . $curl->error];
        }

        // 2. Check for HTTP level errors (e.g., 401, 500).
        if ($code !== 200) {
            // Tries to extract error message from JSON response body if available.
            $errormsg = '';
            if (!empty($response)) {
                $decodederror = json_decode($response, true);
                if (isset($decodederror['error']['message'])) {
                    $errormsg = ': ' . $decodederror['error']['message'];
                }
            }

            $msg = get_string(
                'error_service_code',
                'block_playerhud',
                ['service' => $source, 'code' => $code . $errormsg]
            );
            return ['success' => false, 'message' => $msg];
        }

        // 3. Process Success.
        $decoded = json_decode($response, true);
        $content = '';

        if ($source === 'Gemini') {
            $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $content = $decoded['choices'][0]['message']['content'] ?? '';
        }

        return ['success' => true, 'data' => $content, 'provider' => $source];
    }
}
