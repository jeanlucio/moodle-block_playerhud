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
 * Tests for the chat_message web service (validation and error paths).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;
use core_external\external_api;

/**
 * Tests for the chat_message web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\chat_message
 */
final class chat_message_test extends external_base_testcase {
    /**
     * With no API key configured the chat backend raises a moodle_exception.
     */
    public function test_chat_message_without_key_throws(): void {
        set_config('apikey_gemini', '', 'block_playerhud');
        set_config('apikey_groq', '', 'block_playerhud');
        set_config('apikey_openai', '', 'block_playerhud');

        $history = [['role' => 'user', 'content' => 'Hello']];

        $this->expectException(\moodle_exception::class);
        chat_message::execute($this->instanceid, $this->course->id, $history);
    }

    /**
     * The 'message' field is declared PARAM_TEXT, so a value still carrying markup fails the
     * response validation instead of ever reaching the client — the defense-in-depth layer for
     * a compromised or malicious AI provider echoing HTML in its own error response, which
     * would otherwise land in the teacher's Notification.alert() modal (rendered via .html()).
     */
    public function test_chat_message_message_field_rejects_unclean_html(): void {
        $malicious = ['message' => '<img src=x onerror=alert(1)>'];

        $this->expectException(\core\exception\invalid_response_exception::class);
        external_api::clean_returnvalue(chat_message::execute_returns(), $malicious);
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_chat_message_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        chat_message::execute($this->instanceid, $this->course->id, [['role' => 'user', 'content' => 'Hi']]);
    }

    /**
     * Regression test for the security-audit finding: a courseid that does not belong to this
     * block instance must be rejected, so the AI system prompt can never be built with another
     * course's fullname — including a course the caller has no access to.
     *
     * Asserts the specific 'accessdenied' errorcode wizard::require_course_matches_instance()
     * throws, not just \moodle_exception — without the fix, execute() still ends in a
     * moodle_exception anyway (no AI provider key configured in the test environment), which
     * would make a bare exception-class assertion pass regardless of whether the courseid guard
     * ran at all.
     */
    public function test_chat_message_rejects_a_mismatched_courseid(): void {
        $othercourse = $this->getDataGenerator()->create_course();

        try {
            chat_message::execute($this->instanceid, $othercourse->id, [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected an accessdenied moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('accessdenied', $e->errorcode);
        }
    }
}
