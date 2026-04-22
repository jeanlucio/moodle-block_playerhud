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
use block_playerhud\quest;

/**
 * Tests for the quest logic class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\quest
 */
final class quest_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var \stdClass Dummy course. */
    protected $course;

    /**
     * Set up a block instance and course for all tests.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($this->course->id);

        $bi = new \stdClass();
        $bi->blockname        = 'playerhud';
        $bi->parentcontextid  = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern  = 'course-view-*';
        $bi->defaultregion    = 'side-pre';
        $bi->defaultweight    = 0;
        $bi->timecreated      = time();
        $bi->timemodified     = time();

        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels   = 10;
        $bi->configdata = base64_encode(serialize($config));

        $this->instanceid = $DB->insert_record('block_instances', $bi);
    }

    /**
     * Create a quest record in the database.
     *
     * @param int    $type        Quest type constant.
     * @param string $requirement Requirement value (numeric or cmid).
     * @param int    $rewardxp    XP reward.
     * @param int    $rewarditem  Reward item ID (0 = none).
     * @param int    $reqitemid   Required item ID (for TYPE_SPECIFIC_ITEM).
     * @return \stdClass The created quest record.
     */
    protected function create_quest(
        int $type,
        string $requirement,
        int $rewardxp = 0,
        int $rewarditem = 0,
        int $reqitemid = 0
    ): \stdClass {
        global $DB;
        $q = new \stdClass();
        $q->blockinstanceid  = $this->instanceid;
        $q->name             = 'Test Quest';
        $q->description      = '';
        $q->type             = $type;
        $q->requirement      = $requirement;
        $q->req_itemid       = $reqitemid;
        $q->reward_xp        = $rewardxp;
        $q->reward_itemid    = $rewarditem;
        $q->required_class_id = '0';
        $q->image_todo       = '📋';
        $q->image_done       = '🏅';
        $q->enabled          = 1;
        $q->timecreated      = time();
        $q->timemodified     = time();
        $q->id               = $DB->insert_record('block_playerhud_quests', $q);
        return $q;
    }

    /**
     * Create a dummy item belonging to this block instance.
     *
     * @param string $name Item name.
     * @return \stdClass The created item.
     */
    protected function create_dummy_item(string $name): \stdClass {
        global $DB;
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name            = $name;
        $item->xp              = 0;
        $item->image           = '';
        $item->description     = '';
        $item->enabled         = 1;
        $item->secret          = 0;
        $item->timecreated     = time();
        $item->timemodified    = time();
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Give N copies of an item to a user.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID.
     * @param int $qty    Quantity.
     */
    protected function give_item(int $userid, int $itemid, int $qty = 1): void {
        global $DB;
        $records = [];
        for ($i = 0; $i < $qty; $i++) {
            $records[] = (object)[
                'userid'      => $userid,
                'itemid'      => $itemid,
                'dropid'      => 0,
                'timecreated' => time(),
                'source'      => 'test',
            ];
        }
        $DB->insert_records('block_playerhud_inventory', $records);
    }

    /**
     * Set the current XP for a user in the player table.
     *
     * @param int $userid User ID.
     * @param int $xp     XP amount.
     */
    protected function set_player_xp(int $userid, int $xp): void {
        global $DB;
        $existing = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $userid,
        ]);
        if ($existing) {
            $existing->currentxp    = $xp;
            $existing->timemodified = time();
            $DB->update_record('block_playerhud_user', $existing);
        } else {
            $DB->insert_record('block_playerhud_user', (object)[
                'blockinstanceid'   => $this->instanceid,
                'userid'            => $userid,
                'currentxp'         => $xp,
                'enable_gamification' => 1,
                'ranking_visibility'  => 1,
                'last_inventory_view' => 0,
                'last_shop_view'      => 0,
                'timecreated'       => time(),
                'timemodified'      => time(),
            ]);
        }
    }

    /**
     * Log a trade execution for a user (simulates trade_manager output).
     *
     * @param int $userid  User ID.
     * @param int $tradeid Trade ID.
     * @param int $qty     Number of times to log the trade.
     */
    protected function log_trade(int $userid, int $tradeid, int $qty = 1): void {
        global $DB;
        $records = [];
        for ($i = 0; $i < $qty; $i++) {
            $records[] = (object)[
                'tradeid'     => $tradeid,
                'userid'      => $userid,
                'timecreated' => time(),
            ];
        }
        $DB->insert_records('block_playerhud_trade_log', $records);
    }

    /**
     * TYPE_LEVEL: player not yet at required level.
     *
     * @covers ::check_status
     */
    public function test_check_status_level_not_met(): void {
        $quest = $this->create_quest(quest::TYPE_LEVEL, '5');
        $status = quest::check_status($quest, 1, $this->course->id, 0, 3);

        $this->assertFalse($status->completed);
        $this->assertEquals(60, $status->progress);
        $this->assertStringContainsString('3', $status->label);
        $this->assertStringContainsString('5', $status->label);
    }

    /**
     * TYPE_LEVEL: player exactly at required level.
     *
     * @covers ::check_status
     */
    public function test_check_status_level_met(): void {
        $quest = $this->create_quest(quest::TYPE_LEVEL, '5');
        $status = quest::check_status($quest, 1, $this->course->id, 0, 5);

        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
    }

    /**
     * TYPE_LEVEL: player above required level still completes.
     *
     * @covers ::check_status
     */
    public function test_check_status_level_exceeded(): void {
        $quest = $this->create_quest(quest::TYPE_LEVEL, '3');
        $status = quest::check_status($quest, 1, $this->course->id, 0, 7);

        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
    }

    /**
     * TYPE_XP_TOTAL: player has insufficient XP.
     *
     * @covers ::check_status
     */
    public function test_check_status_xp_total_not_met(): void {
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '200');
        $status = quest::check_status($quest, 1, $this->course->id, 100, 1);

        $this->assertFalse($status->completed);
        $this->assertEquals(50, $status->progress);
    }

    /**
     * TYPE_XP_TOTAL: player meets XP requirement.
     *
     * @covers ::check_status
     */
    public function test_check_status_xp_total_met(): void {
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '200');
        $status = quest::check_status($quest, 1, $this->course->id, 200, 2);

        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
    }

    /**
     * TYPE_UNIQUE_ITEMS: player has fewer unique items than required.
     *
     * @covers ::check_status
     */
    public function test_check_status_unique_items_not_met(): void {
        $user  = $this->getDataGenerator()->create_user();
        $itema = $this->create_dummy_item('Sword');
        $itemb = $this->create_dummy_item('Shield');

        $this->give_item($user->id, $itema->id);
        $this->give_item($user->id, $itemb->id);

        $quest = $this->create_quest(quest::TYPE_UNIQUE_ITEMS, '3');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
    }

    /**
     * TYPE_UNIQUE_ITEMS: player reaches the unique item count.
     *
     * @covers ::check_status
     */
    public function test_check_status_unique_items_met(): void {
        $user  = $this->getDataGenerator()->create_user();
        $itema = $this->create_dummy_item('Sword');
        $itemb = $this->create_dummy_item('Shield');
        $itemc = $this->create_dummy_item('Potion');

        $this->give_item($user->id, $itema->id);
        $this->give_item($user->id, $itemb->id);
        $this->give_item($user->id, $itemc->id);

        $quest = $this->create_quest(quest::TYPE_UNIQUE_ITEMS, '3');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
    }

    /**
     * TYPE_UNIQUE_ITEMS: items from another block instance must not count.
     *
     * @covers ::check_status
     */
    public function test_check_status_unique_items_scoped_to_instance(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        // Create a second block instance.
        $course2 = $this->getDataGenerator()->create_course();
        $ctx2    = \context_course::instance($course2->id);
        $bi2id   = $DB->insert_record('block_instances', (object)[
            'blockname'        => 'playerhud',
            'parentcontextid'  => $ctx2->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'  => 'course-view-*',
            'defaultregion'    => 'side-pre',
            'defaultweight'    => 0,
            'configdata'       => base64_encode(serialize(new \stdClass())),
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        // Give 3 items from the OTHER instance and 1 from this instance.
        $foreignitem = new \stdClass();
        $foreignitem->blockinstanceid = $bi2id;
        $foreignitem->name            = 'Foreign Herb';
        $foreignitem->xp              = 0;
        $foreignitem->image           = '';
        $foreignitem->description     = '';
        $foreignitem->enabled         = 1;
        $foreignitem->secret          = 0;
        $foreignitem->timecreated     = time();
        $foreignitem->timemodified    = time();
        $foreignitem->id = $DB->insert_record('block_playerhud_items', $foreignitem);

        $this->give_item($user->id, $foreignitem->id, 3);

        $localitem = $this->create_dummy_item('Local Gem');
        $this->give_item($user->id, $localitem->id);

        // Quest requires 3 unique items from THIS instance — should NOT be met.
        $quest  = $this->create_quest(quest::TYPE_UNIQUE_ITEMS, '3');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
    }

    /**
     * TYPE_SPECIFIC_ITEM: player has fewer copies than required.
     *
     * @covers ::check_status
     */
    public function test_check_status_specific_item_not_met(): void {
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Iron Ore');

        $this->give_item($user->id, $item->id, 2);

        $quest  = $this->create_quest(quest::TYPE_SPECIFIC_ITEM, '5', 0, 0, $item->id);
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
        $this->assertEquals(40, $status->progress);
    }

    /**
     * TYPE_SPECIFIC_ITEM: player has exactly the required number of copies.
     *
     * @covers ::check_status
     */
    public function test_check_status_specific_item_met(): void {
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Iron Ore');

        $this->give_item($user->id, $item->id, 5);

        $quest  = $this->create_quest(quest::TYPE_SPECIFIC_ITEM, '5', 0, 0, $item->id);
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
    }

    /**
     * TYPE_TOTAL_ITEMS: counts all non-revoked items (including duplicates).
     *
     * @covers ::check_status
     */
    public function test_check_status_total_items_met(): void {
        $user  = $this->getDataGenerator()->create_user();
        $item  = $this->create_dummy_item('Coin');

        $this->give_item($user->id, $item->id, 5);

        $quest  = $this->create_quest(quest::TYPE_TOTAL_ITEMS, '5');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertTrue($status->completed);
    }

    /**
     * TYPE_TOTAL_ITEMS: revoked items must not count toward the total.
     *
     * @covers ::check_status
     */
    public function test_check_status_total_items_excludes_revoked(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Herb');

        // Give 4 normal and 1 revoked.
        $this->give_item($user->id, $item->id, 4);
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $user->id,
            'itemid'      => $item->id,
            'dropid'      => 0,
            'timecreated' => time(),
            'source'      => 'revoked',
        ]);

        $quest  = $this->create_quest(quest::TYPE_TOTAL_ITEMS, '5');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
        $this->assertEquals(80, $status->progress);
    }

    /**
     * TYPE_TRADES: player has not completed enough trades.
     *
     * @covers ::check_status
     */
    public function test_check_status_trades_not_met(): void {
        global $DB;

        $user    = $this->getDataGenerator()->create_user();
        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Dummy Trade',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        $this->log_trade($user->id, $tradeid, 3);

        $quest  = $this->create_quest(quest::TYPE_TRADES, '5');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
        $this->assertEquals(60, $status->progress);
    }

    /**
     * TYPE_TRADES: player meets the trade count.
     *
     * @covers ::check_status
     */
    public function test_check_status_trades_met(): void {
        global $DB;

        $user    = $this->getDataGenerator()->create_user();
        $tradeid = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Dummy Trade',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        $this->log_trade($user->id, $tradeid, 5);

        $quest  = $this->create_quest(quest::TYPE_TRADES, '5');
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertTrue($status->completed);
    }

    /**
     * TYPE_SPECIFIC_TRADE: counts only a specific trade, not others.
     *
     * @covers ::check_status
     */
    public function test_check_status_specific_trade_isolation(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        $tradea = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Trade A',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        $tradeb = $DB->insert_record('block_playerhud_trades', (object)[
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Trade B',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);

        // Execute trade A 3 times and trade B 5 times.
        $this->log_trade($user->id, $tradea, 3);
        $this->log_trade($user->id, $tradeb, 5);

        // Quest requires trade A done 5 times — should NOT be met.
        $quest  = $this->create_quest(quest::TYPE_SPECIFIC_TRADE, '5', 0, 0, $tradea);
        $status = quest::check_status($quest, $user->id, $this->course->id, 0, 1);

        $this->assertFalse($status->completed);
        $this->assertEquals(60, $status->progress);
    }

    /**
     * TYPE_ACTIVITY: requires real Moodle completion infrastructure.
     * Skipped in unit test — covered by integration/behat tests.
     *
     * @covers ::check_status
     */
    public function test_check_status_activity_skipped(): void {
        $this->markTestSkipped(
            'TYPE_ACTIVITY requires a real course module with completion enabled.'
            . ' Covered by integration/behat tests.'
        );
    }

    /**
     * Successful claim: XP is credited to the player and log entry is written.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_grants_xp(): void {
        global $DB;

        $user  = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 100);

        $this->set_player_xp($user->id, 50);

        $result = quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $this->assertStringContainsString('+100 XP', $result);

        $player = \block_playerhud\game::get_player($this->instanceid, $user->id);
        $this->assertEquals(150, $player->currentxp);

        $logged = $DB->record_exists('block_playerhud_quest_log', [
            'questid' => $quest->id,
            'userid'  => $user->id,
        ]);
        $this->assertTrue($logged);
    }

    /**
     * Successful claim: item is added to inventory and log entry is written.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_grants_item(): void {
        global $DB;

        $user   = $this->getDataGenerator()->create_user();
        $reward = $this->create_dummy_item('Golden Crown');
        $quest  = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 0, $reward->id);

        $this->set_player_xp($user->id, 50);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $invinventory = $DB->count_records('block_playerhud_inventory', [
            'userid' => $user->id,
            'itemid' => $reward->id,
            'source' => 'quest',
        ]);
        $this->assertEquals(1, $invinventory);
    }

    /**
     * Claiming the same quest twice must throw error_quest_already_claimed.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_already_claimed(): void {
        $user  = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 10);

        $this->set_player_xp($user->id, 50);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        try {
            quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception error_quest_already_claimed.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_quest_already_claimed', $e->errorcode);
        }
    }

    /**
     * Claiming a disabled quest must throw error_quest_invalid.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_disabled_quest(): void {
        global $DB;

        $user  = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 10);

        $DB->set_field('block_playerhud_quests', 'enabled', 0, ['id' => $quest->id]);

        try {
            quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception error_quest_invalid.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_quest_invalid', $e->errorcode);
        }
    }

    /**
     * Claiming a quest whose requirements are not yet met must throw error_quest_requirements.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_requirements_not_met(): void {
        $user  = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '500', 10);

        $this->set_player_xp($user->id, 100);

        try {
            quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);
            $this->fail('Expected moodle_exception error_quest_requirements.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('error_quest_requirements', $e->errorcode);
        }
    }

    /**
     * Claim must not modify XP when reward_xp is zero.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_zero_xp_no_change(): void {
        $user  = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 0);

        $this->set_player_xp($user->id, 50);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $player = \block_playerhud\game::get_player($this->instanceid, $user->id);
        $this->assertEquals(50, $player->currentxp);
    }
}
