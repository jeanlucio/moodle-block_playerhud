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

/**
 * Tests for the assistant chat's audit logging.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\ai;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for chat::log_chat(), reached via reflection since it is private and never calls the AI.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\ai\chat
 */
final class chat_test extends external_base_testcase {
    /**
     * The logged teacher message is cut at 255 characters, never in the middle of one. Cutting at
     * 255 bytes split a multibyte character, which PostgreSQL rejects as invalid UTF-8 — after the
     * AI had already answered and been billed.
     */
    public function test_log_chat_truncates_by_characters_not_bytes(): void {
        global $DB;

        // The letter ç takes two bytes, so byte 255 falls in the middle of the 128th character.
        $message = str_repeat('ç', 200);
        $chat = new chat($this->instanceid);
        $method = new \ReflectionMethod($chat, 'log_chat');
        $method->setAccessible(true);

        $method->invoke($chat, 'Test', [['role' => 'user', 'content' => $message]]);

        $logged = $DB->get_field('block_playerhud_ai_logs', 'object_name', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'chat',
        ], MUST_EXIST);
        $this->assertSame(200, \core_text::strlen($logged));
        $this->assertSame($message, $logged);
    }

    /**
     * A message over the column limit is cut to exactly 255 characters.
     */
    public function test_log_chat_caps_the_message_at_255_characters(): void {
        global $DB;

        $chat = new chat($this->instanceid);
        $method = new \ReflectionMethod($chat, 'log_chat');
        $method->setAccessible(true);

        $method->invoke($chat, 'Test', [['role' => 'user', 'content' => str_repeat('ç', 300)]]);

        $logged = $DB->get_field('block_playerhud_ai_logs', 'object_name', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'chat',
        ], MUST_EXIST);
        $this->assertSame(str_repeat('ç', 255), $logged);
    }

    /**
     * Calls the protected parse_chat_response() method via reflection.
     *
     * @param string $raw What the model answered.
     * @return array The reply, the action and the provider.
     */
    private function parse(string $raw): array {
        $chat = new chat($this->instanceid);
        $method = new \ReflectionMethod($chat, 'parse_chat_response');
        $method->setAccessible(true);
        return $method->invoke($chat, $raw, 'Test');
    }

    /**
     * A reply that is a list or an object is not shown as the word Array.
     *
     * Regression test: the reply was cast with (string), so a model that answered
     * {"reply": ["Hello"]} printed Array in the chat and raised a notice.
     */
    public function test_a_reply_that_is_not_text_is_not_shown_as_array(): void {
        foreach ([['Hello'], ['text' => 'Hello']] as $reply) {
            $result = $this->parse(json_encode(['reply' => $reply, 'action' => null]));

            $this->assertNotSame('Array', $result['reply']);
            $this->assertStringNotContainsString('Array', $result['reply']);
        }
    }

    /**
     * Ordinary replies keep working: text, a number, and an explicit null as an empty reply.
     */
    public function test_ordinary_replies_are_unchanged(): void {
        $this->assertSame('Hello', $this->parse('{"reply":"Hello"}')['reply']);
        $this->assertSame('5', $this->parse('{"reply":5}')['reply']);
        $this->assertSame('', $this->parse('{"reply":null}')['reply']);
        $this->assertSame(['type' => 'x'], $this->parse('{"reply":"ok","action":{"type":"x"}}')['action']);
    }

    /**
     * The chat logs a failed attempt like the generator does, whatever tier the key came from.
     */
    public function test_chat_logs_a_failed_attempt(): void {
        set_config('apikey_groq', 'groq-key', 'block_playerhud');
        $chat = new class ($this->instanceid) extends chat {
            #[\Override]
            protected function call_groq_chat(string $systemprompt, array $messages, string $key): array {
                return ['success' => false, 'message' => 'Overloaded.', 'provider' => 'Groq', 'seconds' => 2.0];
            }
        };
        $sink = $this->redirectEvents();

        try {
            $chat->send('system', [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Every provider failed, so send() must throw.');
        } catch (\moodle_exception $e) {
            $this->assertSame('ai_error_offline', $e->errorcode);
        }

        $events = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \block_playerhud\event\ai_attempt_failed
        ));
        $this->assertCount(1, $events);
        $this->assertSame('chat', $events[0]->other['description']);
        $this->assertSame('Groq', $events[0]->other['provider']);
    }

    /**
     * The chat asks Groq for the same output room as the generator, and waits on a short rate limit too.
     */
    public function test_chat_asks_groq_for_room_and_waits_when_told_to(): void {
        $chat = new class ($this->instanceid) extends chat {
            /** @var string[] Payloads sent. */
            public array $payloads = [];

            /** @var int[] Seconds waited. */
            public array $waits = [];

            #[\Override]
            protected function curl_request(string $url, string $payload, array $headers, string $source): array {
                $this->payloads[] = $payload;
                return count($this->payloads) === 1
                    ? ['success' => false, 'message' => 'Rate limit', 'provider' => 'Groq', 'retryafter' => 4]
                    : ['success' => true, 'data' => '{"reply":"ok"}', 'provider' => 'Groq'];
            }

            #[\Override]
            protected function pause(int $seconds): void {
                $this->waits[] = $seconds;
            }
        };
        $method = new \ReflectionMethod($chat, 'call_groq_chat');
        $method->setAccessible(true);

        $result = $method->invoke($chat, 'system', [['role' => 'user', 'content' => 'hi']], 'key');

        $this->assertTrue($result['success']);
        $this->assertSame([4], $chat->waits);
        $this->assertSame(6000, json_decode($chat->payloads[1], true)['max_completion_tokens']);
    }
}
