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
 * Tests for the load_scene web service.
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
 * Tests for the load_scene web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\load_scene
 * @covers     \block_playerhud\story_manager
 */
final class load_scene_test extends external_base_testcase {
    /**
     * Success path: the starting node and its choices are returned, and the
     * response validates against the declared return structure.
     */
    public function test_load_scene_returns_start_node(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $start   = $this->create_node($chapter->id, 'You stand at a crossroads.', true);
        $this->create_choice($start->id, 'Go left');
        $this->create_choice($start->id, 'Go right');

        $result = load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $this->assertArrayHasKey('node', $result);
        $this->assertStringContainsString('crossroads', $result['node']['content']);
        $this->assertCount(2, $result['node']['choices']);

        $cleaned = external_api::clean_returnvalue(load_scene::execute_returns(), $result);
        $this->assertArrayHasKey('node', $cleaned);
    }

    /**
     * A role that can view but not interact must not have story progress written on its behalf:
     * entering a chapter used to create the player's progress row and save the start scene even
     * for such a role. The scene is still shown, and a role that may interact keeps the saved
     * position.
     */
    public function test_load_scene_writes_progress_only_with_the_interact_capability(): void {
        global $DB;

        $chapter = $this->create_chapter('Chapter 1');
        $start   = $this->create_node($chapter->id, 'You stand at a crossroads.', true);
        $context = \context_block::instance($this->instanceid);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('block/playerhud:interact', CAP_PROHIBIT, $roleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();

        $result = load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $this->assertStringContainsString('crossroads', $result['node']['content']);
        $this->assertFalse($DB->record_exists('block_playerhud_rpg_progress', [
            'blockinstanceid' => $this->instanceid, 'userid' => $student->id,
        ]));

        unassign_capability('block/playerhud:interact', $roleid, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);

        $progress = $DB->get_record('block_playerhud_rpg_progress', [
            'blockinstanceid' => $this->instanceid, 'userid' => $student->id,
        ], '*', MUST_EXIST);
        $this->assertSame([(int) $chapter->id => [(int) $start->id]], json_decode($progress->current_nodes, true));
    }

    /**
     * A chapter that does not belong to the instance triggers an exception.
     */
    public function test_load_scene_invalid_chapter_throws(): void {
        $this->expectException(\moodle_exception::class);
        load_scene::execute($this->instanceid, $this->course->id, 99999);
    }

    /**
     * A user without block/playerhud:view must not be able to load a scene.
     */
    public function test_load_scene_requires_view_capability(): void {
        $chapter = $this->create_chapter('Chapter 1');
        $this->create_node($chapter->id, 'Start', true);

        $student = $this->create_student_without_view();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        load_scene::execute($this->instanceid, $this->course->id, (int) $chapter->id);
    }
}
