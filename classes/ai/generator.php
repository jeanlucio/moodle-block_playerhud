<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_playerhud\ai;

/**
 * AI Content Generator class.
 *
 * Handles interactions with Generative AI APIs to create game content.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var string System role instruction for item generation. */
    private const PROMPT_ROLE_ITEM = 'Act as a creative item designer for an educational game,'
        . ' matching whatever narrative tone is requested.';

    /** @var string Item generation rules sent as system instruction. */
    private const PROMPT_RULES_ITEM = 'IMPORTANT: If a narrative tone is given below, fully embrace it —'
        . ' including fantasy, sci-fi, or mystery elements as appropriate; the item does NOT need to be a'
        . ' real-world object. If no narrative tone is given, keep the description factual, realistic, and'
        . ' educational, and do NOT invent fantasy stories or "lore".'
        . ' RULES: 1. The "name" must be short (maximum 4 words).'
        . ' 2. The "description" must be extremely concise and direct (maximum 150 characters).'
        . ' 3. Do NOT mention XP, levels, or game mechanics explicitly in the text.'
        . ' 4. The "emoji" field must be a single Unicode emoji that visually represents the item;'
        . ' choose it thematically and never use 📦 unless the item is literally a box or package.';

    /** @var string JSON format instruction appended to every item generation prompt. */
    private const PROMPT_JSON_INSTRUCTION = 'Return ONLY valid JSON following this structure:';

    /** @var string Context hint for common/introductory items. */
    private const PROMPT_CTX_EASY = 'Context: The game needs common or introductory items.';

    /** @var string Context hint for rare/high-value items. */
    private const PROMPT_CTX_HARD = 'Context: The game needs high-value (rare/complex) items.';

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
        $this->config = unserialize_object(base64_decode($bi->configdata));
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
     * @param array $extraoptions Additional options for drops, balancing and 'tone'.
     * @param int $amount Number of items to generate.
     * @return array Result array with success status and data.
     * @throws \moodle_exception If API keys are missing or parsing fails.
     */
    public function generate($mode, $theme, $xp, $createdrop, $extraoptions = [], $amount = 1) {

        // 2. Infinite Rule.
        $isinfinitedrop = $createdrop &&
            isset($extraoptions['drop_max']) &&
            ((int)$extraoptions['drop_max'] === 0);

        if ($isinfinitedrop) {
            $xp = 0;
        }

        // 3. XP Logic.
        $balance = $extraoptions['balance_context'] ?? null;
        if ($xp < 0 && !$isinfinitedrop && $balance) {
            if ($balance['gap'] > 2000) {
                $xp = rand(150, 300);
            } else if ($balance['gap'] > 500) {
                $xp = rand(80, 150);
            } else if ($balance['gap'] > 0) {
                $xp = rand(30, 80);
            } else {
                $xp = rand(10, 30);
            }
        } else if ($xp < 0 && !$isinfinitedrop) {
            $xp = rand(10, 200);
        }

        // 4. Build Prompt.
        $tone = (string)($extraoptions['tone'] ?? '');
        $parts = $this->build_prompt($mode, $theme, $xp, $balance, $amount, $tone);

        // 5. Call API.
        $result = $this->call_with_fallback($parts, 'item');

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
            $createditemids = [];
            $createddropids = [];
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
                $createditemids[] = $saved['item_id'];
                if ($saved['drop_id'] !== null) {
                    $createddropids[] = $saved['drop_id'];
                }
                $lastdropcode = $saved['drop_code'];
            }

            $response = [
                'success' => true,
                'created_items' => $creatednames,
                'created_item_ids' => $createditemids,
                'created_drop_ids' => $createddropids,
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
     * @return array Result with item name, item id, drop code and drop id.
     */
    protected function save_item($data, $targetxp, $createdrop, $provider, $options = []) {
        global $DB;

        // The AI's JSON is untrusted input: coerce to string defensively (a malformed response
        // could hand back an unexpected type) and clamp name to the column's own char(255) limit,
        // same defensive pattern already used for AI story text in wizard_run_step.php.
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = \core_text::substr((string) $data['name'], 0, 255);
        $item->description = (string) $data['description'];
        $item->image = clean_param((string) $data['emoji'], PARAM_TEXT);
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
        $dropid = null;
        if ($createdrop) {
            $dropcode = \block_playerhud\utils::generate_drop_code($this->instanceid);
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
            $dropid = (int) $DB->insert_record('block_playerhud_drops', $drop);
        }

        return [
            'success' => true,
            'item_name' => $item->name,
            'item_id' => (int) $itemid,
            'drop_code' => $dropcode,
            'drop_id' => $dropid,
            'provider' => $provider,
        ];
    }

    /**
     * Builds the AI prompt parts (system and user).
     *
     * Splits the prompt into a system instruction (role + rules) and a user
     * message (task + context + JSON schema). This ensures the model treats
     * the rules as hard constraints rather than conversational context.
     *
     * @param string $mode Generation mode.
     * @param string $theme The theme.
     * @param int $xp XP value.
     * @param array|null $balance Balance context.
     * @param int $amount Amount to generate.
     * @param string $tone Optional narrative tone hint (e.g. 'Fantasia Medieval').
     * @return array Associative array with 'system' and 'user' string keys.
     */
    protected function build_prompt($mode, $theme, $xp, $balance = null, $amount = 1, string $tone = ''): array {
        if ($mode === 'item') {
            $contextstr = '';
            if ($balance) {
                $contextstr = $balance['gap'] > 0 ? self::PROMPT_CTX_HARD : self::PROMPT_CTX_EASY;
            }

            $tonestr = ($tone !== '') ? "Narrative tone: {$tone}." : '';

            if ($amount > 1) {
                $taskstr = "Create {$amount} distinct items related to the theme: '{$theme}'.";
                $jsonstruct = '{"items":[{"name":"Name","description":"Description...",'
                    . '"emoji":"<emoji>","location_name":"Location"},...]}';
            } else {
                $taskstr = "Create ONE item related to the theme: '{$theme}'.";
                $jsonstruct = '{"name":"Name","description":"Description...",'
                    . '"emoji":"<emoji>","location_name":"Location"}';
            }

            $techxpstr = "Technical Requirement: The internal XP value is {$xp}"
                . ' (Do NOT write this number in the description, it is only for your'
                . ' assessment of item "value" or "rarity").';

            $currentlang = get_string('thislanguage', 'langconfig');
            $langinst = "Reply strictly in the language: {$currentlang}.";

            // Role + rules + tone go to the system slot so the model treats them as hard
            // constraints, not soft hints buried in the user conversation. Tone in particular
            // must sit here: it is what decides whether PROMPT_RULES_ITEM's fantasy/sci-fi
            // allowance or its factual/educational fallback applies.
            $system = implode("\n\n", array_filter([self::PROMPT_ROLE_ITEM, self::PROMPT_RULES_ITEM, $tonestr]));

            $user = implode("\n\n", [
                $taskstr,
                $contextstr,
                $techxpstr,
                self::PROMPT_JSON_INSTRUCTION,
                $jsonstruct,
                $langinst,
            ]);

            return ['system' => $system, 'user' => $user];
        }

        return ['system' => '', 'user' => ''];
    }

    /**
     * Calls the Gemini API.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @param string $key The API key.
     * @return array Response array.
     */
    protected function call_gemini(array $parts, string $key): array {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
        $data = [
            "systemInstruction" => ["parts" => [["text" => $parts['system']]]],
            "contents" => [["parts" => [["text" => $parts['user']]]]],
        ];
        return $this->curl_request(
            $url,
            json_encode($data),
            ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            'Gemini'
        );
    }

    /**
     * Calls the Groq API.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @param string $key The API key.
     * @return array Response array.
     */
    protected function call_groq(array $parts, string $key): array {
        $url = "https://api.groq.com/openai/v1/chat/completions";
        $data = [
            "model" => "llama-3.3-70b-versatile",
            "messages" => [
                ["role" => "system", "content" => $parts['system']],
                ["role" => "user", "content" => $parts['user']],
            ],
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
     * Calls any OpenAI-compatible API provider.
     *
     * Works with OpenAI, DeepSeek, Alibaba Qwen, Mistral, OpenRouter, Ollama, and others
     * that expose the standard /v1/chat/completions endpoint.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @param string $key The API key.
     * @param string $baseurl The provider base URL (e.g. https://api.openai.com).
     * @param string $model The model identifier (e.g. gpt-4o-mini).
     * @return array Response array.
     */
    protected function call_openai_compatible(array $parts, string $key, string $baseurl, string $model): array {
        $url = $baseurl;
        $data = [
            "model" => !empty($model) ? $model : 'gpt-4o-mini',
            "messages" => [
                ["role" => "system", "content" => $parts['system']],
                ["role" => "user", "content" => $parts['user']],
            ],
            "response_format" => ["type" => "json_object"],
        ];
        return $this->curl_request(
            $url,
            json_encode($data),
            ["Authorization: Bearer $key", "Content-Type: application/json"],
            'Custom AI'
        );
    }

    /**
     * Loads AI API keys following the canonical ecosystem ladder, tier by tier:
     *
     *   1. own personal (PlayerHUD prefs)
     *   2. hub personal (local_aihub)
     *   3. own site     (PlayerHUD config)
     *   4. hub site     (local_aihub)
     *
     * Each tier is resolved as a whole: the first tier that holds any provider key
     * is used exclusively (so an own personal key always wins over a hub key, even
     * for a different provider). core_ai is the institutional default and is
     * consulted by the caller only when no tier holds a key. The hub tiers are
     * skipped when local_aihub is absent.
     *
     * @return array Keys [geminikey, groqkey, openaikey, openaiurl, openaimodel, keysource].
     *               keysource is 'own_personal', 'hub_personal', 'own_site', 'hub_site' or ''.
     */
    protected function load_api_keys(): array {
        $hubinstalled = class_exists(\local_aihub\local\keys::class);

        $tiers = [];

        // Tier 1: own personal (PlayerHUD user preferences).
        $tiers[] = [
            'source' => 'own_personal',
            'keys' => [
                (string) get_user_preferences('block_playerhud_gemini_key', ''),
                (string) get_user_preferences('block_playerhud_groq_key', ''),
                (string) get_user_preferences('block_playerhud_openai_key', ''),
                (string) get_user_preferences('block_playerhud_openai_url', ''),
                (string) get_user_preferences('block_playerhud_openai_model', ''),
            ],
        ];

        // Tier 2: hub personal. URL and model prefer the hub's personal values,
        // falling back to the hub's site defaults when the user has not set them.
        if ($hubinstalled) {
            $hubpersonalurl = \local_aihub\local\keys::get_personal_openai_url();
            $hubpersonalmodel = \local_aihub\local\keys::get_personal_openai_model();
            $tiers[] = [
                'source' => 'hub_personal',
                'keys' => [
                    \local_aihub\local\keys::get_personal_key('gemini'),
                    \local_aihub\local\keys::get_personal_key('groq'),
                    \local_aihub\local\keys::get_personal_key('openai'),
                    $hubpersonalurl !== '' ? $hubpersonalurl : \local_aihub\local\keys::get_openai_baseurl(),
                    $hubpersonalmodel !== '' ? $hubpersonalmodel : \local_aihub\local\keys::get_openai_model(),
                ],
            ];
        }

        // Tier 3: own site (PlayerHUD config).
        $tiers[] = [
            'source' => 'own_site',
            'keys' => [
                (string) get_config('block_playerhud', 'apikey_gemini'),
                (string) get_config('block_playerhud', 'apikey_groq'),
                (string) get_config('block_playerhud', 'apikey_openai'),
                (string) get_config('block_playerhud', 'openai_baseurl'),
                (string) get_config('block_playerhud', 'openai_model'),
            ],
        ];

        // Tier 4: hub site.
        if ($hubinstalled) {
            $tiers[] = [
                'source' => 'hub_site',
                'keys' => [
                    \local_aihub\local\keys::get_site_key('gemini'),
                    \local_aihub\local\keys::get_site_key('groq'),
                    \local_aihub\local\keys::get_site_key('openai'),
                    \local_aihub\local\keys::get_openai_baseurl(),
                    \local_aihub\local\keys::get_openai_model(),
                ],
            ];
        }

        // Use the first tier that holds any provider key.
        $geminikey = $groqkey = $openaikey = $openaiurl = $openaimodel = '';
        $keysource = '';
        foreach ($tiers as $tier) {
            [$gemini, $groq, $openai, $openaiurltier, $openaimodeltier] = $tier['keys'];
            if ($gemini !== '' || $groq !== '' || $openai !== '') {
                $geminikey = $gemini;
                $groqkey = $groq;
                $openaikey = $openai;
                $openaiurl = $openaiurltier;
                $openaimodel = $openaimodeltier;
                $keysource = $tier['source'];
                break;
            }
        }

        // Reject URLs that could be used to probe internal network addresses (SSRF).
        if ($openaiurl !== '' && !$this->is_safe_url($openaiurl)) {
            $openaiurl = '';
        }
        if ($openaiurl !== '') {
            $openaiurl = $this->resolve_openai_url($openaiurl);
        }

        return [$geminikey, $groqkey, $openaikey, $openaiurl, $openaimodel, $keysource];
    }

    /**
     * Ensures the URL ends with /chat/completions.
     *
     * Providers that follow the OpenAI-compatible standard always expose this path.
     * Users who supply only a base URL (e.g. https://integrate.api.nvidia.com/v1)
     * get the suffix appended automatically; users who already include it are unaffected.
     *
     * @param string $url The configured endpoint URL.
     * @return string URL guaranteed to end with /chat/completions.
     */
    private function resolve_openai_url(string $url): string {
        if (!str_ends_with($url, '/chat/completions')) {
            $url = rtrim($url, '/') . '/chat/completions';
        }
        return $url;
    }

    /**
     * Returns true only if the URL is safe to use as an external AI endpoint.
     *
     * Enforces HTTPS and blocks loopback, link-local, and RFC-1918 private addresses
     * to prevent Server-Side Request Forgery (SSRF) via teacher-configured endpoints.
     *
     * @param string $url The URL to validate.
     * @return bool True if safe; false otherwise.
     */
    private function is_safe_url(string $url): bool {
        $parsed = parse_url($url);
        if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return false;
        }
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            // Block private and reserved IP ranges (RFC-1918, loopback, link-local, etc.).
            $ispublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($ispublic === false) {
                return false;
            }
        } else {
            // Hostname: resolve all A/AAAA records and re-apply the private/reserved check
            // to prevent DNS rebinding attacks where a public domain resolves to an internal IP.
            $resolvedips = [];
            $arecords = dns_get_record($host, DNS_A);
            if (is_array($arecords)) {
                foreach ($arecords as $r) {
                    if (!empty($r['ip'])) {
                        $resolvedips[] = $r['ip'];
                    }
                }
            }
            $aaaarecords = dns_get_record($host, DNS_AAAA);
            if (is_array($aaaarecords)) {
                foreach ($aaaarecords as $r) {
                    if (!empty($r['ipv6'])) {
                        $resolvedips[] = $r['ipv6'];
                    }
                }
            }
            foreach ($resolvedips as $resolvedip) {
                $ispublic = filter_var(
                    $resolvedip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
                if ($ispublic === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns true when Moodle core_ai has at least one provider configured for text generation.
     *
     * Compatible with Moodle 4.5+ — the manager is retrieved via the dependency
     * container, which injects the dependencies for the running version.
     *
     * @return bool
     */
    protected function has_core_ai_provider(): bool {
        if (
            !class_exists(\core_ai\manager::class)
            || !class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            return false;
        }
        try {
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = \core\di::get(\core_ai\manager::class);
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            return !empty($providers[$actionclass]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Generates text via the Moodle core_ai subsystem.
     *
     * Combines system and user prompt parts into a single string (core_ai accepts only
     * one prompttext field). Compatible with Moodle 4.5+.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @return array Result with keys: success (bool), data (string), message (string), provider (string).
     */
    protected function call_core_ai(array $parts): array {
        global $USER;
        try {
            $fullprompt = trim($parts['system'] . "\n\n" . $parts['user']);
            $actionclass = \core_ai\aiactions\generate_text::class;
            $manager = \core\di::get(\core_ai\manager::class);
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            if (empty($providers[$actionclass])) {
                return ['success' => false, 'message' => ''];
            }
            $action = new \core_ai\aiactions\generate_text(
                contextid: \context_system::instance()->id,
                userid: (int) $USER->id,
                prompttext: $fullprompt,
            );
            $response = $manager->process_action($action);
            if (!$response->get_success()) {
                return ['success' => false, 'message' => 'core_ai: provider returned failure'];
            }
            $data = $response->get_response_data();
            $content = (string) ($data['generatedcontent'] ?? '');
            if ($content === '') {
                return ['success' => false, 'message' => 'core_ai: empty response'];
            }
            return ['success' => true, 'data' => $content, 'provider' => 'Moodle AI'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'core_ai: ' . $e->getMessage()];
        }
    }

    /**
     * Calls AI providers following the canonical ladder.
     *
     * The configured key level (personal or site, resolved by load_api_keys) is
     * tried first: Gemini → Groq → OpenAI-compatible. Moodle core_ai is the
     * institutional default and is consulted only when no key is configured at any
     * level, so an explicitly set personal or site key always wins.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @param string $description Short label of what is being generated, reported to the
     *               AI Hub's usage log when a hub-borrowed key serves the request.
     * @return array Result array with keys 'success', 'data', 'provider'.
     * @throws \moodle_exception If all providers fail or no keys are configured.
     */
    protected function call_with_fallback(array $parts, string $description = ''): array {
        global $USER;

        [$geminikey, $groqkey, $openaikey, $openaiurl, $openaimodel, $keysource] = $this->load_api_keys();

        $nokeys = empty($geminikey) && empty($groqkey) && empty($openaikey);
        $result = ['success' => false, 'message' => ''];

        if (!empty($geminikey)) {
            $result = $this->call_gemini($parts, $geminikey);
        }

        if (!$result['success'] && !empty($groqkey)) {
            $result = $this->call_groq($parts, $groqkey);
        }

        if (!$result['success'] && !empty($openaikey) && !empty($openaiurl)) {
            $result = $this->call_openai_compatible($parts, $openaikey, $openaiurl, $openaimodel);
        }

        // A hub-borrowed key served the request: this class calls providers directly
        // instead of going through local_aihub\ai::generate_text(), so the hub never
        // sees the request on its own. Report it after the fact so the site usage
        // report still reflects it.
        $hubtiers = ['hub_personal', 'hub_site'];
        if ($result['success'] && in_array($keysource, $hubtiers, true) && class_exists(\local_aihub\ai::class)) {
            \local_aihub\ai::report_usage(
                (int) $USER->id,
                'block_playerhud',
                $description,
                (string) ($result['provider'] ?? ''),
                '',
                $keysource === 'hub_personal' ? 'personal' : 'site'
            );
        }

        // Bottom of the ladder: Moodle core_ai, only when no key is configured.
        if (!$result['success'] && $nokeys && $this->has_core_ai_provider()) {
            $result = $this->call_core_ai($parts);
        }

        if (!$result['success']) {
            if ($nokeys && empty($result['message'])) {
                throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
            }
            $errormsg = !empty($result['message']) ?
                $result['message'] :
                get_string('ai_error_no_keys', 'block_playerhud');
            throw new \moodle_exception('ai_error_offline', 'block_playerhud', '', $errormsg);
        }

        return $result;
    }

    /**
     * Builds the Class Oracle AI prompt.
     *
     * @param string $theme The theme or description for the class.
     * @return array Prompt parts with 'system' and 'user' keys.
     */
    protected function build_prompt_class_oracle(string $theme): array {
        $currentlang = get_string('thislanguage', 'langconfig');
        $prompt = "You are an RPG game designer for an educational game."
            . " Create a unique character based on the theme: {$theme}."
            . ' Reply ONLY with valid JSON — no markdown, no extra text.'
            . ' Structure: {"name":"character name (max 4 words)",'
            . '"description":"flavour text (max 150 characters)","hp":120,'
            . '"emoji":"<single Unicode emoji that visually represents this character"}'
            . ' Rules for emoji: choose a thematic emoji that matches the character concept'
            . ' (e.g. 🧙 for a wizard, 🏹 for an archer, 🌿 for a nature-based character);'
            . ' never use ⚔️ as default — pick something specific to the character.'
            . "\nGenerate all text content in the language: {$currentlang}.";
        return ['system' => '', 'user' => $prompt];
    }

    /**
     * Builds the Story Generation AI prompt.
     *
     * @param string $theme The story theme or setting.
     * @param array $options Optional mechanics constraints (karma_gain, karma_loss, item_qty).
     * @return array Prompt parts with 'system' and 'user' keys.
     */
    protected function build_prompt_story(string $theme, array $options = []): array {
        $prompt = "You are an interactive story designer for an educational text-adventure game."
            . " Create a branching story chapter based on the theme: {$theme}."
            . " Rules: minimum 6 nodes and at least 2 distinct ending nodes;"
            . " the starting node must have is_start=1;"
            . " branch nodes must have exactly 2 or 3 choices;"
            . ' terminal (ending) nodes must have "choices":[] and contain 2-3 sentences of satisfying'
            . " concluding text that wraps up that narrative path;"
            . " every branch MUST eventually reach a terminal node — do NOT create cycles or dead ends;"
            . " all target_index values must reference valid indexes in this JSON."
            . " Reply ONLY with valid JSON — no markdown, no extra text."
            . ' Structure: {"title":"chapter title","intro":"one-line summary","nodes":[{"index":0,'
            . '"content":"scene text (2-3 sentences)","is_start":1,'
            . '"choices":[{"text":"choice label (max 60 chars)","target_index":1}]}]}';

        $karmagain = max(0, (int)($options['karma_gain'] ?? 0));
        $karmaloss = max(0, (int)($options['karma_loss'] ?? 0));
        $itemqty   = max(0, (int)($options['item_qty'] ?? 0));

        if ($karmagain > 0 || $karmaloss > 0) {
            $prompt .= "\n\nReputation constraints: add a \"karma_delta\" integer field to every choice object. "
                . "Distribute positive karma_delta values on virtuous choices, totalling approximately +{$karmagain}. "
                . "Distribute negative karma_delta values on questionable choices, totalling approximately -{$karmaloss}. "
                . "Choices with no moral weight should have karma_delta set to 0. "
                . "Terminal nodes have no choices and therefore no karma_delta.";
        }

        if ($itemqty > 0) {
            $prompt .= "\n\nItem cost constraints: add a \"cost_item_qty\" integer field (use 0 for free choices) "
                . "to every choice object. Distribute item costs totalling approximately {$itemqty} "
                . "across key choices where the player must pay a price to proceed.";
        }

        $arcsummary = trim((string) ($options['arc_summary'] ?? ''));
        if ($arcsummary !== '') {
            $prompt .= "\n\nFull story arc, for consistency only — do not restate it, just keep the tone, "
                . "characters and setting coherent with where the story is headed:\n{$arcsummary}";
        }

        $beat = trim((string) ($options['beat'] ?? ''));
        if ($beat !== '') {
            $prompt .= "\n\nThis chapter's specific role in the arc: {$beat}";
        }

        $previouscontext = trim((string) ($options['previous_context'] ?? ''));
        if ($previouscontext !== '') {
            $prompt .= "\n\nContinue directly from the previous chapter, which ended like this:\n"
                . "{$previouscontext}\nKeep character names, tone and setting consistent with it — "
                . 'this is chapter 2 or later, not a new opening.';
        }

        $currentlang = get_string('thislanguage', 'langconfig');
        $prompt .= "\nGenerate all text content in the language: {$currentlang}.";

        return ['system' => '', 'user' => $prompt];
    }

    /**
     * Builds the prompt for generating a multi-chapter story arc outline: one short beat per
     * chapter, so later chapters can be generated individually while staying consistent with a
     * plan that has a real beginning, middle and end — instead of only knowing the immediately
     * previous chapter (a Markov chain that tends to wander over 5+ chapters).
     *
     * @param string $theme The story theme or setting.
     * @param int $chaptercount How many beats to produce, one per chapter.
     * @return array{system: string, user: string} Prompt parts for call_with_fallback().
     */
    protected function build_prompt_story_outline(string $theme, int $chaptercount): array {
        $prompt = "You are an interactive story designer for an educational text-adventure game."
            . " Design a {$chaptercount}-chapter story arc outline based on the theme: {$theme}."
            . ' The arc must have a clear beginning (chapter 1), rising tension through the middle'
            . ' chapters, and a satisfying climax and resolution on the final chapter — do not let'
            . ' the story wander without direction.'
            . ' Reply ONLY with valid JSON — no markdown, no extra text.'
            . ' Structure: {"beats":["one-sentence summary of chapter 1\'s events",'
            . '"one-sentence summary of chapter 2\'s events"]}.'
            . " The \"beats\" array must have exactly {$chaptercount} entries, in chapter order.";

        $currentlang = get_string('thislanguage', 'langconfig');
        $prompt .= "\nGenerate all text content in the language: {$currentlang}.";

        return ['system' => '', 'user' => $prompt];
    }

    /**
     * Generates a story arc outline via AI: one short beat per chapter, used to keep a
     * multi-chapter arc coherent (see build_prompt_story_outline()).
     *
     * @param string $theme The story theme or setting.
     * @param int $chaptercount How many beats to produce, one per chapter.
     * @return array{beats: string[], provider: string} Exactly $chaptercount beats, in order.
     * @throws \moodle_exception If parsing fails, key loading fails, or fewer beats than
     *     requested come back.
     */
    public function generate_story_outline(string $theme, int $chaptercount): array {
        $prompt = $this->build_prompt_story_outline($theme, $chaptercount);
        $result = $this->call_with_fallback($prompt, 'story');

        // Backtick markdown cleanup.
        $cleanjson = preg_replace('/^\x60{3}json|\x60{3}$/m', '', $result['data']);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata || empty($aidata['beats']) || count($aidata['beats']) < $chaptercount) {
            throw new \moodle_exception('ai_error_parsing', 'block_playerhud');
        }

        return [
            'beats' => array_slice(array_values($aidata['beats']), 0, $chaptercount),
            'provider' => $result['provider'],
        ];
    }

    /**
     * Generates an RPG class via the Class Oracle AI and saves it to the database.
     *
     * @param string $theme The theme or description for the class.
     * @return array Result array with 'success', 'class_name', and 'provider'.
     * @throws \moodle_exception If API keys are missing or parsing fails.
     */
    public function generate_class(string $theme): array {
        global $DB;

        $prompt = $this->build_prompt_class_oracle($theme);
        $result = $this->call_with_fallback($prompt, 'class');

        // Backtick markdown cleanup.
        $cleanjson = preg_replace('/^\x60{3}json|\x60{3}$/m', '', $result['data']);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata || empty($aidata['name'])) {
            throw new \moodle_exception('ai_error_parsing', 'block_playerhud');
        }

        // Normalize: some models wrap responses in an array.
        if (isset($aidata[0])) {
            $aidata = $aidata[0];
        } else if (isset($aidata['classes'][0])) {
            $aidata = $aidata['classes'][0];
        }

        $class = new \stdClass();
        $class->blockinstanceid = $this->instanceid;
        $class->name            = $aidata['name'];
        $class->description     = $aidata['description'] ?? '';
        $class->base_hp         = isset($aidata['hp']) ? max(1, (int)$aidata['hp']) : 100;
        $class->timecreated     = time();
        $class->timemodified    = time();

        $emoji = trim($aidata['emoji'] ?? '');
        if (!empty($emoji) && strpos($emoji, 'http') !== 0) {
            $class->emoji_tier1 = $emoji;
        }

        $DB->insert_record('block_playerhud_classes', $class);

        return [
            'success'    => true,
            'class_name' => $class->name,
            'provider'   => $result['provider'],
        ];
    }

    /**
     * Generates a story chapter with nodes and choices via AI and saves everything in a transaction.
     *
     * @param string $theme The story theme or setting.
     * @param array $options Optional mechanics constraints: karma_gain, karma_loss, item_id, item_qty.
     * @return array Result array with 'success', 'chapter_title', 'provider', 'chapter_id',
     *     'node_ids' (int[]) and 'choice_ids' (int[]).
     * @throws \moodle_exception If parsing fails or key loading fails.
     */
    public function generate_story(string $theme, array $options = []): array {
        global $DB;

        $prompt = $this->build_prompt_story($theme, $options);
        $result = $this->call_with_fallback($prompt, 'story');

        // Backtick markdown cleanup.
        $cleanjson = preg_replace('/^\x60{3}json|\x60{3}$/m', '', $result['data']);
        $aidata = json_decode($cleanjson, true);

        if (!$aidata || empty($aidata['title']) || empty($aidata['nodes'])) {
            throw new \moodle_exception('ai_error_parsing', 'block_playerhud');
        }

        $transaction = $DB->start_delegated_transaction();

        $chapter = new \stdClass();
        $chapter->blockinstanceid = $this->instanceid;
        $chapter->title           = $aidata['title'];
        $chapter->intro_text      = $aidata['intro'] ?? '';
        $chapter->unlock_date     = 0;
        $chapter->required_level  = 0;
        $chapter->sortorder       = $DB->count_records(
            'block_playerhud_chapters',
            ['blockinstanceid' => $this->instanceid]
        ) + 1;

        $chapterid = $DB->insert_record('block_playerhud_chapters', $chapter);

        // First pass: insert all nodes and build index → real ID map.
        $idxmap = [];
        foreach ($aidata['nodes'] as $nodedata) {
            $node           = new \stdClass();
            $node->chapterid = $chapterid;
            $node->content  = $nodedata['content'];
            $node->is_start = !empty($nodedata['is_start']) ? 1 : 0;
            $nid = $DB->insert_record('block_playerhud_story_nodes', $node);
            $idxmap[(int)($nodedata['index'] ?? count($idxmap))] = $nid;
        }

        // Second pass: insert choices with resolved next_nodeid.
        $choiceids = [];
        foreach ($aidata['nodes'] as $nodedata) {
            if (empty($nodedata['choices'])) {
                continue;
            }

            $nodeid = $idxmap[(int)($nodedata['index'] ?? -1)] ?? 0;
            if (!$nodeid) {
                continue;
            }

            foreach ($nodedata['choices'] as $choicedata) {
                $nextnodeid = $idxmap[(int)($choicedata['target_index'] ?? -1)] ?? 0;
                if ($nextnodeid === 0) {
                    // Target_index out of range - skip to avoid false chapter completion.
                    continue;
                }
                $choiceitemqty        = max(0, (int)($choicedata['cost_item_qty'] ?? 0));
                $choiceitemid         = (int)($options['item_id'] ?? 0);
                $choice               = new \stdClass();
                $choice->nodeid       = $nodeid;
                $choice->text         = $choicedata['text'];
                $choice->next_nodeid  = $nextnodeid;
                $choice->req_class_id = 0;
                $choice->req_karma_min = 0;
                $choice->karma_delta  = (int)($choicedata['karma_delta'] ?? 0);
                $choice->set_class_id = 0;
                $choice->cost_itemid  = ($choiceitemid > 0 && $choiceitemqty > 0) ? $choiceitemid : 0;
                $choice->cost_item_qty = ($choiceitemid > 0 && $choiceitemqty > 0) ? $choiceitemqty : 1;
                $choiceids[] = (int) $DB->insert_record('block_playerhud_choices', $choice);
            }
        }

        $transaction->allow_commit();

        return [
            'success'       => true,
            'chapter_title' => $aidata['title'],
            'provider'      => $result['provider'],
            'chapter_id'    => (int) $chapterid,
            'node_ids'      => array_values($idxmap),
            'choice_ids'    => $choiceids,
        ];
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
            $msg = get_string('error_connection', 'block_playerhud') . ' (' . $curl->error . ')';
            return ['success' => false, 'message' => $msg];
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
