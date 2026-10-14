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
 * Tests that a block hidden from a role is closed to that role.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;
use block_playerhud\utils;

/**
 * Denying moodle/block:view on the instance hides the block from a role, and the profile section
 * and the file serving already honour that. The student web services and the shared helper must
 * as well, while whoever manages the instance keeps access.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\utils
 * @covers     \block_playerhud\external\collect_item
 * @covers     \block_playerhud\external\use_item
 * @covers     \block_playerhud\external\make_choice
 * @covers     \block_playerhud\external\load_scene
 * @covers     \block_playerhud\external\load_recap
 */
final class block_access_test extends external_base_testcase {
    /**
     * The helper lets a student in, shuts out one the block is hidden from, and lets a manager in
     * whatever the student role is denied.
     */
    public function test_require_block_access(): void {
        $context = \context_block::instance($this->instanceid);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        utils::require_block_access($context, 'block/playerhud:view');
        utils::require_block_access($context, 'block/playerhud:interact');

        $hidden = $this->create_student_with_block_hidden();
        $this->setUser($hidden);
        foreach (['block/playerhud:view', 'block/playerhud:interact'] as $capability) {
            try {
                utils::require_block_access($context, $capability);
                $this->fail("A student the block is hidden from reached $capability.");
            } catch (\required_capability_exception $e) {
                // The student still holds both plugin capabilities: only moodle/block:view is missing.
                $this->assertTrue(has_capability($capability, $context));
            }
        }

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        utils::require_block_access($context, 'block/playerhud:view');
        utils::require_block_access($context, 'block/playerhud:interact');
    }

    /**
     * Every student web service refuses a student the block is hidden from.
     */
    public function test_student_web_services_refuse_a_hidden_block(): void {
        global $DB;

        $item = $this->create_item($this->instanceid, 'Gem', ['xp' => 10]);
        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid, 'itemid' => $item->id, 'name' => 'Spot', 'maxusage' => 5,
            'value' => 1, 'respawntime' => 0, 'code' => utils::generate_drop_code($this->instanceid),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $chapter = $this->create_chapter('Chapter');
        $start = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($start->id, 'Go');
        $courseid = (int) $this->course->id;
        $instanceid = $this->instanceid;
        $this->setUser($this->create_student_with_block_hidden());

        $calls = [
            'collect_item' => fn() => collect_item::execute($instanceid, $dropid, $courseid),
            'use_item' => fn() => use_item::execute($instanceid, $courseid, (int) $item->id, 0),
            'make_choice' => fn() => make_choice::execute($instanceid, $courseid, (int) $choice->id),
            'load_scene' => fn() => load_scene::execute($instanceid, $courseid, (int) $chapter->id),
            'load_recap' => fn() => load_recap::execute($instanceid, $courseid, (int) $chapter->id),
        ];
        foreach ($calls as $name => $call) {
            try {
                $call();
                $this->fail("$name answered a student the block is hidden from.");
            } catch (\required_capability_exception $e) {
                $this->assertNotEmpty($e->getMessage(), $name);
            }
        }
    }
}
