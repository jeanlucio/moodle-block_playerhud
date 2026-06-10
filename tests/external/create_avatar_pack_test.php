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
 * Tests for the create_avatar_pack web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the create_avatar_pack web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\create_avatar_pack
 */
final class create_avatar_pack_test extends external_base_testcase {
    /**
     * A fresh instance receives all 17 pre-defined avatar items.
     */
    public function test_create_avatar_pack_creates_17_items(): void {
        global $DB;

        $result = create_avatar_pack::execute($this->instanceid, $this->course->id);

        $this->assertEquals(17, $result['created']);
        $total = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(17, $total);
    }

    /**
     * Every item created by the pack must have action_type = 'avatar_profile'.
     */
    public function test_create_avatar_pack_all_items_have_avatar_action_type(): void {
        global $DB;

        create_avatar_pack::execute($this->instanceid, $this->course->id);

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        foreach ($items as $item) {
            $this->assertEquals(
                'avatar_profile',
                $item->action_type,
                "Item '{$item->name}' must have action_type=avatar_profile."
            );
        }
    }

    /**
     * An item already in the instance with the same emoji image is skipped,
     * so the pack creates 16 instead of 17.
     */
    public function test_create_avatar_pack_skips_existing_emoji(): void {
        global $DB;

        // Pre-create one item using the first avatar's emoji (🧛🏻‍♂️).
        $this->create_item($this->instanceid, 'Pre-existing vampire', [
            'image'        => '🧛🏻‍♂️',
            'tradable'     => 0,
            'action_type'  => 'avatar_profile',
        ]);

        $result = create_avatar_pack::execute($this->instanceid, $this->course->id);

        $this->assertEquals(16, $result['created'], 'One avatar with matching emoji must be skipped.');
        $total = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(17, $total, '16 new + 1 pre-existing = 17 total.');
    }

    /**
     * Calling create_avatar_pack twice returns created=0 on the second call.
     */
    public function test_create_avatar_pack_idempotent_second_call_creates_zero(): void {
        create_avatar_pack::execute($this->instanceid, $this->course->id);

        $second = create_avatar_pack::execute($this->instanceid, $this->course->id);

        $this->assertEquals(0, $second['created'], 'No new items must be created when all emojis already exist.');
    }

    /**
     * A student without block/playerhud:manage must not be able to create the avatar pack.
     */
    public function test_create_avatar_pack_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_avatar_pack::execute($this->instanceid, $this->course->id);
    }
}
