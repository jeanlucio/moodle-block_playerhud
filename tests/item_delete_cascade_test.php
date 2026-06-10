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

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\controller\items;
use context_block;

/**
 * Tests for items controller: find_orphaned_trades, delete_item, bulk_delete_items.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\controller\items
 */
final class item_delete_cascade_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var context_block Block context. */
    protected context_block $context;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->instanceid = $this->create_block_instance();
        $this->context = context_block::instance($this->instanceid);
    }

    // Tests for find_orphaned_trades() — single item scenarios.

    /**
     * Item is the only requirement → trade is orphaned.
     */
    public function test_sole_req_item_orphans_trade(): void {
        $coin   = $this->make_item('Coin');
        $sword  = $this->make_item('Sword');
        $tradeid = $this->make_trade('Sword trade', req: [$coin->id], rew: [$sword->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin->id]);

        $this->assertArrayHasKey($tradeid, $orphans);
        $this->assertCount(1, $orphans);
    }

    /**
     * Item is one of two requirements → trade survives (still has another req).
     */
    public function test_one_of_two_req_items_does_not_orphan_trade(): void {
        $coin1  = $this->make_item('Coin1');
        $coin2  = $this->make_item('Coin2');
        $sword  = $this->make_item('Sword');
        $this->make_trade('Dual req trade', req: [$coin1->id, $coin2->id], rew: [$sword->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin1->id]);

        $this->assertCount(0, $orphans);
    }

    /**
     * Item is the only reward → trade is orphaned.
     */
    public function test_sole_reward_item_orphans_trade(): void {
        $coin  = $this->make_item('Coin');
        $sword = $this->make_item('Sword');
        $tradeid = $this->make_trade('Sword trade', req: [$coin->id], rew: [$sword->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$sword->id]);

        $this->assertArrayHasKey($tradeid, $orphans);
        $this->assertCount(1, $orphans);
    }

    /**
     * Item is one of two rewards → trade survives (still has another reward).
     */
    public function test_one_of_two_reward_items_does_not_orphan_trade(): void {
        $coin   = $this->make_item('Coin');
        $sword  = $this->make_item('Sword');
        $shield = $this->make_item('Shield');
        $this->make_trade('Dual reward trade', req: [$coin->id], rew: [$sword->id, $shield->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$sword->id]);

        $this->assertCount(0, $orphans);
    }

    /**
     * Item is both the sole req and the sole reward → trade appears exactly once.
     */
    public function test_item_as_sole_req_and_sole_reward_appears_once(): void {
        $coin    = $this->make_item('Coin');
        $tradeid = $this->make_trade('Self trade', req: [$coin->id], rew: [$coin->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin->id]);

        $this->assertArrayHasKey($tradeid, $orphans);
        $this->assertCount(1, $orphans);
    }

    /**
     * No trades exist → returns empty array.
     */
    public function test_no_trades_returns_empty(): void {
        $coin = $this->make_item('Coin');

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin->id]);

        $this->assertCount(0, $orphans);
    }

    /**
     * Empty item list → returns empty array without DB errors.
     */
    public function test_empty_item_list_returns_empty(): void {
        $orphans = items::find_orphaned_trades($this->instanceid, []);

        $this->assertCount(0, $orphans);
    }

    // Tests for find_orphaned_trades() — bulk and multi-item scenarios.

    /**
     * Two items together empty a trade's req side; neither alone would.
     */
    public function test_bulk_two_items_together_orphan_trade(): void {
        $coin1  = $this->make_item('Coin1');
        $coin2  = $this->make_item('Coin2');
        $sword  = $this->make_item('Sword');
        $tradeid = $this->make_trade('Dual req trade', req: [$coin1->id, $coin2->id], rew: [$sword->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin1->id, $coin2->id]);

        $this->assertArrayHasKey($tradeid, $orphans);
    }

    /**
     * Deleting item A orphans trade 1; deleting item B orphans trade 2; bulk returns both.
     */
    public function test_bulk_each_item_orphans_separate_trade(): void {
        $coina   = $this->make_item('CoinA');
        $coinb   = $this->make_item('CoinB');
        $reward  = $this->make_item('Reward');
        $tradeid1 = $this->make_trade('Trade A', req: [$coina->id], rew: [$reward->id]);
        $tradeid2 = $this->make_trade('Trade B', req: [$coinb->id], rew: [$reward->id]);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coina->id, $coinb->id]);

        $this->assertArrayHasKey($tradeid1, $orphans);
        $this->assertArrayHasKey($tradeid2, $orphans);
        $this->assertCount(2, $orphans);
    }

    /**
     * Trade belongs to a different instance → not returned.
     */
    public function test_cross_instance_trade_not_returned(): void {
        global $DB;

        $otherid = $this->create_block_instance();
        $coin = $this->make_item('Coin', instanceid: $otherid);
        $rew  = $this->make_item('Reward', instanceid: $otherid);
        $this->make_trade('Other trade', req: [$coin->id], rew: [$rew->id], instanceid: $otherid);

        $orphans = items::find_orphaned_trades($this->instanceid, [$coin->id]);

        $this->assertCount(0, $orphans);
    }

    // Tests for delete_item() — database state assertions.

    /**
     * Deleting an item with no affected trades removes the item record.
     */
    public function test_delete_item_removes_item_record(): void {
        global $DB;

        $coin  = $this->make_item('Coin');
        $sword = $this->make_item('Sword');
        $this->make_trade('Trade', req: [$coin->id, $sword->id], rew: [$sword->id]);

        items::delete_item($coin, $this->instanceid, $this->context);

        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $coin->id]));
    }

    /**
     * Cascading delete removes the orphaned trade record.
     */
    public function test_delete_item_cascades_orphaned_trade(): void {
        global $DB;

        $coin    = $this->make_item('Coin');
        $sword   = $this->make_item('Sword');
        $tradeid = $this->make_trade('Trade', req: [$coin->id], rew: [$sword->id]);

        items::delete_item($coin, $this->instanceid, $this->context, [$tradeid]);

        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $coin->id]));
        $this->assertFalse($DB->record_exists('block_playerhud_trades', ['id' => $tradeid]));
        $this->assertFalse($DB->record_exists('block_playerhud_trade_reqs', ['tradeid' => $tradeid]));
        $this->assertFalse($DB->record_exists('block_playerhud_trade_rewards', ['tradeid' => $tradeid]));
    }

    /**
     * Non-orphaned trade (has another req) survives the deletion.
     */
    public function test_delete_item_does_not_remove_non_orphaned_trade(): void {
        global $DB;

        $coin1   = $this->make_item('Coin1');
        $coin2   = $this->make_item('Coin2');
        $sword   = $this->make_item('Sword');
        $tradeid = $this->make_trade('Trade', req: [$coin1->id, $coin2->id], rew: [$sword->id]);

        items::delete_item($coin1, $this->instanceid, $this->context);

        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $coin1->id]));
        $this->assertTrue($DB->record_exists('block_playerhud_trades', ['id' => $tradeid]));
        $this->assertFalse(
            $DB->record_exists('block_playerhud_trade_reqs', ['tradeid' => $tradeid, 'itemid' => $coin1->id])
        );
        $this->assertTrue(
            $DB->record_exists('block_playerhud_trade_reqs', ['tradeid' => $tradeid, 'itemid' => $coin2->id])
        );
    }

    // Tests for bulk_delete_items() — database state assertions.

    /**
     * Bulk delete removes all selected item records.
     */
    public function test_bulk_delete_removes_all_items(): void {
        global $DB;

        $coin1 = $this->make_item('Coin1');
        $coin2 = $this->make_item('Coin2');
        $items = [$coin1->id => $coin1, $coin2->id => $coin2];

        items::bulk_delete_items($items, $this->instanceid, $this->context);

        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $coin1->id]));
        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $coin2->id]));
    }

    /**
     * Bulk delete with cascade removes the orphaned trade.
     */
    public function test_bulk_delete_cascades_orphaned_trade(): void {
        global $DB;

        $coin1   = $this->make_item('Coin1');
        $coin2   = $this->make_item('Coin2');
        $sword   = $this->make_item('Sword');
        $tradeid = $this->make_trade('Trade', req: [$coin1->id, $coin2->id], rew: [$sword->id]);

        $items = [$coin1->id => $coin1, $coin2->id => $coin2];
        items::bulk_delete_items($items, $this->instanceid, $this->context, [$tradeid]);

        $this->assertFalse($DB->record_exists('block_playerhud_trades', ['id' => $tradeid]));
    }

    // Helper methods.

    /**
     * Creates a minimal block_instances row and returns its ID.
     *
     * @return int New instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        return $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Inserts a minimal item record and returns it with id populated.
     *
     * @param string $name Item name.
     * @param int|null $instanceid Defaults to $this->instanceid.
     * @return \stdClass Inserted record.
     */
    private function make_item(string $name, ?int $instanceid = null): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid'   => $instanceid ?? $this->instanceid,
            'name'              => $name,
            'image'             => '',
            'description'       => '',
            'xp'                => 0,
            'enabled'           => 1,
            'tradable'          => 1,
            'secret'            => 0,
            'required_class_id' => '0',
            'action_type'       => '',
            'action_value'      => '',
            'timecreated'       => time(),
            'timemodified'      => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Inserts a trade with given req and reward item IDs. Returns the trade ID.
     *
     * @param string $name Trade name.
     * @param int[] $req Requirement item IDs.
     * @param int[] $rew Reward item IDs.
     * @param int|null $instanceid Defaults to $this->instanceid.
     * @return int Trade ID.
     */
    private function make_trade(string $name, array $req, array $rew, ?int $instanceid = null): int {
        global $DB;
        $iid = $instanceid ?? $this->instanceid;
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $iid,
            'name'            => $name,
            'groupid'         => 0,
            'centralized'     => 1,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        foreach ($req as $itemid) {
            $DB->insert_record('block_playerhud_trade_reqs', (object) [
                'tradeid' => $tradeid,
                'itemid'  => $itemid,
                'qty'     => 1,
            ]);
        }
        foreach ($rew as $itemid) {
            $DB->insert_record('block_playerhud_trade_rewards', (object) [
                'tradeid' => $tradeid,
                'itemid'  => $itemid,
                'qty'     => 1,
            ]);
        }
        return $tradeid;
    }
}
