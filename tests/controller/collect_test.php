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
 * Tests for the collect controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use stdClass;

/**
 * Tests for the collect controller transaction logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\collect
 */
final class collect_test extends advanced_testcase {
    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    protected function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Creates an item carrying the given XP and returns its record.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $xp XP awarded by the item.
     * @return stdClass The item record.
     */
    protected function make_item(int $instanceid, int $xp): stdClass {
        global $DB;

        $id = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Gem',
            'xp'              => $xp,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('block_playerhud_items', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Creates a drop with the given usage limit and returns its record.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $itemid Item the drop belongs to.
     * @param int $maxusage Usage limit (0 = infinite).
     * @return stdClass The drop record.
     */
    protected function make_drop(int $instanceid, int $itemid, int $maxusage): stdClass {
        global $DB;

        $id = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid'          => $itemid,
            'name'            => 'Spot',
            'maxusage'        => $maxusage,
            'respawntime'     => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('block_playerhud_drops', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Seeds a player progress row with the given XP for the block instance.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $xp Current XP.
     * @return void
     */
    protected function seed_player(int $instanceid, int $userid, int $xp): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid,
            'userid'          => $userid,
            'currentxp'       => $xp,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * A finite drop awards the item XP and stores the collected item.
     *
     * @covers ::process_transaction
     */
    public function test_process_transaction_awards_xp_for_finite_drop(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $item = $this->make_item($instanceid, 100);
        $drop = $this->make_drop($instanceid, (int) $item->id, 5);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 50);

        $earned = (new collect())->process_transaction($drop, $item, $instanceid, (int) $user->id);

        $this->assertSame(100, $earned);
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertSame(150, (int) $currentxp);

        $inv = $DB->get_record('block_playerhud_inventory', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame((int) $item->id, (int) $inv->itemid);
        $this->assertSame((int) $drop->id, (int) $inv->dropid);
        $this->assertSame('map', $inv->source);
        $this->assertSame(100, (int) $inv->xpawarded);
    }

    /**
     * An infinite drop (maxusage 0) stores the item but awards no XP.
     *
     * @covers ::process_transaction
     */
    public function test_process_transaction_infinite_drop_awards_no_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $item = $this->make_item($instanceid, 100);
        $drop = $this->make_drop($instanceid, (int) $item->id, 0);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 50);

        $earned = (new collect())->process_transaction($drop, $item, $instanceid, (int) $user->id);

        $this->assertSame(0, $earned);
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertSame(50, (int) $currentxp);
        $this->assertSame(1, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_inventory', 'xpawarded', ['userid' => $user->id]));
    }

    /**
     * A zero-XP item stores the collection without changing the player XP.
     *
     * @covers ::process_transaction
     */
    public function test_process_transaction_zero_xp_item_awards_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $item = $this->make_item($instanceid, 0);
        $drop = $this->make_drop($instanceid, (int) $item->id, 5);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 50);

        $earned = (new collect())->process_transaction($drop, $item, $instanceid, (int) $user->id);

        $this->assertSame(0, $earned);
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertSame(50, (int) $currentxp);
        $this->assertSame(1, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
    }
}
