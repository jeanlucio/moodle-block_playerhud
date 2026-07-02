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
            'source' => 'map', 'timecreated' => time(),
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
}
