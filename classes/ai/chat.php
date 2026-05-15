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
     * The last element of $messages must be the current user turn.
     * Tries providers in order: Gemini → Groq → OpenAI-compatible.
     *
     * @param string $systemprompt System instruction describing the AI role and context.
     * @param array $messages Conversation history as [{role, content}, ...].
     *              Roles must be 'user' or 'assistant'.
     * @return array {reply: string, action: array|null, provider: string}
     * @throws \moodle_exception If no key is available or all providers fail.
     */
    public function send(string $systemprompt, array $messages): array {
        [$geminikey, $groqkey, $openaikey, $openaiurl, $openaimodel] = $this->load_api_keys();

        if (empty($geminikey) && empty($groqkey) && empty($openaikey)) {
            throw new \moodle_exception('ai_error_no_keys', 'block_playerhud');
        }

        $result = ['success' => false, 'message' => ''];

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

        if (!$result['success']) {
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

        // Fallback: plain text reply (AI did not honour the JSON instruction).
        return [
            'reply'    => $raw,
            'action'   => null,
            'provider' => $provider,
        ];
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
