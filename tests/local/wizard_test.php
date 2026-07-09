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

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the gamification wizard run manifest and rollback.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\local\wizard
 */
final class wizard_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /**
     * Set up a block instance for the wizard tests.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;
        $coursecontext = \context_course::instance($course->id);
        $this->instanceid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Inserts a minimal item and returns its ID.
     *
     * @return int The new item ID.
     */
    protected function create_item(): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Item', 'xp' => 10, 'enabled' => 1,
            'maxusage' => 1, 'respawntime' => 0, 'tradable' => 1, 'secret' => 0, 'required_class_id' => '0',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A new run starts with a running status and stores the selected modules as JSON.
     *
     * @covers ::start_run
     */
    public function test_start_run_creates_row_with_running_status(): void {
        global $DB, $USER;

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items', 'missions']);

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame($this->instanceid, (int) $run->blockinstanceid);
        $this->assertSame('running', $run->status);
        $this->assertSame(['items', 'missions'], json_decode($run->modules, true));
    }

    /**
     * finish_run updates the status and timemodified.
     *
     * @covers ::finish_run
     */
    public function test_finish_run_updates_status(): void {
        global $DB, $USER;

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::finish_run($runid, 'done');

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('done', $run->status);
    }

    /**
     * Rollback deletes every recorded object across its own table, regardless of
     * how many different tables the run touched, and marks the run rolledback.
     *
     * @covers ::record_objects
     * @covers ::rollback
     */
    public function test_rollback_deletes_objects_across_tables(): void {
        global $DB, $USER;

        $itemid = $this->create_item();
        $dropid = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid, 'itemid' => $itemid, 'name' => 'Spot',
            'maxusage' => 0, 'respawntime' => 0, 'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
        wizard::record_objects($runid, 'block_playerhud_drops', [$dropid]);
        wizard::finish_run($runid, 'done');

        $deleted = wizard::rollback($runid, $this->instanceid, $this->courseid);

        $this->assertSame(2, $deleted);
        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $itemid]));
        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
        $this->assertFalse($DB->record_exists('block_playerhud_wizard_objects', ['runid' => $runid]));
        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $runid], '*', MUST_EXIST);
        $this->assertSame('rolledback', $run->status);
    }

    /**
     * Rollback strips a recorded shortcode back out of the course content it was
     * inserted into, in addition to deleting the drop row.
     *
     * @covers ::record_shortcode
     * @covers ::rollback
     */
    public function test_rollback_strips_recorded_shortcode(): void {
        global $DB, $USER;

        $itemid = $this->create_item();
        $code = \block_playerhud\utils::generate_drop_code($this->instanceid);
        $dropid = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid, 'itemid' => $itemid, 'name' => 'Spot',
            'maxusage' => 0, 'respawntime' => 0, 'code' => $code,
            'timecreated' => time(), 'timemodified' => time(),
        ]);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $this->courseid,
            'content' => '[PLAYERHUD_DROP code=' . $code . ']' . "\n" . 'Keep this body',
        ]);

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
        wizard::record_objects($runid, 'block_playerhud_drops', [$dropid]);
        wizard::record_shortcode($runid, $dropid, (int) $page->cmid, 'content');
        wizard::finish_run($runid, 'done');

        wizard::rollback($runid, $this->instanceid, $this->courseid);

        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringNotContainsString('[PLAYERHUD_DROP code=' . $code . ']', $content);
        $this->assertStringContainsString('Keep this body', $content);
        $this->assertFalse($DB->record_exists('block_playerhud_wizard_shortcodes', ['runid' => $runid]));
    }

    /**
     * Rollback reverts the XP students earned from the objects it removes and clears
     * their play history, matching a manual delete rather than a raw wipe.
     *
     * @covers ::rollback
     */
    public function test_rollback_reverts_xp_and_clears_play_history(): void {
        global $DB, $USER;

        $student = $this->getDataGenerator()->create_user();
        $player = \block_playerhud\game::get_player($this->instanceid, (int) $student->id);
        $DB->set_field('block_playerhud_user', 'currentxp', 100, ['id' => $player->id]);

        // An item worth 10 XP the student is holding once.
        $itemid = $this->create_item();
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $student->id, 'itemid' => $itemid, 'dropid' => 0,
            'source' => 'map', 'timecreated' => time(), 'xpawarded' => 10,
        ]);

        // A quest worth 15 XP the student has already claimed.
        $questid = (int) $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Quest', 'description' => '',
            'type' => 1, 'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 15,
            'reward_itemid' => 0, 'required_class_id' => '0', 'image_todo' => '📋',
            'image_done' => '🏅', 'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $student->id, 'timecreated' => time(),
        ]);

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items', 'missions']);
        wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
        wizard::record_objects($runid, 'block_playerhud_quests', [$questid]);
        wizard::finish_run($runid, 'done');

        wizard::rollback($runid, $this->instanceid, $this->courseid);

        // The generated objects and their play-history rows are gone...
        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $itemid]));
        $this->assertFalse($DB->record_exists('block_playerhud_quests', ['id' => $questid]));
        $this->assertFalse($DB->record_exists('block_playerhud_inventory', ['itemid' => $itemid]));
        $this->assertFalse($DB->record_exists('block_playerhud_quest_log', ['questid' => $questid]));

        // ...and the 10 + 15 XP they granted has been taken back.
        $this->assertSame(75, (int) $DB->get_field('block_playerhud_user', 'currentxp', ['id' => $player->id]));
    }

    /**
     * Rollback is scoped to the caller's own instance: a run ID that belongs to
     * a different block instance must never be rolled back.
     *
     * @covers ::rollback
     */
    public function test_rollback_rejects_mismatched_instance(): void {
        global $USER;

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);

        $this->expectException(\dml_missing_record_exception::class);
        wizard::rollback($runid, $this->instanceid + 999, $this->courseid);
    }

    /**
     * Only 'done' runs are returned, newest first, with per-table object counts.
     *
     * @covers ::get_active_runs
     */
    public function test_get_active_runs_returns_only_done_runs_with_counts(): void {
        global $USER;

        $itemid1 = $this->create_item();
        $itemid2 = $this->create_item();

        $donerunid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::record_objects($donerunid, 'block_playerhud_items', [$itemid1, $itemid2]);
        wizard::finish_run($donerunid, 'done');

        $rolledbackrunid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::finish_run($rolledbackrunid, 'rolledback');

        $runs = wizard::get_active_runs($this->instanceid);

        $this->assertCount(1, $runs);
        $this->assertSame($donerunid, $runs[0]->id);
        $this->assertSame(['block_playerhud_items' => 2], $runs[0]->counts);
    }

    /**
     * The limit parameter caps the number of runs returned, newest first.
     *
     * @covers ::get_active_runs
     */
    public function test_get_active_runs_respects_limit(): void {
        global $USER;

        for ($i = 0; $i < 3; $i++) {
            $itemid = $this->create_item();
            $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
            wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
            wizard::finish_run($runid, 'done');
        }

        $runs = wizard::get_active_runs($this->instanceid, 2);

        $this->assertCount(2, $runs);
    }

    /**
     * A fresh instance has nothing generated: every mechanic reports false, so every wizard
     * card starts enabled.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_all_false_on_fresh_instance(): void {
        $generated = wizard::get_generated_modules($this->instanceid, new \stdClass());

        $this->assertSame([], array_keys(array_filter($generated)));
    }

    /**
     * Inserts a minimal quest and returns its ID.
     *
     * @return int The new quest ID.
     */
    protected function create_quest(): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Quest', 'description' => '',
            'type' => 1, 'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 15,
            'reward_itemid' => 0, 'required_class_id' => '0', 'image_todo' => '📋',
            'image_done' => '🏅', 'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Mechanics included in a completed run report generated only when that run's own manifest
     * still points at real content; a rolled-back run does not count (undoing a run re-enables
     * its cards).
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_counts_done_runs_only(): void {
        $donerun = wizard::start_run($this->instanceid, 2, ['items', 'missions']);
        wizard::record_object($donerun, 'block_playerhud_items', $this->create_item());
        wizard::record_object($donerun, 'block_playerhud_quests', $this->create_quest());
        wizard::finish_run($donerun, 'done');
        $undonerun = wizard::start_run($this->instanceid, 2, ['comercio']);
        wizard::finish_run($undonerun, 'rolledback');

        $generated = wizard::get_generated_modules($this->instanceid, new \stdClass());

        $this->assertTrue($generated['items']);
        $this->assertTrue($generated['missions']);
        $this->assertFalse($generated['comercio']);
    }

    /**
     * A 'done' run alone must not keep a mechanic disabled once its content is gone — e.g.
     * deleted through the Items management screen instead of the wizard's own undo, which
     * always flips the run's status away from 'done'. Regression test for a real course where
     * PlayerCoin/RPG item stayed permanently disabled after their items were deleted outside
     * the wizard, despite two 'done' runs long done rolling back — and a second real course
     * where Items itself stayed disabled after its run's manifest was recorded but the AI-created
     * items vanished before the run even finished.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_ignores_stale_done_run_without_content(): void {
        $donerun = wizard::start_run(
            $this->instanceid,
            2,
            ['items', 'missions', 'comercio', 'playercoin', 'avatars', 'pill', 'secret_drops',
                'latepenalty', 'progress_item']
        );
        // Deliberately no record_object() calls: this run's manifest stays empty, simulating a
        // run that claims to have generated content no wizard_objects row ever backed.
        wizard::finish_run($donerun, 'done');

        $generated = wizard::get_generated_modules($this->instanceid, new \stdClass());

        // Items/Missions/Comércio now also require their own run's manifest to still point at
        // real content — an empty manifest must not keep the card disabled either.
        $this->assertFalse($generated['items']);
        $this->assertFalse($generated['missions']);
        $this->assertFalse($generated['comercio']);
        // These six all have a real fingerprint, so a stale 'done' run with nothing left to
        // show for it must not keep the card disabled.
        $this->assertFalse($generated['playercoin']);
        $this->assertFalse($generated['avatars']);
        $this->assertFalse($generated['pill']);
        $this->assertFalse($generated['secret_drops']);
        $this->assertFalse($generated['latepenalty']);
        $this->assertFalse($generated['progress_item']);
    }

    /**
     * The exact regression this exists for: a 'done' run recorded NO manifest entries for Items
     * even though the AI genuinely created named items (proven by ai_logs in the real incident)
     * — the run's manifest is the source of truth, not the module having been requested.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_items_false_when_run_manifest_is_empty_but_ai_logged(): void {
        global $DB;

        $donerun = wizard::start_run($this->instanceid, 2, ['items']);
        wizard::finish_run($donerun, 'done');
        $DB->insert_record('block_playerhud_ai_logs', (object) [
            'blockinstanceid' => $this->instanceid, 'userid' => 2, 'action_type' => 'item',
            'object_name' => 'Ghost Item', 'ai_provider' => 'Gemini', 'timecreated' => time(),
        ]);

        $generated = wizard::get_generated_modules($this->instanceid, new \stdClass());

        $this->assertFalse($generated['items']);
    }

    /**
     * Items reports generated once its run's manifest points at an item that still exists —
     * the positive counterpart of the empty-manifest tests above.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_items_true_when_manifest_item_exists(): void {
        $donerun = wizard::start_run($this->instanceid, 2, ['items']);
        wizard::record_object($donerun, 'block_playerhud_items', $this->create_item());
        wizard::finish_run($donerun, 'done');

        $generated = wizard::get_generated_modules($this->instanceid, new \stdClass());

        $this->assertTrue($generated['items']);
    }

    /**
     * Content that already exists in the instance counts as generated even without any wizard
     * run — created before this rule existed, or through the manual management screens, the
     * wizard's own generators would skip it anyway.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_detects_existing_content(): void {
        global $DB;

        $now = time();
        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'PlayerCoin', 'xp' => 0, 'enabled' => 1,
            'tradable' => 1, 'secret' => 0, 'required_class_id' => '0', 'action_type' => 'playercoin',
            'action_value' => '', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => get_string('wizard_progress_item_name_scifi', 'block_playerhud'),
            'xp' => 0, 'enabled' => 1, 'tradable' => 0, 'secret' => 0, 'required_class_id' => '0',
            'action_type' => '', 'action_value' => '', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $this->instanceid, 'title' => 'Chapter 1', 'intro_text' => '',
            'unlock_date' => 0, 'required_level' => 0, 'sortorder' => 1,
        ]);
        $config = (object) ['enable_ranking' => 1];

        $generated = wizard::get_generated_modules($this->instanceid, $config);

        $this->assertTrue($generated['playercoin']);
        $this->assertTrue($generated['progress_item'], 'Any tone\'s progress item name counts.');
        $this->assertTrue($generated['rpg'], 'An existing chapter blocks generating an arc on top.');
        $this->assertTrue($generated['ranking']);
        $this->assertFalse($generated['avatars']);
        $this->assertFalse($generated['pill']);
        $this->assertFalse($generated['secret_drops']);
        $this->assertFalse($generated['latepenalty']);
    }

    /**
     * Ranking reads live off the block's own enable_ranking setting, never off run history —
     * checking its box any number of times across any number of completed runs must never make
     * it report generated while the setting itself is off, and must always report generated the
     * instant the setting is on, with zero wizard runs at all.
     *
     * @covers ::get_generated_modules
     */
    public function test_get_generated_modules_ranking_ignores_run_count(): void {
        for ($i = 0; $i < 3; $i++) {
            $run = wizard::start_run($this->instanceid, 2, ['ranking']);
            wizard::finish_run($run, 'done');
        }

        $off = wizard::get_generated_modules($this->instanceid, (object) ['enable_ranking' => 0]);
        $this->assertFalse($off['ranking'], 'Off in settings must report false no matter how many runs touched it.');

        $on = wizard::get_generated_modules($this->instanceid, (object) ['enable_ranking' => 1]);
        $this->assertTrue($on['ranking']);

        // No wizard run at all, setting simply on: still reports generated.
        $context = \context_course::instance($this->courseid);
        $freshinstance = $this->getDataGenerator()->create_block('playerhud', ['parentcontextid' => $context->id]);
        $generated = wizard::get_generated_modules($freshinstance->id, (object) ['enable_ranking' => 1]);
        $this->assertTrue($generated['ranking']);
    }

    /**
     * ensure_config_flag() turns a flag on when it is off, without touching any other config
     * property already stored — the shared one-directional helper generate_ranking() and the
     * Items/Missions/RPG generators all rely on to auto-enable their own tab.
     *
     * @covers ::ensure_config_flag
     */
    public function test_ensure_config_flag_turns_on_without_touching_other_config(): void {
        $blockinstance = \block_instance_by_id($this->instanceid);
        $blockinstance->instance_config_save((object) ['xp_per_level' => 50]);

        wizard::ensure_config_flag($this->instanceid, 'enable_items');

        $reloaded = \block_instance_by_id($this->instanceid);
        $this->assertSame(1, (int) $reloaded->config->enable_items);
        $this->assertSame(50, (int) $reloaded->config->xp_per_level);
    }

    /**
     * Already on: calling it again is a harmless no-op — the stored configdata comes out
     * byte-identical, proving instance_config_save() was never even called.
     *
     * @covers ::ensure_config_flag
     */
    public function test_ensure_config_flag_noop_when_already_on(): void {
        global $DB;

        $blockinstance = \block_instance_by_id($this->instanceid);
        $blockinstance->instance_config_save((object) ['enable_rpg' => 1]);
        $before = $DB->get_field('block_instances', 'configdata', ['id' => $this->instanceid]);

        wizard::ensure_config_flag($this->instanceid, 'enable_rpg');

        $after = $DB->get_field('block_instances', 'configdata', ['id' => $this->instanceid]);
        $this->assertSame($before, $after);
    }
}
