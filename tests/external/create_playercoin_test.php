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
 * Tests for the create_playercoin web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the create_playercoin web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\create_playercoin
 */
final class create_playercoin_test extends external_base_testcase {
    /**
     * First call creates the PlayerCoin item and returns created=true.
     */
    public function test_create_playercoin_creates_new_item(): void {
        global $DB;

        $result = create_playercoin::execute($this->instanceid, $this->course->id);

        $this->assertTrue($result['created']);
        $this->assertGreaterThan(0, $result['itemid']);
        $this->assertStringContainsString((string) $result['itemid'], $result['edit_url']);

        $item = $DB->get_record('block_playerhud_items', ['id' => $result['itemid']], '*', MUST_EXIST);
        $this->assertEquals('PlayerCoin', $item->name);
        $this->assertEquals('🪙', $item->image);
        $this->assertEquals($this->instanceid, (int) $item->blockinstanceid);
        $this->assertEquals('playercoin', $item->action_type, 'PlayerCoin must be tagged with action_type=playercoin.');
    }

    /**
     * Second call returns created=false and the same itemid — no duplicate created.
     */
    public function test_create_playercoin_idempotent_returns_existing(): void {
        global $DB;

        $first  = create_playercoin::execute($this->instanceid, $this->course->id);
        $second = create_playercoin::execute($this->instanceid, $this->course->id);

        $this->assertFalse($second['created']);
        $this->assertEquals($first['itemid'], $second['itemid']);

        $count = $DB->count_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'playercoin',
        ]);
        $this->assertEquals(1, $count, 'Only one PlayerCoin must exist after two calls.');
    }

    /**
     * A student without block/playerhud:manage must not be able to create PlayerCoin.
     */
    public function test_create_playercoin_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_playercoin::execute($this->instanceid, $this->course->id);
    }
}
