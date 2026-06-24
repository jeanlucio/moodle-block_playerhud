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
 * Tests for the trades controller.
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
 * Tests for the trades controller persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\trades
 */
final class trades_test extends advanced_testcase {
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
     * Creates an item for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new item ID.
     */
    protected function make_item(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Item',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Inserts a trade directly for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param string $name Trade name.
     * @return int The new trade ID.
     */
    protected function seed_trade(int $instanceid, string $name): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => $name,
            'timecreated'     => time(),
        ]);
    }

    /**
     * Builds submitted trade form data with the common defaults.
     *
     * @param array $overrides Field overrides merged over the defaults.
     * @return stdClass
     */
    protected function trade_data(array $overrides): stdClass {
        return (object) array_merge([
            'name'         => 'Deal',
            'centralized'  => 1,
            'onetime'      => 0,
            'groupid'      => 0,
            'tradeid'      => 0,
            'repeats_req'  => 3,
            'repeats_give' => 3,
        ], $overrides);
    }

    /**
     * A new trade is inserted together with its requirements and rewards.
     *
     * @covers ::save_trade
     */
    public function test_save_trade_inserts_with_reqs_and_rewards(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $needitem = $this->make_item($instanceid);
        $giveitem = $this->make_item($instanceid);

        $data = $this->trade_data([
            'name'         => 'Sword for gold',
            'req_itemid_0' => $needitem,
            'req_qty_0'    => 2,
            'give_itemid_0' => $giveitem,
            'give_qty_0'   => 3,
        ]);

        $tradeid = (new trades())->save_trade($data, $instanceid);

        $trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid], '*', MUST_EXIST);
        $this->assertSame($instanceid, (int) $trade->blockinstanceid);
        $this->assertSame('Sword for gold', $trade->name);

        $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $tradeid], '*', MUST_EXIST);
        $this->assertSame($needitem, (int) $req->itemid);
        $this->assertSame(2, (int) $req->qty);

        $reward = $DB->get_record('block_playerhud_trade_rewards', ['tradeid' => $tradeid], '*', MUST_EXIST);
        $this->assertSame($giveitem, (int) $reward->itemid);
        $this->assertSame(3, (int) $reward->qty);
    }

    /**
     * Saving with a trade ID updates the trade and replaces its requirements.
     *
     * @covers ::save_trade
     */
    public function test_save_trade_updates_and_replaces_reqs(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $olditem = $this->make_item($instanceid);
        $newitem = $this->make_item($instanceid);

        $tradeid = $this->seed_trade($instanceid, 'Old deal');
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $olditem,
            'qty'     => 5,
        ]);

        $data = $this->trade_data([
            'name'         => 'New deal',
            'tradeid'      => $tradeid,
            'req_itemid_0' => $newitem,
            'req_qty_0'    => 1,
        ]);

        $returned = (new trades())->save_trade($data, $instanceid);

        $this->assertSame($tradeid, $returned);
        $trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid], '*', MUST_EXIST);
        $this->assertSame('New deal', $trade->name);

        $reqs = $DB->get_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
        $this->assertCount(1, $reqs);
        $req = reset($reqs);
        $this->assertSame($newitem, (int) $req->itemid);
    }

    /**
     * A trade belonging to another instance cannot be updated.
     *
     * @covers ::save_trade
     */
    public function test_save_trade_rejects_foreign_instance(): void {
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $tradeid = $this->seed_trade($instancea, 'Owned by A');

        $data = $this->trade_data(['name' => 'Hijack', 'tradeid' => $tradeid]);

        $this->expectException(\dml_missing_record_exception::class);
        (new trades())->save_trade($data, $instanceb);
    }

    /**
     * Requirement items from another instance are rejected and not stored.
     *
     * @covers ::save_trade
     */
    public function test_save_trade_skips_foreign_items(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $owneditem = $this->make_item($instanceid);
        $foreignitem = $this->make_item($this->make_instance());

        $data = $this->trade_data([
            'req_itemid_0' => $foreignitem,
            'req_qty_0'    => 1,
            'req_itemid_1' => $owneditem,
            'req_qty_1'    => 1,
        ]);

        $tradeid = (new trades())->save_trade($data, $instanceid);

        $reqs = $DB->get_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
        $this->assertCount(1, $reqs);
        $req = reset($reqs);
        $this->assertSame($owneditem, (int) $req->itemid);
    }
}
