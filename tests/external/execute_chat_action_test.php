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
 * Tests for the execute_chat_action web service (deterministic branches).
 *
 * The action allow-list and parameter validation run before any AI call, and
 * the open_tab action never touches the network, so these branches are fully
 * deterministic.
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
 * Tests for the execute_chat_action web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\execute_chat_action
 */
final class execute_chat_action_test extends external_base_testcase {
    /**
     * The open_tab action returns a redirect URL to the requested tab without
     * calling the AI backend.
     */
    public function test_open_tab_returns_redirect_url(): void {
        $result = execute_chat_action::execute(
            $this->instanceid,
            $this->course->id,
            'open_tab',
            json_encode(['tab' => 'quests'])
        );

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('manage.php', $result['redirect_url']);
        $this->assertStringContainsString('tab=quests', $result['redirect_url']);

        $cleaned = external_api::clean_returnvalue(execute_chat_action::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * An action type outside the allow-list is rejected with success=false.
     */
    public function test_unknown_action_type_returns_failure(): void {
        $result = execute_chat_action::execute(
            $this->instanceid,
            $this->course->id,
            'delete_everything',
            json_encode([])
        );

        $this->assertFalse($result['success']);
        $this->assertEmpty($result['redirect_url']);
    }

    /**
     * Malformed JSON parameters are rejected with success=false.
     */
    public function test_bad_params_returns_failure(): void {
        $result = execute_chat_action::execute(
            $this->instanceid,
            $this->course->id,
            'create_item',
            'this is not json'
        );

        $this->assertFalse($result['success']);
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_execute_chat_action_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        execute_chat_action::execute(
            $this->instanceid,
            $this->course->id,
            'open_tab',
            json_encode(['tab' => 'items'])
        );
    }
}
