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
 * @coversDefaultClass \block_playerhud\quest
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
     * TYPE_ACTIVITY: quest is incomplete before the activity is finished,
     * and completed immediately after the user marks it as done.
     *
     * @covers ::check_status
     */
    public function test_check_status_activity_completion(): void {
        global $CFG, $DB;

        $CFG->enablecompletion = 1;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Use cm_info (not stdClass) — required by completion_info::update_state in Moodle 5.2+.
        $modinfo = get_fast_modinfo($course);
        $cminfo  = $modinfo->get_cm($page->cmid);

        // Create a block instance bound to this course so the quest FK is valid.
        $coursecontext = \context_course::instance($course->id);
        $bi = new \stdClass();
        $bi->blockname        = 'playerhud';
        $bi->parentcontextid  = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern  = 'course-view-*';
        $bi->defaultregion    = 'side-pre';
        $bi->defaultweight    = 0;
        $bi->timecreated      = time();
        $bi->timemodified     = time();
        $bi->configdata       = base64_encode(serialize(new \stdClass()));
        $instanceid = $DB->insert_record('block_instances', $bi);

        $quest = $this->create_quest(quest::TYPE_ACTIVITY, (string)$cminfo->id);
        $DB->set_field('block_playerhud_quests', 'blockinstanceid', $instanceid, ['id' => $quest->id]);

        // Before completion: quest must be incomplete.
        $status = quest::check_status($quest, $user->id, $course->id, 0, 1);
        $this->assertFalse($status->completed);
        $this->assertEquals(0, $status->progress);

        // Mark the activity as completed.
        // Set the student as current user so completion_info::internal_set_data()
        // takes the $USER == $userid branch, which refreshes modinfo via
        // get_fast_modinfo($course, 0, true) and updates the MUC cache correctly.
        // This is required on Moodle 5.2+ where the other branch only deletes
        // the cache without rebuilding it, leaving check_status with stale data.
        $this->setUser($user);
        $completion = new \completion_info($course);
        $completion->update_state($cminfo, COMPLETION_COMPLETE, $user->id);
        $this->setAdminUser();

        // Belt-and-suspenders: also purge completion cache and modinfo singleton
        // so check_status always starts with fresh data regardless of version.
        \cache::make('core', 'completion')->purge();
        \course_modinfo::clear_instance_cache();

        // After completion: quest must be complete with 100% progress.
        $status = quest::check_status($quest, $user->id, $course->id, 0, 1);
        $this->assertTrue($status->completed);
        $this->assertEquals(100, $status->progress);
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
        $this->assertSame(100, (int) $DB->get_field('block_playerhud_quest_log', 'xpawarded', [
            'questid' => $quest->id,
            'userid'  => $user->id,
        ]));
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
     * A quest's item reward records xpawarded = 0 even when the item itself has a real XP
     * value configured, since that value is never actually paid through this path (only
     * reward_xp is) — see analytics::game_xp_totals() for the same rule applied to totals.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_item_records_zero_xpawarded_even_with_item_xp(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $reward = $this->create_dummy_item('Golden Crown');
        $DB->set_field('block_playerhud_items', 'xp', 500, ['id' => $reward->id]);
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 0, $reward->id);

        $this->set_player_xp($user->id, 50);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $xpawarded = $DB->get_field('block_playerhud_inventory', 'xpawarded', [
            'userid' => $user->id,
            'itemid' => $reward->id,
        ]);
        $this->assertSame(0, (int) $xpawarded);
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

    /**
     * A claim that crosses a level flashes the level-up celebration.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_flashes_levelup(): void {
        $user = $this->getDataGenerator()->create_user();

        // A second enabled quest keeps the game total XP high so the claim does not also win.
        $this->create_quest(quest::TYPE_LEVEL, '99', 5000);
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '50', 50);
        $this->set_player_xp($user->id, 90); // Level 1; the XP-total requirement (>= 50) is met.

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $flag = get_user_preferences('block_playerhud_celebration', '', $user->id);
        $this->assertEquals('levelup:2', $flag, 'Crossing into level 2 must flash a level-up.');
    }

    /**
     * Reaching 100% on a claim flashes the win, which outranks the level-up.
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_win_outranks_levelup(): void {
        $user = $this->getDataGenerator()->create_user();

        // The reward is the only XP source, so claiming it reaches 100% of the game
        // (and also crosses into level 2). Winning must take priority.
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '0', 100);
        $this->set_player_xp($user->id, 0);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $flag = get_user_preferences('block_playerhud_celebration', '', $user->id);
        $this->assertEquals('win', $flag, 'Beating the game must outrank the level-up.');
    }

    /**
     * A claim that neither levels up nor wins flashes nothing, and never sets the
     * first-quest milestone (that is driven by the sidebar render, not by claiming).
     *
     * @covers ::claim_reward
     */
    public function test_claim_reward_no_celebration(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->create_quest(quest::TYPE_LEVEL, '99', 5000); // Keeps the game total high.
        $quest = $this->create_quest(quest::TYPE_XP_TOTAL, '0', 10);
        $this->set_player_xp($user->id, 20);

        quest::claim_reward($quest->id, $user->id, $this->instanceid, $this->course->id);

        $flag = get_user_preferences('block_playerhud_celebration', '', $user->id);
        $this->assertEquals('', $flag, 'A minor reward must not flash a celebration.');

        $player = \block_playerhud\game::get_player($this->instanceid, $user->id);
        $this->assertEquals(
            0,
            (int) $player->milestones & \block_playerhud\game::MILESTONE_FIRSTQUEST,
            'Claiming must not set the first-quest milestone.'
        );
    }

    /**
     * has_claimable_quests is true once a completed quest remains unclaimed.
     *
     * @covers ::has_claimable_quests
     */
    public function test_has_claimable_quests_detects_completed_unclaimed(): void {
        $user = $this->getDataGenerator()->create_user();
        $quest = $this->create_quest(quest::TYPE_LEVEL, '2', 100);

        // Level 1 (XP below the requirement): nothing claimable yet.
        $this->assertFalse(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 50, 1)
        );

        // Reaching level 2 makes the reward claimable.
        $this->assertTrue(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 250, 2)
        );

        // After claiming it, nothing remains.
        global $DB;
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $quest->id, 'userid' => $user->id, 'timecreated' => time(),
        ]);
        $this->assertFalse(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 250, 2)
        );
    }

    /**
     * has_claimable_quests is false when the instance has no enabled quests.
     *
     * @covers ::has_claimable_quests
     */
    public function test_has_claimable_quests_no_quests(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 9999, 99)
        );
    }

    /**
     * has_claimable_quests resolves item, trade and chapter requirements.
     *
     * @covers ::has_claimable_quests
     */
    public function test_has_claimable_quests_item_trade_chapter(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();

        // Unique-items quest needing two distinct items.
        $this->create_quest(quest::TYPE_UNIQUE_ITEMS, '2', 50);
        $item1 = $this->create_dummy_item('Item 1');
        $item2 = $this->create_dummy_item('Item 2');
        $this->give_item($user->id, $item1->id);

        // Only one unique item so far: not claimable.
        $this->assertFalse(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 0, 1)
        );

        // A second distinct item satisfies the quest.
        $this->give_item($user->id, $item2->id);
        $this->assertTrue(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 0, 1)
        );
    }

    /**
     * build_record_from_suggestion maps a descriptor onto a quest record.
     *
     * @covers ::build_record_from_suggestion
     */
    public function test_build_record_from_suggestion(): void {
        $sug = [
            'type' => quest::TYPE_LEVEL,
            'requirement' => 5,
            'name' => 'Reach level 5',
            'reward_xp' => 120,
            'image_todo' => '📈',
            'image_done' => '👑',
        ];

        $record = quest::build_record_from_suggestion($this->instanceid, $sug);
        $this->assertEquals($this->instanceid, $record->blockinstanceid);
        $this->assertEquals('Reach level 5', $record->name);
        $this->assertEquals(quest::TYPE_LEVEL, $record->type);
        $this->assertSame('5', $record->requirement);
        $this->assertEquals(120, $record->reward_xp);
        $this->assertEquals(1, $record->enabled);

        // An XP override replaces the suggestion's reward and is floored at zero.
        $overridden = quest::build_record_from_suggestion($this->instanceid, $sug, -10);
        $this->assertEquals(0, $overridden->reward_xp);
    }

    /**
     * req_itemid and reward_itemid default to 0 when absent from the descriptor, and are
     * carried through when a bespoke suggestion (built directly by a wizard module rather than
     * by get_heuristic_suggestions()) supplies them.
     *
     * @covers ::build_record_from_suggestion
     */
    public function test_build_record_from_suggestion_carries_item_ids(): void {
        $sug = [
            'type' => quest::TYPE_SPECIFIC_ITEM,
            'requirement' => 11,
            'name' => 'Collect: Pill',
            'reward_xp' => 100,
            'reward_itemid' => 42,
            'req_itemid' => 7,
            'image_todo' => '💊',
            'image_done' => '📚',
        ];
        $record = quest::build_record_from_suggestion($this->instanceid, $sug);
        $this->assertSame(7, $record->req_itemid);
        $this->assertSame(42, $record->reward_itemid);

        $bare = quest::build_record_from_suggestion($this->instanceid, [
            'type' => quest::TYPE_LEVEL,
            'requirement' => 2,
            'name' => 'Reach level 2',
            'reward_xp' => 40,
            'image_todo' => '📈',
            'image_done' => '👑',
        ]);
        $this->assertSame(0, $bare->req_itemid);
        $this->assertSame(0, $bare->reward_itemid);
    }

    /**
     * get_heuristic_suggestions proposes level, collection and economy milestones
     * and skips milestones that already exist as quests.
     *
     * @covers ::get_heuristic_suggestions
     */
    public function test_get_heuristic_suggestions(): void {
        $config = (object) ['max_levels' => 20, 'xp_per_level' => 100];

        // Two enabled items unlock the collection milestones; an unlimited trade unlocks economy.
        $this->create_dummy_item('Alpha');
        $this->create_dummy_item('Beta');
        global $DB;
        $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Open Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 0, 'timecreated' => time(),
        ]);

        $suggestions = quest::get_heuristic_suggestions($this->instanceid, $this->course->id, $config);
        $types = array_column($suggestions, 'type');

        $this->assertContains(quest::TYPE_LEVEL, $types, 'Level milestones must be suggested.');
        $this->assertContains(quest::TYPE_UNIQUE_ITEMS, $types, 'Collection milestones must be suggested.');
        $this->assertContains(quest::TYPE_TRADES, $types, 'Economy milestones must be suggested.');

        // A level milestone that already exists as a quest must not be suggested again.
        $existinglevel = null;
        foreach ($suggestions as $sug) {
            if ($sug['type'] == quest::TYPE_LEVEL) {
                $existinglevel = $sug['requirement'];
                break;
            }
        }
        $this->create_quest(quest::TYPE_LEVEL, (string) $existinglevel, 0);

        $after = quest::get_heuristic_suggestions($this->instanceid, $this->course->id, $config);
        $levelreqs = array_column(array_filter($after, fn($s) => $s['type'] == quest::TYPE_LEVEL), 'requirement');
        $this->assertNotContains($existinglevel, $levelreqs, 'An already-created level milestone must be skipped.');
    }

    /**
     * has_claimable_quests evaluates every count-based requirement type without error.
     *
     * @covers ::has_claimable_quests
     */
    public function test_has_claimable_quests_all_requirement_types(): void {
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Relic');

        // One unmet quest of each count-based type; the switch must visit every branch.
        $this->create_quest(quest::TYPE_XP_TOTAL, '9999');
        $this->create_quest(quest::TYPE_TOTAL_ITEMS, '99');
        $this->create_quest(quest::TYPE_SPECIFIC_ITEM, '5', 0, 0, $item->id);
        $this->create_quest(quest::TYPE_TRADES, '99');
        $this->create_quest(quest::TYPE_SPECIFIC_TRADE, '5', 0, 0, 4242);
        $this->create_quest(quest::TYPE_CHAPTER, '3');

        // None are satisfied, so nothing is claimable, but every branch executed.
        $this->assertFalse(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 0, 1)
        );

        // Satisfy the XP-total quest and confirm it now reports claimable.
        $this->assertTrue(
            quest::has_claimable_quests($this->instanceid, $user->id, $this->course->id, 10000, 1)
        );
    }

    /**
     * A completion-tracked activity is offered as a heuristic quest and drives the
     * activity branch of the claimable check.
     *
     * @covers ::has_claimable_quests
     * @covers ::get_heuristic_suggestions
     */
    public function test_activity_completion_quests(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $coursecontext = \context_course::instance($course->id);
        $instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]
        );
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // The completion-tracked module is offered as an activity quest.
        $suggestions = quest::get_heuristic_suggestions($instanceid, $course->id, (object) ['max_levels' => 10]);
        $activityreqs = array_column(
            array_filter($suggestions, fn($s) => $s['type'] == quest::TYPE_ACTIVITY),
            'requirement'
        );
        $activityreqs = array_map('intval', $activityreqs);
        $this->assertContains((int) $page->cmid, $activityreqs, 'A completion-tracked activity must be suggested.');

        // An activity quest for a module the user has not completed is not claimable.
        // This exercises the full activity branch (modinfo, visibility, completion lookup).
        $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $instanceid, 'name' => 'Finish the page', 'description' => '',
            'type' => quest::TYPE_ACTIVITY, 'requirement' => (string) $page->cmid, 'req_itemid' => 0,
            'reward_xp' => 50, 'reward_itemid' => 0, 'required_class_id' => '0',
            'image_todo' => '📋', 'image_done' => '🏅', 'enabled' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertFalse(
            quest::has_claimable_quests($instanceid, $user->id, $course->id, 0, 1),
            'Incomplete activity must not be claimable.'
        );
    }
}
