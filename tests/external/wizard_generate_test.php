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

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid);

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

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]));
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

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid);

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

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid);

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
