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
