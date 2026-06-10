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
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_chat_message_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        chat_message::execute($this->instanceid, $this->course->id, [['role' => 'user', 'content' => 'Hi']]);
    }
}
