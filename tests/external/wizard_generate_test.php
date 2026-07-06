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
}
