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
 * Tests for the load_recap web service.
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
 * Tests for the load_recap web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\load_recap
 */
final class load_recap_test extends external_base_testcase {
    /**
     * Success path: after visiting a scene the recap HTML is returned.
     */
    public function test_load_recap_returns_html_after_visit(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $start   = $this->create_node($chapter->id, 'A memorable scene.', true);
        $this->create_choice($start->id, 'Done');

        // Visiting the scene records story history, which the recap reads.
        load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $result = load_recap::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
        $this->assertStringContainsString('memorable scene', $result['html']);

        $cleaned = external_api::clean_returnvalue(load_recap::execute_returns(), $result);
        $this->assertArrayHasKey('html', $cleaned);
    }

    /**
     * Requesting a recap with no recorded history triggers an exception.
     */
    public function test_load_recap_without_history_throws(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $this->create_node($chapter->id, 'Start', true);

        $this->expectException(\moodle_exception::class);
        load_recap::execute($this->instanceid, $this->course->id, (int) $chapter->id);
    }

    /**
     * A user without block/playerhud:view must not be able to load a recap.
     */
    public function test_load_recap_requires_view_capability(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $this->create_node($chapter->id, 'Start', true);

        $student = $this->create_student_without_view();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        load_recap::execute($this->instanceid, $this->course->id, (int) $chapter->id);
    }
}
