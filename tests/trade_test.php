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
use block_playerhud\game;

/**
 * Tests for the trade and economy logic.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game::get_full_trades
 */
final class trade_test extends advanced_testcase {
    /** @var int Dummy block instance ID for testing. */
    protected $instanceid;

    /**
     * Set up a block instance for the tests.
     */
    protected function setUp(): void {
        parent::setUp();

        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

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
     * Test the zero N+1 assembly of trades.
     */
    public function test_get_full_trades_assembly(): void {
        global $DB;

        // 1. Create items (Herb and Potion).
        $herb = $this->create_dummy_item('Herb');
        $potion = $this->create_dummy_item('Health Potion');

        // 2. Create the Trade.
        $trade = new \stdClass();
        $trade->blockinstanceid = $this->instanceid;
        $trade->name = 'Alchemist Trade';
        $trade->groupid = 0;
        $trade->centralized = 1;
        $trade->onetime = 1;
        $trade->timecreated = time();
        $tradeid = $DB->insert_record('block_playerhud_trades', $trade);

        // 3. Set Requirements (Costs 3 Herbs).
        $req = new \stdClass();
        $req->tradeid = $tradeid;
        $req->itemid = $herb->id;
        $req->qty = 3;
        $DB->insert_record('block_playerhud_trade_reqs', $req);

        // 4. Set Rewards (Gives 1 Potion).
        $rew = new \stdClass();
        $rew->tradeid = $tradeid;
        $rew->itemid = $potion->id;
        $rew->qty = 1;
        $DB->insert_record('block_playerhud_trade_rewards', $rew);

        // 5. Execute the bulk fetch method.
        $trades = game::get_full_trades($this->instanceid);

        $this->assertCount(1, $trades);
        $fetchedtrade = $trades[$tradeid];

        $this->assertEquals('Alchemist Trade', $fetchedtrade->name);
        $this->assertCount(1, $fetchedtrade->requirements);
        $this->assertCount(1, $fetchedtrade->rewards);

        // Verify data mapping.
        $this->assertEquals($herb->id, $fetchedtrade->requirements[0]->itemid);
        $this->assertEquals(3, $fetchedtrade->requirements[0]->qty);
        $this->assertEquals($potion->id, $fetchedtrade->rewards[0]->itemid);
        $this->assertEquals(1, $fetchedtrade->rewards[0]->qty);
    }
}
