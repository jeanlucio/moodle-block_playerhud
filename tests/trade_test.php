<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_playerhud\tests;

use advanced_testcase;
use block_playerhud\trade_manager;

/**
 * Tests for the trade and economy logic.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\trade_manager
 */
final class trade_test extends advanced_testcase {
    /** @var int Dummy block instance ID for testing. */
    protected $instanceid;

    /** @var \stdClass Dummy course. */
    protected $course;

    /**
     * Set up a block instance for the tests.
     */
    protected function setUp(): void {
        parent::setUp();

        global $DB;
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($this->course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = time();
        $bi->timemodified = time();
        $this->instanceid = $DB->insert_record('block_instances', $bi);
    }

    /**
     * Helper to create a dummy item.
     *
     * @param string $name Item name.
     * @return \stdClass The created item.
     */
    protected function create_dummy_item(string $name): \stdClass {
        global $DB;
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = $name;
        $item->xp = 0;
        $item->image = '';
        $item->description = '';
        $item->enabled = 1;
        $item->secret = 0;
        $item->timecreated = time();
        $item->timemodified = time();
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Helper to inject items into user inventory.
     *
     * @param int $userid The user ID.
     * @param int $itemid The item ID.
     * @param int $qty The quantity to give.
     */
    protected function give_item_to_user(int $userid, int $itemid, int $qty): void {
        global $DB;
        $records = [];
        for ($i = 0; $i < $qty; $i++) {
            $records[] = (object)[
                'userid' => $userid,
                'itemid' => $itemid,
                'timecreated' => time(),
                'source' => 'test',
                'dropid' => 0,
            ];
        }
        $DB->insert_records('block_playerhud_inventory', $records);
    }

    /**
     * Test the zero N+1 assembly of trades.
     */
    public function test_get_full_trades_assembly(): void {
        global $DB;
        $herb = $this->create_dummy_item('Herb');
        $potion = $this->create_dummy_item('Health Potion');

        $trade = new \stdClass();
        $trade->blockinstanceid = $this->instanceid;
        $trade->name = 'Alchemist Trade';
        $trade->groupid = 0;
        $trade->centralized = 1;
        $trade->onetime = 1;
        $trade->timecreated = time();
        $tradeid = $DB->insert_record('block_playerhud_trades', $trade);

        $req = new \stdClass();
        $req->tradeid = $tradeid;
        $req->itemid = $herb->id;
        $req->qty = 3;
        $DB->insert_record('block_playerhud_trade_reqs', $req);

        $rew = new \stdClass();
        $rew->tradeid = $tradeid;
        $rew->itemid = $potion->id;
        $rew->qty = 1;
        $DB->insert_record('block_playerhud_trade_rewards', $rew);

        $trades = trade_manager::get_full_trades($this->instanceid);

        $this->assertCount(1, $trades);
        $fetchedtrade = $trades[$tradeid];

        $this->assertEquals('Alchemist Trade', $fetchedtrade->name);
        $this->assertCount(1, $fetchedtrade->requirements);
        $this->assertCount(1, $fetchedtrade->rewards);

        $this->assertEquals($herb->id, $fetchedtrade->requirements[0]->itemid);
        $this->assertEquals(3, $fetchedtrade->requirements[0]->qty);
        $this->assertEquals($potion->id, $fetchedtrade->rewards[0]->itemid);
        $this->assertEquals(1, $fetchedtrade->rewards[0]->qty);
    }

    /**
     * Test 1: Insufficient funds block.
     */
    public function test_trade_insufficient_funds(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $coin = $this->create_dummy_item('Coin');
        $potion = $this->create_dummy_item('Health Potion');

        $this->give_item_to_user($user->id, $coin->id, 3);

        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Buy Potion',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);

        $DB->insert_record('block_playerhud_trade_reqs', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 5,
        ]);

        $DB->insert_record('block_playerhud_trade_rewards', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $potion->id,
            'qty'     => 1,
        ]);

        try {
            trade_manager::execute_trade($tradeid, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception due to insufficient funds.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_trade_insufficient', $e->errorcode);
        }
    }

    /**
     * Test 2: Atomic transaction success.
     */
    public function test_trade_success_atomic(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $coin = $this->create_dummy_item('Coin');
        $potion = $this->create_dummy_item('Health Potion');

        $this->give_item_to_user($user->id, $coin->id, 5);

        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Buy Potion',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);

        $DB->insert_record('block_playerhud_trade_reqs', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 5,
        ]);

        $DB->insert_record('block_playerhud_trade_rewards', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $potion->id,
            'qty'     => 1,
        ]);

        $result = trade_manager::execute_trade($tradeid, $user->id, $this->instanceid, $this->course->id);

        $this->assertStringContainsString('Health Potion', $result);

        $coinsleft = $DB->count_records('block_playerhud_inventory', ['userid' => $user->id, 'itemid' => $coin->id]);
        $this->assertEquals(0, $coinsleft, 'Coins should be fully deducted.');

        $potionsowned = $DB->count_records('block_playerhud_inventory', ['userid' => $user->id, 'itemid' => $potion->id]);
        $this->assertEquals(1, $potionsowned, 'Potion should be awarded.');
    }

    /**
     * Test 3: One-time limit enforcement.
     */
    public function test_trade_onetime_limit(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $coin = $this->create_dummy_item('Coin');
        $special = $this->create_dummy_item('Special Sword');

        $this->give_item_to_user($user->id, $coin->id, 10);

        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Buy Sword',
            'groupid'         => 0,
            'onetime'         => 1,
            'timecreated'     => time(),
        ]);

        $DB->insert_record('block_playerhud_trade_reqs', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 5,
        ]);

        $DB->insert_record('block_playerhud_trade_rewards', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $special->id,
            'qty'     => 1,
        ]);

        trade_manager::execute_trade($tradeid, $user->id, $this->instanceid, $this->course->id);

        try {
            trade_manager::execute_trade($tradeid, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception due to one-time restriction.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_trade_onetime', $e->errorcode);
        }
    }

    /**
     * Test 4: Group restriction.
     */
    public function test_trade_group_restriction(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $coin = $this->create_dummy_item('Coin');
        $shield = $this->create_dummy_item('Shield');

        $this->give_item_to_user($user->id, $coin->id, 5);

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);

        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Group Trade',
            'groupid'         => $group->id,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);

        $DB->insert_record('block_playerhud_trade_reqs', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 1,
        ]);

        $DB->insert_record('block_playerhud_trade_rewards', (object)[
            'tradeid' => $tradeid,
            'itemid'  => $shield->id,
            'qty'     => 1,
        ]);

        try {
            trade_manager::execute_trade($tradeid, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception due to group restriction.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_trade_group', $e->errorcode);
        }
    }
}
