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
     * eligible activity per drop_distribution::compute_pill_quotas() — records everything into
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
        $this->assertSame([], $result['created_quests']);

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

        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));

        $manifesttables = array_column(
            $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]),
            'objecttable'
        );
        $this->assertContains('block_playerhud_items', $manifesttables);
        $this->assertContains('block_playerhud_drops', $manifesttables);
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
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertSame('Original body.', $content);
    }

    /**
     * Trade also wires the Pill<->Book trade once both items exist, costing 10 Pills, and
     * creates the "earned the exclusive trade" quest (TYPE_SPECIFIC_TRADE).
     */
    public function test_trade_wires_pill_book_with_manifest(): void {
        global $DB;

        $pill = $this->create_item($this->instanceid, 'Knowledge Pill', ['action_type' => 'knowledge_pill']);
        $book = $this->create_item($this->instanceid, 'Book of Knowledge', ['action_type' => 'knowledge_book']);

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
        $this->assertSame(['Book of Knowledge'], $result['created_trades']);
        $this->assertSame(['Earn: Book of Knowledge'], $result['created_quests']);

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
     * Running Trade twice must not wire a second Pill<->Book trade.
     */
    public function test_trade_pill_book_is_idempotent_across_runs(): void {
        $this->create_item($this->instanceid, 'Knowledge Pill', ['action_type' => 'knowledge_pill']);
        $this->create_item($this->instanceid, 'Book of Knowledge', ['action_type' => 'knowledge_book']);

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
        $this->assertSame(['Book of Knowledge'], $first['created_trades']);

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
        $this->assertSame([], $second['created_quests']);
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
     * Trade also wires PlayerCoin<->Deadline Extension once both items exist. Requires
     * local_latepenalty (for the item) and is otherwise identical in shape to the Pill<->Book
     * trade wiring.
     */
    public function test_trade_wires_latepenalty_with_manifest(): void {
        global $DB;
        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $coin = $this->create_item($this->instanceid, 'PlayerCoin', ['action_type' => 'playercoin']);
        $item = $this->create_item($this->instanceid, 'Deadline Extension', ['action_type' => 'deadline_extension']);

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
        $this->assertSame(['Deadline Extension'], $result['created_trades']);

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

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame(['progress_item'], json_decode($run->modules, true));

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
}
