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
 * Tests for the make_choice web service.
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
 * Tests for the make_choice web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\make_choice
 */
final class make_choice_test extends external_base_testcase {
    /**
     * Success path: choosing a branch advances the story to the destination node.
     */
    public function test_make_choice_advances_to_next_node(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $start   = $this->create_node($chapter->id, 'Start scene.', true);
        $next    = $this->create_node($chapter->id, 'Second scene.');
        $choice  = $this->create_choice($start->id, 'Continue', (int) $next->id);

        // Position the player at the start node before making the choice.
        load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $result = make_choice::execute($this->instanceid, $this->course->id, (int) $choice->id);

        $this->assertArrayHasKey('node', $result);
        $this->assertStringContainsString('Second scene.', $result['node']['content']);

        $cleaned = external_api::clean_returnvalue(make_choice::execute_returns(), $result);
        $this->assertArrayHasKey('node', $cleaned);
    }

    /**
     * A choice that does not belong to the instance triggers an exception.
     */
    public function test_make_choice_invalid_choice_throws(): void {
        $this->expectException(\moodle_exception::class);
        make_choice::execute($this->instanceid, $this->course->id, 99999);
    }

    /**
     * A user without block/playerhud:view must not be able to make a choice.
     */
    public function test_make_choice_requires_view_capability(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $start   = $this->create_node($chapter->id, 'Start', true);
        $choice  = $this->create_choice($start->id, 'Continue');

        $student = $this->create_student_without_view();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        make_choice::execute($this->instanceid, $this->course->id, (int) $choice->id);
    }
}
