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
 * Tests for the collect_item web service.
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
 * Tests for the collect_item web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\collect_item
 */
final class collect_item_test extends external_base_testcase {
    /**
     * Insert a finite drop for an item and return its ID.
     *
     * @param int $itemid Target item ID.
     * @param int $maxusage Pickup limit (0 = infinite).
     * @return int The new drop ID.
     */
    private function create_drop(int $itemid, int $maxusage = 5): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Test drop',
            'maxusage'        => $maxusage,
            'respawntime'     => 0,
            'code'            => substr(md5(uniqid('', true)), 0, 12),
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Success path: the item is added to the user's inventory and the return
     * structure matches the declared external definition.
     */
    public function test_collect_item_success(): void {
        global $DB, $USER;

        $item   = $this->create_item($this->instanceid, 'Gem', ['xp' => 10]);
        $dropid = $this->create_drop($item->id, 5);

        $result = collect_item::execute($this->instanceid, $dropid, $this->course->id);

        $this->assertTrue($result['success']);
        $this->assertTrue(
            $DB->record_exists('block_playerhud_inventory', [
                'userid' => $USER->id,
                'dropid' => $dropid,
                'itemid' => $item->id,
            ]),
            'A new inventory record must be created on a successful collection.'
        );

        // The response must validate against the declared return structure.
        $cleaned = external_api::clean_returnvalue(collect_item::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * A non-existent drop is caught and reported as success=false rather than
     * bubbling up an exception.
     */
    public function test_collect_item_invalid_drop_returns_failure(): void {
        $result = collect_item::execute($this->instanceid, 0, $this->course->id);

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    /**
     * Reaching the drop limit blocks further collection (reported as failure).
     */
    public function test_collect_item_limit_reached_returns_failure(): void {
        global $DB, $USER;

        $item   = $this->create_item($this->instanceid, 'Gem', ['xp' => 10]);
        $dropid = $this->create_drop($item->id, 1);

        // Seed one prior pickup so the limit (1) is already reached.
        $this->give_item_to_user((int) $USER->id, $item->id, ['dropid' => $dropid, 'source' => 'map']);

        $result = collect_item::execute($this->instanceid, $dropid, $this->course->id);

        $this->assertFalse($result['success']);
        $this->assertEquals(
            1,
            $DB->count_records('block_playerhud_inventory', ['userid' => $USER->id, 'dropid' => $dropid]),
            'No extra inventory record must be created once the limit is reached.'
        );
    }

    /**
     * A user without block/playerhud:view must not be able to collect.
     */
    public function test_collect_item_requires_view_capability(): void {
        $item   = $this->create_item($this->instanceid, 'Gem', ['xp' => 10]);
        $dropid = $this->create_drop($item->id, 5);

        // Enrolled student whose view capability is prohibited on the block context.
        $student = $this->create_student_without_view();
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        collect_item::execute($this->instanceid, $dropid, $this->course->id);
    }
}
