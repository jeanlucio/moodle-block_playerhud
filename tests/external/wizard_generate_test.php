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
 * Tests for the wizard_generate web service (Missions/PlayerCoin/Avatars modules;
 * no network involved).
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
 * Tests for the wizard_generate web service.
 *
 * The Items & Trade module calls the AI generator and is exercised manually
 * (see .docs/plano-wizard-octalysis.md); these tests cover the Missions,
 * PlayerCoin and Avatars modules, which are fully deterministic and need no
 * network access.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_generate
 */
final class wizard_generate_test extends external_base_testcase {
    /**
     * Requesting only the Missions module creates quests and a rollback manifest,
     * without touching the item/drop tables.
     */
    public function test_missions_only_creates_quests_and_manifest(): void {
        global $DB;

        $this->create_item($this->instanceid, 'Sword');
        $this->create_item($this->instanceid, 'Shield');

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['created_quests']);
        $this->assertSame([], $result['created_items']);

        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);

        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(count($result['created_quests']), $quests);

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame('done', $run->status);
        $this->assertSame(['missions'], json_decode($run->modules, true));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]);
        $this->assertCount(count($quests), $manifest);
        foreach ($manifest as $entry) {
            $this->assertSame('block_playerhud_quests', $entry->objecttable);
        }
    }

    /**
     * Requesting Missions also ensures the block's own enable_quests setting is on — a teacher
     * who had the Missions tab turned off still sees what the wizard just generated there.
     */
    public function test_missions_ensures_enable_quests_is_on(): void {
        \block_instance_by_id($this->instanceid)->instance_config_save((object) ['enable_quests' => 0]);

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);

        $this->assertTrue($result['success']);
        $blockinstance = \block_instance_by_id($this->instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_quests);
    }

    /**
     * Rolling back a Missions-only run removes the created quests.
     */
    public function test_missions_only_run_can_be_rolled_back(): void {
        global $DB;

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * More candidate missions than the journey's mission count are trimmed down to the
     * limit, drawing from more than one candidate type rather than exhausting the first.
     */
    public function test_missions_are_capped_by_journey_size_and_stay_mixed(): void {
        global $DB;

        for ($i = 1; $i <= 4; $i++) {
            $this->create_item($this->instanceid, "Item $i");
        }

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);

        $this->assertTrue($result['success']);
        // Short size caps at 3 missions, even though level (5/10/15) and unique-item (2/4)
        // milestones together offer 5 candidates.
        $this->assertCount(3, $result['created_quests']);

        $quests = array_values($DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
        $types = array_unique(array_map(static fn($q): int => (int) $q->type, $quests));
        $this->assertGreaterThan(1, count($types), 'Selection must not be entirely one candidate type.');
    }

    /**
     * Every selected mission's reward_xp is overridden to a deterministic share of the XP room
     * (instead of each type's own hardcoded formula: level*20, items*30...), and the shares
     * always sum to exactly the gap — the division's remainder lands as a +1 bonus on the first
     * selected missions rather than being quietly lost.
     */
    public function test_missions_reward_xp_is_an_even_share_of_the_gap(): void {
        global $DB;

        for ($i = 1; $i <= 4; $i++) {
            $this->create_item($this->instanceid, "Item $i");
        }

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);

        $this->assertTrue($result['success']);
        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], 'id ASC');
        $rewards = array_values(array_map(static fn($q): int => (int) $q->reward_xp, $quests));
        // Empty economy: ceiling 100 * 20 = 2000, 3 missions selected -> base 666, remainder 2,
        // so the first 2 missions get 667 and the last gets 666. Sum is exactly 2000.
        $this->assertSame([667, 667, 666], $rewards);
        $this->assertSame(2000, array_sum($rewards));
    }

    /**
     * A level-milestone mission's name is re-flavoured for the chosen tone, replacing the
     * generic "Reach Level N" wording.
     */
    public function test_missions_names_are_tone_flavoured(): void {
        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            true,
            false,
            false,
            false,
            'scifi'
        );

        $this->assertTrue($result['success']);
        $this->assertContains('Reach access level 5', $result['created_quests']);
    }

    /**
     * Activity-completion suggestions, filtered out of the wizard before, are now included:
     * a completion-enabled activity produces a tone-flavoured "complete this activity" mission.
     */
    public function test_missions_include_activity_completion(): void {
        global $CFG, $DB;

        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Intro Reading',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $coursecontext = \context_course::instance($course->id);
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud',
            'parentcontextid' => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize((object) [])),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = wizard_generate::execute($instanceid, $course->id, '', '', 'short', false, true);

        $this->assertTrue($result['success']);
        $this->assertContains('Complete the trial: Intro Reading', $result['created_quests']);
    }

    /**
     * PlayerCoin and Avatars are mechanical (no AI/network) and create items with a manifest.
     */
    public function test_playercoin_and_avatars_create_items_and_manifest(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            true,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertContains('PlayerCoin', $result['created_items']);
        $this->assertGreaterThan(1, count($result['created_items']));

        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(count($result['created_items']), $items);

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame(['playercoin', 'avatars'], json_decode($run->modules, true));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]);
        $this->assertCount(count($items), $manifest);
        foreach ($manifest as $entry) {
            $this->assertSame('block_playerhud_items', $entry->objecttable);
        }
    }

    /**
     * Running PlayerCoin twice must not duplicate the item nor record it into the
     * second run's manifest, since nothing new was actually created.
     */
    public function test_playercoin_is_idempotent_across_runs(): void {
        global $DB;

        $first = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, false, true, false);
        $this->assertSame(['PlayerCoin'], $first['created_items']);

        $second = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, false, true, false);
        $this->assertSame([], $second['created_items']);

        $this->assertEquals(1, $DB->count_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'playercoin',
        ]));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $second['runid']]);
        $this->assertCount(0, $manifest);
    }

    /**
     * Rolling back a PlayerCoin + Avatars run removes the created items.
     */
    public function test_playercoin_and_avatars_run_can_be_rolled_back(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            true,
            true
        );
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * When the course has a news forum, PlayerCoin auto-distributes its drop into it and
     * records both the drop and the shortcode for rollback — undoing the run must remove the
     * item, the drop and strip the shortcode back out of the forum intro.
     */
    public function test_playercoin_auto_distributes_drop_and_rolls_back_cleanly(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => '',
        ]);

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, false, true, false);
        $this->assertSame(['PlayerCoin'], $result['created_items']);

        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'playercoin',
        ], '*', MUST_EXIST);
        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $item->id], '*', MUST_EXIST);

        $introafter = $DB->get_field('forum', 'intro', ['id' => $forum->id]);
        $this->assertStringContainsString('[PLAYERHUD_DROP code=' . $drop->code . ']', $introafter);

        $manifesttables = $DB->get_records_menu(
            'block_playerhud_wizard_objects',
            ['runid' => $result['runid']],
            '',
            'id, objecttable'
        );
        $this->assertContains('block_playerhud_drops', $manifesttables);
        $this->assertCount(1, $DB->get_records('block_playerhud_wizard_shortcodes', ['runid' => $result['runid']]));

        \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $drop->id]));
        $introrolledback = $DB->get_field('forum', 'intro', ['id' => $forum->id]);
        $this->assertStringNotContainsString('PLAYERHUD_DROP', (string) $introrolledback);
    }

    /**
     * Unchecking PlayerCoin's own "insert into the news forum" checkbox still creates the
     * item, but skips setup_playercoin_drop() entirely — even with a news forum available.
     */
    public function test_playercoin_distribute_false_skips_forum_drop(): void {
        global $DB;

        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => '',
        ]);

        $result = wizard_generate::execute(
            instanceid: $this->instanceid,
            courseid: $this->course->id,
            theme: '',
            size: 'short',
            includeitems: false,
            includeplayercoin: true,
            distributeplayercoin: false
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['PlayerCoin'], $result['created_items']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops'));
    }

    /**
     * Trade wires PlayerCoin<->Avatar Pack trades from whatever already exists in the
     * instance (not just this run's own creations), and records the trade plus its
     * requirement and reward rows into the manifest.
     */
    public function test_trade_wires_playercoin_and_avatars_with_manifest(): void {
        global $DB;

        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $avatar = $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        // One avatar yields 2 suggestions: the individual trade for it, plus the "bundle all
        // avatars" trade — see game::build_trade_suggestions().
        $this->assertCount(2, $result['created_trades']);

        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(2, $trades);
        foreach ($trades as $trade) {
            $this->assertSame(1, (int) $trade->onetime);
            $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $trade->id], '*', MUST_EXIST);
            $this->assertGreaterThan(0, (int) $req->qty);
            $reward = $DB->get_record(
                'block_playerhud_trade_rewards',
                ['tradeid' => $trade->id, 'itemid' => $avatar->id],
                '*',
                MUST_EXIST
            );
            $this->assertSame($avatar->id, (int) $reward->itemid);
        }

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_trades', $manifesttables);
        $this->assertContains('block_playerhud_trade_reqs', $manifesttables);
        $this->assertContains('block_playerhud_trade_rewards', $manifesttables);
    }

    /**
     * Running Trade twice must not duplicate a trade that already covers the same avatar.
     */
    public function test_trade_is_idempotent_across_runs(): void {
        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            true
        );
        $this->assertNotEmpty($first['created_trades']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            true
        );
        $this->assertSame([], $second['created_trades']);
    }

    /**
     * Rolling back a Trade run removes the trade and its requirement/reward rows.
     */
    public function test_trade_run_can_be_rolled_back(): void {
        global $DB;

        $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $this->create_item($this->instanceid, 'Fox', ['action_type' => 'avatar_profile']);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            true
        );
        $this->assertTrue($result['success']);

        \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertEquals(0, $DB->count_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_reqs', []));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_rewards', []));
    }

    /**
     * Pill creates the tone-specific Pill and Book items and spreads Pill drops across every
     * eligible activity per drop_distribution::compute_activity_quotas() — records everything into
     * the run's manifest. Deliberately creates no "collect them all" quest (see
     * generate_pill()'s docblock for why that would be a trap once the Pill<->Book trade exists).
     *
     * Also the regression test for the Page-module visibility bug: a Page's intro is never
     * shown unless the teacher explicitly enables "Display page description", so the shortcode
     * must land in content (always shown on the page's own view) instead.
     */
    public function test_pill_creates_items_and_distributes_with_manifest(): void {
        global $DB;

        $pages = [];
        for ($i = 0; $i < 5; $i++) {
            $pages[] = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        }

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'academic',
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Knowledge Pill', 'Book of Knowledge'], $result['created_items']);
        // The Pill module now also wires the intrinsic Pill->Book trade and its quest, so it no
        // longer depends on the separate Comercio module being ticked.
        $this->assertSame(['Book of Knowledge'], $result['created_trades']);
        $this->assertSame(['Earn: Book of Knowledge'], $result['created_quests']);

        $pill = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'knowledge_pill',
        ], '*', MUST_EXIST);
        $this->assertSame('💊', $pill->image);
        $this->assertSame(0, (int) $pill->xp);
        $this->assertSame(0, (int) $pill->tradable);

        $book = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'knowledge_book',
        ], '*', MUST_EXIST);
        $this->assertSame('📚', $book->image);

        $drops = $DB->get_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid, 'itemid' => $pill->id]);
        $this->assertCount(5, $drops);
        $this->assertSame(11, array_sum(array_column($drops, 'maxusage')));
        foreach ($drops as $drop) {
            $this->assertSame(3600, (int) $drop->respawntime);
        }

        $this->assertEquals(1, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_items', $manifesttables);
        $this->assertContains('block_playerhud_drops', $manifesttables);
        $this->assertContains('block_playerhud_trades', $manifesttables);
        $this->assertContains('block_playerhud_quests', $manifesttables);
        $shortcoderows = $DB->get_records('block_playerhud_wizard_shortcodes', ['runid' => $result['runid']]);
        $this->assertCount(5, $shortcoderows);

        // Every eligible activity here is a Page, so every shortcode must have landed in
        // content (always visible) rather than intro (hidden unless the teacher opts in).
        foreach ($shortcoderows as $row) {
            $this->assertSame('content', $row->field);
        }
        foreach ($pages as $page) {
            $content = $DB->get_field('page', 'content', ['id' => $page->id]);
            $this->assertStringContainsString('[PLAYERHUD_DROP', (string) $content);
        }
    }

    /**
     * Unchecking Pill's own "distribute into activities" checkbox still creates both items, but
     * skips the quota-scatter across course activities entirely — proving distribute_pill
     * actually gates generate_pill()'s own placement, independent of every other module.
     */
    public function test_pill_distribute_false_skips_activity_scatter(): void {
        global $DB;

        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);

        $result = wizard_generate::execute(
            instanceid: $this->instanceid,
            courseid: $this->course->id,
            theme: '',
            size: 'short',
            includeitems: false,
            tonekey: 'academic',
            includepill: true,
            distributepill: false
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Knowledge Pill', 'Book of Knowledge'], $result['created_items']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops'));
    }

    /**
     * Running Pill twice must not duplicate the item pair or its drops.
     */
    public function test_pill_is_idempotent_across_runs(): void {
        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'academic',
            false,
            false,
            false,
            false,
            true
        );
        $this->assertNotEmpty($first['created_items']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'academic',
            false,
            false,
            false,
            false,
            true
        );
        $this->assertSame([], $second['created_items']);
        $this->assertSame([], $second['created_quests']);
        $this->assertSame([], $second['created_trades']);
    }

    /**
     * Rolling back a Pill run removes the items and drops, and strips every shortcode back out
     * of the activities it was inserted into — here specifically content, since the only
     * eligible activity is a Page.
     */
    public function test_pill_run_can_be_rolled_back(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'content' => 'Original body.',
        ]);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'academic',
            false,
            false,
            false,
            false,
            true
        );
        $this->assertTrue($result['success']);

        \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]));
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertSame('Original body.', $content);
    }

    /**
     * Secret Drops creates a tone-specific item flagged secret=1, worth 0 XP and non-tradable,
     * and spreads exactly SECRET_DROP_COUNT (3) one-time drops across eligible activities —
     * records everything into the run's manifest.
     */
    public function test_secret_drops_creates_item_and_distributes_with_manifest(): void {
        global $DB;

        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        }

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Lost Relic'], $result['created_items']);

        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Lost Relic',
        ], '*', MUST_EXIST);
        $this->assertSame(1, (int) $item->secret);
        $this->assertSame(0, (int) $item->xp);
        $this->assertSame(0, (int) $item->tradable);

        $drops = $DB->get_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid, 'itemid' => $item->id]);
        $this->assertCount(3, $drops);
        $this->assertSame(3, array_sum(array_column($drops, 'maxusage')));
        foreach ($drops as $drop) {
            $this->assertSame(1, (int) $drop->maxusage);
        }

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_items', $manifesttables);
        $this->assertContains('block_playerhud_drops', $manifesttables);
        $shortcoderows = $DB->get_records('block_playerhud_wizard_shortcodes', ['runid' => $result['runid']]);
        $this->assertCount(3, $shortcoderows);

        // Every eligible activity here is a Page, so every shortcode must have landed in
        // content (always visible) rather than intro (hidden unless the teacher opts in).
        foreach ($shortcoderows as $row) {
            $this->assertSame('content', $row->field);
        }
    }

    /**
     * Unchecking Secret Drops' own "distribute into activities" checkbox still creates the
     * item, but skips scattering its drops entirely — same gating proof as Pill's, for the
     * quota-scatter mechanic distribute_secret controls.
     */
    public function test_secret_drops_distribute_false_skips_activity_scatter(): void {
        global $DB;

        $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);

        $result = wizard_generate::execute(
            instanceid: $this->instanceid,
            courseid: $this->course->id,
            theme: '',
            size: 'short',
            includeitems: false,
            tonekey: 'fantasy',
            includesecretdrops: true,
            distributesecret: false
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Lost Relic'], $result['created_items']);
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops'));
    }

    /**
     * Running Secret Drops twice must not duplicate the item or its drops.
     */
    public function test_secret_drops_is_idempotent_across_runs(): void {
        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertSame(['Lost Relic'], $first['created_items']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertSame([], $second['created_items']);
    }

    /**
     * Rolling back a Secret Drops run removes the item and drops, and strips every shortcode
     * back out of the activities it was inserted into.
     */
    public function test_secret_drops_run_can_be_rolled_back(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'content' => 'Original body.',
        ]);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertTrue($result['success']);

        \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]));
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertSame('Original body.', $content);
    }

    /**
     * Ranking turns the block's ranking setting on when it is off, merging into the existing
     * config object rather than replacing it (proven here with a pre-existing, unrelated
     * config value that must survive untouched).
     */
    public function test_ranking_turns_on_when_off(): void {
        $instanceid = $this->create_block_instance(['enable_ranking' => 0, 'enable_items' => 0]);

        $result = wizard_generate::execute(
            $instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $blockinstance = \block_instance_by_id($instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_ranking);
        $this->assertSame(0, (int) $blockinstance->config->enable_items);
    }

    /**
     * Ranking already on is left untouched (no unnecessary config write).
     */
    public function test_ranking_noop_when_already_on(): void {
        $instanceid = $this->create_block_instance(['enable_ranking' => 1]);

        $result = wizard_generate::execute(
            $instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $blockinstance = \block_instance_by_id($instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_ranking);
    }

    /**
     * The Pill module wires the Pill<->Book trade (10 Pills -> Book) and its TYPE_SPECIFIC_TRADE
     * quest as part of generating the Pill — no separate Comercio tick required, so the Book can
     * never be left unobtainable.
     */
    public function test_pill_wires_book_trade_and_quest(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'academic',
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Book of Knowledge'], $result['created_trades']);
        $this->assertSame(['Earn: Book of Knowledge'], $result['created_quests']);

        $pill = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'knowledge_pill',
        ], '*', MUST_EXIST);
        $book = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'knowledge_book',
        ], '*', MUST_EXIST);

        $trade = $DB->get_record('block_playerhud_trades', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame((int) $pill->id, (int) $req->itemid);
        $this->assertSame(10, (int) $req->qty);
        $reward = $DB->get_record('block_playerhud_trade_rewards', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame((int) $book->id, (int) $reward->itemid);

        $quest = $DB->get_record('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertEquals(\block_playerhud\quest::TYPE_SPECIFIC_TRADE, (int) $quest->type);
        $this->assertSame('1', $quest->requirement);
        $this->assertSame((int) $trade->id, (int) $quest->req_itemid);
        $this->assertSame(150, (int) $quest->reward_xp);
    }

    /**
     * Latepenalty creates the Deadline Extension item and an early "reach level 2" quest that
     * rewards it, recording both into the run's manifest. Requires local_latepenalty.
     */
    public function test_latepenalty_creates_item_and_quest_with_manifest(): void {
        global $DB;
        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Deadline Extension'], $result['created_items']);
        $this->assertSame(['Reach level 2'], $result['created_quests']);

        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'deadline_extension',
        ], '*', MUST_EXIST);
        $this->assertSame(0, (int) $item->xp);
        $this->assertSame(0, (int) $item->tradable);
        $this->assertSame(['days' => 2, 'cmid' => 0], json_decode($item->action_value, true));

        $quest = $DB->get_record('block_playerhud_quests', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertEquals(\block_playerhud\quest::TYPE_LEVEL, (int) $quest->type);
        $this->assertSame('2', $quest->requirement);
        $this->assertSame((int) $item->id, (int) $quest->reward_itemid);
        $this->assertSame(40, (int) $quest->reward_xp);

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_items', $manifesttables);
        $this->assertContains('block_playerhud_quests', $manifesttables);
    }

    /**
     * Running Latepenalty twice must not duplicate the item or quest.
     */
    public function test_latepenalty_is_idempotent_across_runs(): void {
        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertSame(['Deadline Extension'], $first['created_items']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertSame([], $second['created_items']);
        $this->assertSame([], $second['created_quests']);
    }

    /**
     * Rolling back a Latepenalty run removes the item and quest.
     */
    public function test_latepenalty_run_can_be_rolled_back(): void {
        global $DB;
        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );
        $this->assertTrue($result['success']);

        \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * Without local_latepenalty installed, the module is a clean no-op — no item, no quest.
     * Only meaningful (and only runs) in an environment where the plugin is absent, the
     * inverse of every other Latepenalty test in this file.
     */
    public function test_latepenalty_noop_when_not_installed(): void {
        if (class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Only relevant when local_latepenalty is absent.');
        }

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['created_items']);
        $this->assertSame([], $result['created_quests']);
    }

    /**
     * The Latepenalty module wires the PlayerCoin<->Deadline Extension trade as part of
     * generating the item, when PlayerCoin already exists — no separate Comercio tick required.
     * Requires local_latepenalty (for the item).
     */
    public function test_latepenalty_wires_playercoin_trade(): void {
        global $DB;
        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $coin = $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            false,
            false,
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame(['Deadline Extension'], $result['created_trades']);

        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type' => 'deadline_extension',
        ], '*', MUST_EXIST);
        $trade = $DB->get_record('block_playerhud_trades', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame((int) $coin->id, (int) $req->itemid);
        $this->assertSame(20, (int) $req->qty);
        $reward = $DB->get_record('block_playerhud_trade_rewards', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame((int) $item->id, (int) $reward->itemid);
    }

    /**
     * RPG Classes is mechanical (no AI/network): creates 3 classes, a fixed Chapter 1 with
     * 6 nodes and choices, and records everything into the run's manifest.
     */
    public function test_rpg_creates_classes_and_chapter_with_manifest(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );

        $this->assertTrue($result['success']);
        $this->assertCount(4, $result['created_items'], '3 classes + 1 chapter title.');

        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);

        $classes = $DB->get_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(3, $classes);

        $chapters = $DB->get_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(1, $chapters);
        $chapter = reset($chapters);

        $nodes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapter->id]);
        $this->assertCount(6, $nodes);

        $nodeids = array_keys($nodes);
        [$insql, $inparams] = $DB->get_in_or_equal($nodeids, SQL_PARAMS_NAMED);
        $choices = $DB->get_records_select('block_playerhud_choices', "nodeid $insql", $inparams);
        $this->assertCount(6, $choices, '1 (continue) + 2 (help/direct) + 3 (class picks) = 6.');

        $classchoices = array_filter($choices, static fn($choice): bool => (int) $choice->set_class_id > 0);
        $this->assertCount(3, $classchoices, 'Exactly the 3 archetype-selection choices set a class.');
        $assignedclassids = array_map(static fn($choice): int => (int) $choice->set_class_id, $classchoices);
        sort($assignedclassids);
        $expectedclassids = array_map('intval', array_keys($classes));
        sort($expectedclassids);
        $this->assertSame($expectedclassids, $assignedclassids, 'Each of the 3 classes is assignable.');

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame(['rpg'], json_decode($run->modules, true));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]);
        $this->assertCount(3 + 1 + 6 + 6, $manifest, 'classes + chapter + nodes + choices.');
    }

    /**
     * Requesting RPG also ensures the block's own enable_rpg setting is on — a teacher who had
     * the Classes/Chapters tabs turned off still sees what the wizard just generated there.
     */
    public function test_rpg_ensures_enable_rpg_is_on(): void {
        \block_instance_by_id($this->instanceid)->instance_config_save((object) ['enable_rpg' => 0]);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );

        $this->assertTrue($result['success']);
        $blockinstance = \block_instance_by_id($this->instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_rpg);
    }

    /**
     * Even on the idempotent skip path (Chapter 1 already exists for this tone, so nothing new
     * is created), checking the RPG box is still the teacher's intent to see those tabs.
     */
    public function test_rpg_ensures_enable_rpg_is_on_even_when_already_generated(): void {
        wizard_generate::generate_rpg_classes($this->instanceid, 'fantasy', 0);
        \block_instance_by_id($this->instanceid)->instance_config_save((object) ['enable_rpg' => 0]);

        $result = wizard_generate::generate_rpg_classes($this->instanceid, 'fantasy', 0);

        $this->assertSame('', $result['chapter_title'], 'Idempotent skip: nothing new created.');
        $blockinstance = \block_instance_by_id($this->instanceid);
        $this->assertSame(1, (int) $blockinstance->config->enable_rpg);
    }

    /**
     * Running RPG Classes twice with the same tone must not duplicate anything.
     */
    public function test_rpg_is_idempotent_across_runs(): void {
        global $DB;

        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );
        $this->assertCount(4, $first['created_items']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );
        $this->assertSame([], $second['created_items']);

        $this->assertEquals(3, $DB->count_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(1, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $second['runid']]);
        $this->assertCount(0, $manifest);
    }

    /**
     * Rolling back an RPG Classes run removes the classes, chapter, nodes and choices.
     */
    public function test_rpg_run_can_be_rolled_back(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_story_nodes', []));
        $this->assertEquals(0, $DB->count_records('block_playerhud_choices', []));
    }

    /**
     * A different tone key produces a different chapter, coexisting with the first.
     */
    public function test_rpg_different_tone_produces_different_chapter(): void {
        global $DB;

        wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'fantasy'
        );
        $scifi = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            true,
            'scifi'
        );

        $this->assertCount(4, $scifi['created_items'], 'Sci-Fi has different names, so nothing is skipped.');
        $this->assertEquals(2, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(6, $DB->count_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * The progress item is mechanical (no AI/network): creates a themed item with an
     * infinite, cooldown-based drop, independent of RPG Classes, with a manifest.
     */
    public function test_progress_item_creates_item_and_drop_with_manifest(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            true
        );

        $this->assertTrue($result['success']);
        $expectedname = get_string('wizard_progress_item_name_fantasy', 'block_playerhud');
        $this->assertSame([$expectedname], $result['created_items']);

        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);

        $item = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertSame($expectedname, $item->name);
        $this->assertEquals(0, $item->xp);
        $this->assertEquals(0, $item->tradable);

        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $item->id], '*', MUST_EXIST);
        $this->assertEquals(0, $drop->maxusage);
        $this->assertEquals(3600, $drop->respawntime);

        // Distribute_progress_item defaults to true, so a plain progress-item run also earns an
        // auto_distribute module in its own manifest — it is the one feeding that step a drop.
        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame(['progress_item', 'auto_distribute'], json_decode($run->modules, true));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]);
        $this->assertCount(2, $manifest, 'item + drop.');
    }

    /**
     * Running the progress item module twice must not duplicate anything.
     */
    public function test_progress_item_is_idempotent_across_runs(): void {
        global $DB;

        $first = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            true
        );
        $this->assertCount(1, $first['created_items']);

        $second = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            true
        );
        $this->assertSame([], $second['created_items']);

        $this->assertEquals(1, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * Rolling back a progress item run removes the item and its drop.
     */
    public function test_progress_item_run_can_be_rolled_back(): void {
        global $DB;

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            true
        );
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid, $this->course->id);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * Checking only the progress item (independent of RPG or Items & Trade) feeds its drop
     * into the same run's auto-distribute step.
     */
    public function test_progress_item_feeds_into_auto_distribute(): void {
        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            true,
            true
        );

        $this->assertTrue($result['success']);
        // The PHPUnit test course has no activities, so distribution is skipped with a message —
        // proving the progress item's drop actually reached the auto-distribute step.
        $this->assertSame(
            get_string('wizard_distribute_no_activities', 'block_playerhud'),
            $result['distribute_message']
        );
    }

    /**
     * Regression test for the Page-module visibility bug: a Page's intro is never shown unless
     * the teacher explicitly enables "Display page description", so distribute_drops() must
     * target content (always shown on the page's own view) instead when the best-matching
     * activity is a Page — same fix as generate_pill(), exercised here via the progress item
     * since it needs no AI key.
     */
    public function test_auto_distribute_targets_page_content_not_intro(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Crystals',
            'content' => 'Original body.',
        ]);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            true,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['distribute_message']);

        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringContainsString('[PLAYERHUD_DROP', (string) $content);
        $this->assertStringContainsString('Original body.', (string) $content);

        $intro = $DB->get_field('page', 'intro', ['id' => $page->id]);
        $this->assertStringNotContainsString('PLAYERHUD_DROP', (string) $intro);

        $shortcoderow = $DB->get_record('block_playerhud_wizard_shortcodes', ['runid' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame('content', $shortcoderow->field);
    }

    /**
     * Auto-distributing a drop must rename it to the activity it actually landed in — otherwise
     * the drops management table's "Localização/Nome" column keeps showing the item's own name,
     * useless for finding where a drop physically is. Uses a page name deliberately different
     * from the item's own name so a pass can only mean a real rename, not a coincidence.
     */
    public function test_auto_distribute_renames_drop_to_its_activity(): void {
        global $DB;

        $this->getDataGenerator()->create_module('page', [
            'course' => $this->course->id,
            'name' => 'Reactor Room',
            'content' => 'Original body.',
        ]);

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'scifi',
            true,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertSame('', $result['distribute_message']);

        $itemname = get_string('wizard_progress_item_name_scifi', 'block_playerhud');
        $item = $DB->get_record(
            'block_playerhud_items',
            ['blockinstanceid' => $this->instanceid, 'name' => $itemname],
            '*',
            MUST_EXIST
        );
        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $item->id], '*', MUST_EXIST);

        $this->assertSame('Reactor Room', $drop->name);
        $this->assertNotSame($itemname, $drop->name);
    }

    /**
     * Without an AI key, generate_story() throws before creating any chapter — but the progress
     * item this same call auto-creates first still needs to be rolled back, since it's an
     * earlier real write in the same run. Exercises the wizard::rollback() fix (see
     * "reload the page..." commit history) with a fully deterministic failure, no AI needed.
     */
    public function test_next_chapter_without_key_rolls_back_progress_item(): void {
        global $DB;

        set_config('apikey_gemini', '', 'block_playerhud');
        set_config('apikey_groq', '', 'block_playerhud');
        set_config('apikey_openai', '', 'block_playerhud');

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            'A haunted forest',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            true
        );

        $this->assertFalse($result['success']);
        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertFalse($cleaned['success']);

        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
        $this->assertEquals(0, $DB->count_records('block_playerhud_drops'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $this->instanceid]));

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame('rolledback', $run->status);
        $this->assertEquals(0, $DB->count_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]));
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_wizard_generate_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
    }

    /**
     * build_step_types() must return exactly one step type per checked module, in the fixed
     * order execute() itself runs them, ending with auto_distribute — the § 5.9 step plan
     * (wizard_start) and the single-call execute() must always agree on this order.
     */
    public function test_build_step_types_matches_selected_modules_in_order(): void {
        $params = self::wizard_generate_params([
            'include_items' => true,
            'include_ranking' => true,
            'include_avatars' => true,
            'include_missions' => true,
            'distribute_items' => true,
        ]);

        $this->assertSame(
            ['items', 'missions', 'avatars', 'ranking', 'auto_distribute'],
            wizard_generate::build_step_types($params)
        );
    }

    /**
     * The auto_distribute step is skipped when Items is selected but its own distribute
     * checkbox was left off — nothing would be forwarded to it, so it earns no step of its own.
     */
    public function test_build_step_types_skips_auto_distribute_when_items_distribute_is_off(): void {
        $params = self::wizard_generate_params([
            'include_items' => true,
            'distribute_items' => false,
        ]);

        $this->assertSame(['items'], wizard_generate::build_step_types($params));
    }

    /**
     * With every module flag left off, the plan is empty.
     */
    public function test_build_step_types_empty_when_nothing_selected(): void {
        $this->assertSame([], wizard_generate::build_step_types(self::wizard_generate_params([])));
    }

    /**
     * compute_shared_xp_shares() is a no-op — no economy_health() query, empty shares — when
     * neither Items nor Missions is selected, since nothing would consume the shared XP room.
     * Pill/Latepenalty are also excluded here, so their bonus XP is 0 too (unused).
     */
    public function test_compute_shared_xp_shares_empty_when_items_and_missions_excluded(): void {
        [$itemshares, $missionshares, $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            new \stdClass(),
            self::wizard_generate_params(['include_playercoin' => true])
        );

        $this->assertSame([], $itemshares);
        $this->assertSame([], $missionshares);
        $this->assertSame(0, $pillbonus);
        $this->assertSame(0, $latepenaltybonus);
    }

    /**
     * Without Items/Missions, Pill and Latepenalty keep their own fixed default reward instead
     * of competing for a share of the ceiling — there is no active budget context to reconcile
     * against, and handing either of them the *entire* remaining gap (as a 1-element shared
     * distribution would) would be a wildly disproportionate single-quest reward.
     */
    public function test_compute_shared_xp_shares_pill_and_latepenalty_use_defaults_when_alone(): void {
        [, , $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            new \stdClass(),
            self::wizard_generate_params(['include_pill' => true, 'include_latepenalty' => true])
        );

        $this->assertSame(150, $pillbonus);
        $this->assertSame(40, $latepenaltybonus);
    }

    /**
     * With Items/Missions in the same run, Pill and Latepenalty each claim one more slice of the
     * same shared distribution — so a full run (Items + Missions + Pill + Latepenalty) lands its
     * combined total on exactly the level ceiling, instead of overshooting it by their old fixed
     * defaults (150 + 40 = 190 XP that the budget never accounted for).
     */
    public function test_compute_shared_xp_shares_pill_and_latepenalty_share_the_budget_with_items(): void {
        $config = (object) ['xp_per_level' => 100, 'max_levels' => 20];
        $params = self::wizard_generate_params([
            'include_items' => true,
            'include_missions' => true,
            'include_pill' => true,
            'include_latepenalty' => true,
            'size' => 'short',
        ]);

        [$itemshares, $missionshares, $pillbonus, $latepenaltybonus] = wizard_generate::compute_shared_xp_shares(
            $this->instanceid,
            $config,
            $params
        );

        $total = array_sum($itemshares) + array_sum($missionshares) + $pillbonus + $latepenaltybonus;
        $this->assertSame(2000, $total);
    }

    /**
     * resolve_or_create_progress_item() creates the item on first call and reuses the same ID
     * on a second call — the § 5.9 story-arc chapter step relies on this to never duplicate it
     * across the arc's several chapter steps.
     */
    public function test_resolve_or_create_progress_item_is_idempotent(): void {
        global $DB;

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);

        $first = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'fantasy', $runid);
        $second = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'fantasy', $runid);

        $this->assertSame($first, $second);
        $this->assertEquals(1, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * This is what lets the merged RPG checkbox (classes, Chapter 1 and the rest of the AI story
     * arc) run without the teacher ever ticking "Item de progresso" on its own: on an instance
     * with none yet, the item created on demand must be indistinguishable from one
     * generate_progress_item() created directly — correct tone-based name and emoji, an infinite
     * drop (maxusage 0, so future chapters can spend it any number of times), and both rows
     * recorded in the run's manifest so an interrupted run can still be rolled back.
     */
    public function test_resolve_or_create_progress_item_creates_a_complete_item_when_missing(): void {
        global $DB;

        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, []);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));

        $itemid = wizard_generate::resolve_or_create_progress_item($this->instanceid, 'scifi', $runid);

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $this->assertSame(get_string('wizard_progress_item_name_scifi', 'block_playerhud'), $item->name);
        $this->assertSame("\u{1F50B}", $item->image);
        $this->assertEquals(0, (int) $item->tradable);
        $this->assertSame('', $item->action_type);

        $drop = $DB->get_record('block_playerhud_drops', ['itemid' => $itemid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $drop->maxusage, 'Infinite drop: future chapters can spend it any number of times.');

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $this->assertCount(2, $manifest, 'item + drop, so an interrupted run can still roll it back.');
    }

    /**
     * resolve_previous_chapter_context() is empty for an instance with no chapters yet, and
     * combines the latest chapter's title/intro with its starting node's real text once one
     * exists — the § 5.9 story-arc chapter step uses this, read from the database rather than
     * trusting the browser, to keep each new AI chapter consistent with the one before it.
     */
    public function test_resolve_previous_chapter_context_reads_the_latest_chapter(): void {
        $this->assertSame('', wizard_generate::resolve_previous_chapter_context($this->instanceid));

        global $DB;
        $chapter = $this->create_chapter('The Sunken Library');
        $DB->set_field('block_playerhud_chapters', 'intro_text', 'A flooded archive of secrets.', ['id' => $chapter->id]);
        $this->create_node($chapter->id, 'You wade into the flooded archive, torch held high.', true);

        $context = wizard_generate::resolve_previous_chapter_context($this->instanceid);
        $this->assertStringContainsString('The Sunken Library', $context);
        $this->assertStringContainsString('A flooded archive of secrets.', $context);
        $this->assertStringContainsString('You wade into the flooded archive, torch held high.', $context);
    }

    /**
     * Builds a validated params array matching wizard_generate::execute_parameters()'s shape,
     * with every include_* flag defaulting to false and every distribute_* flag defaulting to
     * true (its real default) — mirrors what self::validate_parameters() produces inside
     * execute(), since build_step_types()/compute_shared_xp_shares() are called with that same
     * validated array, not raw booleans.
     *
     * @param array $overrides Flags to override, e.g. ['include_missions' => true].
     * @return array The params array.
     */
    private static function wizard_generate_params(array $overrides): array {
        return array_merge([
            'instanceid' => 0,
            'courseid' => 0,
            'theme' => '',
            'tone' => '',
            'size' => 'short',
            'include_items' => false,
            'include_missions' => false,
            'include_playercoin' => false,
            'include_avatars' => false,
            'include_rpg' => false,
            'tone_key' => 'fantasy',
            'distribute_items' => true,
            'include_progress_item' => false,
            'include_next_chapter' => false,
            'include_comercio' => false,
            'include_pill' => false,
            'include_latepenalty' => false,
            'include_secret_drops' => false,
            'include_ranking' => false,
            'distribute_progress_item' => true,
            'distribute_playercoin' => true,
            'distribute_pill' => true,
            'distribute_secret' => true,
        ], $overrides);
    }
}
