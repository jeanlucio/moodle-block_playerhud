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
 * Multi-turn AI chat for the Game Master Assistant tab.
 *
 * Extends generator to reuse key loading, SSRF protection, and provider fallback.
 * Accepts a full conversation history and returns a structured reply that may
 * include an optional action card for the teacher to confirm.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat extends generator {
    /**
     * Sends a multi-turn conversation to the configured AI provider.
     *
     * The last element of $messages must be the current user turn. The configured
     * key level (personal or site) is used first with native multi-turn support
     * (Gemini → Groq → OpenAI-compatible); core_ai is tried only when no key is
     * configured at any level.
     *
     * @param string $systemprompt System instruction describing the AI role and context.
     * @param array $messages Conversation history as [{role, content}, ...].
     *              Roles must be 'user' or 'assistant'.
     * @return array {reply: string, action: array|null, provider: string}
     * @throws \moodle_exception If no key is available or all providers fail.
     */
    public function send(string $systemprompt, array $messages): array {
        global $USER;

        [$geminikey, $groqkey, $openaikey, $openaiurl, $openaimodel, $keysource] = $this->load_api_keys();

        $nokeys = empty($geminikey) && empty($groqkey) && empty($openaikey);
        $result = ['success' => false, 'message' => ''];

        // Configured key level first, with native multi-turn support.
        if (!empty($geminikey)) {
            $result = $this->call_gemini_chat($systemprompt, $messages, $geminikey);
        }

        if (!$result['success'] && !empty($groqkey)) {
            $result = $this->call_groq_chat($systemprompt, $messages, $groqkey);
        }

        if (!$result['success'] && !empty($openaikey) && !empty($openaiurl)) {
            $result = $this->call_openai_chat(
                $systemprompt,
                $messages,
                $openaikey,
                $openaiurl,
                $openaimodel
            );
        }

        // A hub-borrowed key served the request: report it so the hub's usage report
        // reflects requests it never saw directly (see generator::call_with_fallback).
        $hubtiers = ['hub_personal', 'hub_site'];
        if ($result['success'] && in_array($keysource, $hubtiers, true) && class_exists(\local_aihub\ai::class)) {
            \local_aihub\ai::report_usage(
                (int) $USER->id,
                'block_playerhud',
                'chat',
                (string) ($result['provider'] ?? ''),
                '',
                $keysource === 'hub_personal' ? 'personal' : 'site'
            );
        }

        // Bottom of the ladder: Moodle core_ai, only when no key is configured.
        // core_ai has no native multi-turn API, so the history is flattened.
        if (!$result['success'] && $nokeys && $this->has_core_ai_provider()) {
            $chatlines = [];
            foreach ($messages as $msg) {
                $role = $msg['role'] === 'assistant' ? 'Assistant' : 'User';
                $chatlines[] = $role . ': ' . $msg['content'];
            }
            $parts = ['system' => $systemprompt, 'user' => implode("\n", $chatlines)];
            $result = $this->call_core_ai($parts);
        }

        if (!$result['success']) {
            if ($nokeys && empty($result['message'])) {
                throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
            }
            $errmsg = !empty($result['message'])
                ? $result['message']
                : get_string('ai_error_no_keys', 'block_playerhud');
            throw new \moodle_exception('ai_error_offline', 'block_playerhud', '', $errmsg);
        }

        $parsed = $this->parse_chat_response($result['data'], $result['provider']);
        $this->log_chat($result['provider'], $messages);
        return $parsed;
    }

    /**
     * Calls the Gemini API with full conversation history.
     *
     * Gemini uses role 'model' for assistant turns instead of 'assistant'.
     *
     * @param string $systemprompt System instruction text.
     * @param array $messages Conversation history [{role, content}].
     * @param string $key Gemini API key.
     * @return array curl_request result array.
     */
    protected function call_gemini_chat(string $systemprompt, array $messages, string $key): array {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

        $contents = [];
        foreach ($messages as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $msg['content']]]];
        }

        $data = [
            'systemInstruction' => ['parts' => [['text' => $systemprompt]]],
            'contents' => $contents,
        ];

        return $this->curl_request(
            $url,
            json_encode($data),
            ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            'Gemini'
        );
    }

    /**
     * Calls the Groq API with full conversation history.
     *
     * @param string $systemprompt System instruction text.
     * @param array $messages Conversation history [{role, content}].
     * @param string $key Groq API key.
     * @return array curl_request result array.
     */
    protected function call_groq_chat(string $systemprompt, array $messages, string $key): array {
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $msgs = [['role' => 'system', 'content' => $systemprompt]];
        foreach ($messages as $msg) {
            $msgs[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $data = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => $msgs,
        ];

        return $this->curl_request(
            $url,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'Groq'
        );
    }

    /**
     * Calls any OpenAI-compatible endpoint with full conversation history.
     *
     * @param string $systemprompt System instruction text.
     * @param array $messages Conversation history [{role, content}].
     * @param string $key API key.
     * @param string $baseurl Full endpoint URL (e.g. https://api.openai.com/v1/chat/completions).
     * @param string $model Model identifier (e.g. gpt-4o-mini).
     * @return array curl_request result array.
     */
    protected function call_openai_chat(
        string $systemprompt,
        array $messages,
        string $key,
        string $baseurl,
        string $model
    ): array {
        $msgs = [['role' => 'system', 'content' => $systemprompt]];
        foreach ($messages as $msg) {
            $msgs[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $data = [
            'model' => !empty($model) ? $model : 'gpt-4o-mini',
            'messages' => $msgs,
        ];

        return $this->curl_request(
            $baseurl,
            json_encode($data),
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            'Custom AI'
        );
    }

    /**
     * Parses the raw AI response text into a structured reply + optional action.
     *
     * Expected JSON from AI:
     *   {"reply": "...", "action": {"type": "...", "label": "...", "params": {...}}}
     * or:
     *   {"reply": "..."}
     *
     * Falls back to treating the entire response as a plain-text reply when the
     * AI ignores the JSON format instruction.
     *
     * @param string $raw Raw text returned by the provider.
     * @param string $provider Provider name for the return array.
     * @return array {reply: string, action: array|null, provider: string}
     */
    protected function parse_chat_response(string $raw, string $provider): array {
        // Strip optional markdown fences.
        $cleaned = preg_replace('/^\x60{3}json\s*/im', '', $raw);
        $cleaned = preg_replace('/\x60{3}\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (is_array($decoded) && array_key_exists('reply', $decoded)) {
            $action = (isset($decoded['action']) && is_array($decoded['action']))
                ? $decoded['action']
                : null;
            return [
                'reply'    => (string) $decoded['reply'],
                'action'   => $action,
                'provider' => $provider,
            ];
        }

        // Second attempt: string-based extraction for when the model produces invalid JSON
        // (e.g. unescaped double quotes inside the reply value — common with core_ai providers
        // that do not support JSON output mode). Extracts reply and action independently.
        $extracted = $this->extract_reply_and_action($cleaned);
        if ($extracted !== null) {
            return $extracted + ['provider' => $provider];
        }

        // Fallback: plain text reply (AI did not honour the JSON instruction).
        return [
            'reply'    => $raw,
            'action'   => null,
            'provider' => $provider,
        ];
    }

    /**
     * Extracts reply text and action object from a malformed JSON string.
     *
     * Handles the common case where the model places unescaped double quotes
     * inside the reply string value, making the outer object invalid JSON while
     * the action sub-object (which contains no free text) remains valid.
     *
     * @param string $cleaned JSON string with markdown fences already stripped.
     * @return array|null Array with 'reply' and 'action' keys, or null if extraction fails.
     */
    private function extract_reply_and_action(string $cleaned): ?array {
        $replykey  = '"reply":"';
        $actionkey = '","action":';

        $replypos  = strpos($cleaned, $replykey);
        $actionpos = strpos($cleaned, $actionkey);

        // Response has both reply and action fields.
        if ($replypos !== false && $actionpos !== false) {
            $replytext  = substr($cleaned, $replypos + strlen($replykey), $actionpos - $replypos - strlen($replykey));
            $actionjson = trim(substr($cleaned, $actionpos + strlen($actionkey)));
            $action     = $this->extract_json_object($actionjson);
            return ['reply' => $replytext, 'action' => $action];
        }

        // Response has only a reply field (no action).
        if ($replypos !== false) {
            $after     = substr($cleaned, $replypos + strlen($replykey));
            $endpos    = strrpos($after, '"');
            $replytext = $endpos !== false ? substr($after, 0, $endpos) : $after;
            return ['reply' => $replytext, 'action' => null];
        }

        return null;
    }

    /**
     * Extracts the first complete JSON object from a string using brace counting.
     *
     * @param string $str String starting at or before the opening brace.
     * @return array|null Decoded array or null if extraction fails.
     */
    private function extract_json_object(string $str): ?array {
        $start = strpos($str, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $len   = strlen($str);
        for ($i = $start; $i < $len; $i++) {
            if ($str[$i] === '{') {
                $depth++;
            } else if ($str[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($str, $start, $i - $start + 1), true);
                    return is_array($decoded) ? $decoded : null;
                }
            }
        }
        return null;
    }

    /**
     * Inserts a row in block_playerhud_ai_logs for each chat exchange.
     *
     * @param string $provider Provider display name.
     * @param array $messages Full conversation history.
     * @return void
     */
    private function log_chat(string $provider, array $messages): void {
        global $DB, $USER;

        // Grab the last user message as the logged "object_name".
        $lastuser = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                $lastuser = $messages[$i]['content'];
                break;
            }
        }

        $record = new \stdClass();
        $record->blockinstanceid = $this->instanceid;
        $record->userid          = (int) $USER->id;
        $record->action_type     = 'chat';
        $record->object_name     = substr($lastuser, 0, 255);
        $record->ai_provider     = substr($provider, 0, 50);
        $record->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $record, false);
    }
}
