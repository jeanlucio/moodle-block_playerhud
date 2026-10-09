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

namespace block_playerhud\local\diagnostics;

use advanced_testcase;

/**
 * Tests for the detection and cleanup of data left by removed block instances.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\diagnostics\integrity
 */
final class integrity_test extends advanced_testcase {
    /** @var \stdClass Course of the live instance. */
    protected $course;

    /** @var int Live block instance. */
    protected int $liveid;

    /** @var int Block instance that will be removed the way pre-v1.7.0 sites removed it. */
    protected int $orphanid;

    /** @var \stdClass Student playing in both instances. */
    protected $student;

    /**
     * Creates a live instance and a second one whose block_instances row is then removed,
     * each owning rows across the plugin's tables, plus a few loose child rows.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');

        $this->liveid = $this->create_instance();
        $this->orphanid = $this->create_instance();
        $this->populate($this->liveid);
        $this->populate($this->orphanid);
        $this->remove_instance_row($this->orphanid);
    }

    /**
     * Inserts a PlayerHUD block instance in the course.
     *
     * @return int Block instance id.
     */
    private function create_instance(): int {
        global $DB;
        $id = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($this->course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        \context_block::instance($id);
        return $id;
    }

    /**
     * Gives an instance a player, an item held by the player, a quest log entry and a
     * chapter with one scene and one choice.
     *
     * @param int $instanceid Block instance id.
     * @return void
     */
    private function populate(int $instanceid): void {
        global $DB;
        $now = time();

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid, 'userid' => $this->student->id, 'currentxp' => 120,
            'enable_gamification' => 1, 'ranking_visibility' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid, 'name' => 'Sword', 'xp' => 10, 'enabled' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->student->id, 'itemid' => $itemid, 'source' => 'map', 'timecreated' => $now,
        ]);
        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $instanceid, 'name' => 'Quest', 'type' => 1, 'requirement' => '2',
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $this->student->id, 'timecreated' => $now,
        ]);
        $chapterid = $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid, 'title' => 'Chapter', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $nodeid = $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid, 'content' => 'Scene',
        ]);
        $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid' => $nodeid, 'text' => 'Go on',
        ]);
        set_user_preference('block_playerhud_avatar_' . $instanceid, $itemid, $this->student->id);
    }

    /**
     * Removes only the block_instances row and its context, as core did before the plugin's
     * instance_delete() cleaned its own tables.
     *
     * @param int $instanceid Block instance id.
     * @return void
     */
    private function remove_instance_row(int $instanceid): void {
        global $DB;
        \context_helper::delete_instance(CONTEXT_BLOCK, $instanceid);
        $DB->delete_records('block_instances', ['id' => $instanceid]);
    }

    /**
     * Inserts child rows whose parents do not exist: an inventory row, a scene without its
     * chapter (with a choice) and a wizard rollback row without its run.
     *
     * @return void
     */
    private function create_loose_rows(): void {
        global $DB;
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->student->id, 'itemid' => 987654, 'source' => 'map', 'timecreated' => time(),
        ]);
        $nodeid = $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => 987654, 'content' => 'Lost scene',
        ]);
        $DB->insert_record('block_playerhud_choices', (object) ['nodeid' => $nodeid, 'text' => 'Lost choice']);
        $DB->insert_record('block_playerhud_wizard_objects', (object) [
            'runid' => 987654, 'objecttable' => 'block_playerhud_items', 'objectid' => 1, 'timecreated' => time(),
        ]);
    }

    /**
     * Counts the rows a block instance still owns across the plugin's tables.
     *
     * @param int $instanceid Block instance id.
     * @return int Row count.
     */
    private function count_instance_rows(int $instanceid): int {
        global $DB;
        $total = 0;
        foreach (integrity::DIRECT_TABLES as $table) {
            $total += $DB->count_records($table, ['blockinstanceid' => $instanceid]);
        }
        return $total;
    }

    /**
     * The orphan detection and the cleanup maps together must name every table of install.xml,
     * so a table added later cannot escape the diagnostics silently.
     */
    public function test_table_maps_cover_every_declared_table(): void {
        global $CFG;

        $xmldb = new \xmldb_file($CFG->dirroot . '/blocks/playerhud/db/install.xml');
        $xmldb->loadXMLStructure();
        $declared = array_map(static fn($table) => $table->getName(), $xmldb->getStructure()->getTables());

        $mapped = array_merge(integrity::DIRECT_TABLES, array_keys(integrity::CHILD_TABLES));
        sort($declared);
        sort($mapped);
        $this->assertSame($declared, $mapped);
    }

    /**
     * Only the instance whose block_instances row is gone is reported.
     */
    public function test_orphan_instance_ids(): void {
        $this->assertSame([$this->orphanid], integrity::get_orphan_instance_ids());
    }

    /**
     * Instances that never had a block_instances row (id 0, from early development data) count
     * as orphans too.
     */
    public function test_instance_zero_is_an_orphan(): void {
        global $DB;
        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => 0, 'name' => 'Stray', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertSame([0, $this->orphanid], integrity::get_orphan_instance_ids());
    }

    /**
     * The orphan summary carries its players, XP and items; with no log entry its course is unknown.
     */
    public function test_orphan_summary_without_log(): void {
        $orphans = integrity::get_orphan_instances();

        $this->assertCount(1, $orphans);
        $orphan = $orphans[0];
        $this->assertSame($this->orphanid, $orphan->id);
        $this->assertSame(1, $orphan->players);
        $this->assertSame(120, $orphan->xp);
        $this->assertSame(1, $orphan->items);
        $this->assertSame(0, $orphan->deletedusers);
        $this->assertSame(0, $orphan->courseid);
        $this->assertNull($orphan->courseexists);
    }

    /**
     * Enables the standard log store so events are written to logstore_standard_log.
     *
     * @return void
     */
    private function enable_standard_log(): void {
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * Earns XP in an instance through the real API (which logs xp_changed), then removes the
     * instance row.
     *
     * @return int Removed instance id.
     */
    private function create_logged_orphan(): int {
        global $DB;
        $instanceid = $this->create_instance();
        $playerid = $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid, 'userid' => $this->student->id, 'currentxp' => 0,
            'enable_gamification' => 1, 'ranking_visibility' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $player = $DB->get_record('block_playerhud_user', ['id' => $playerid]);
        \block_playerhud\game::change_xp($player, 50, $instanceid);
        $this->remove_instance_row($instanceid);
        return $instanceid;
    }

    /**
     * The course of an orphan is recovered from its last xp_changed log entry, and flagged as
     * still existing while the course is there.
     */
    public function test_orphan_course_found_in_log_and_still_exists(): void {
        $this->enable_standard_log();
        $loggedid = $this->create_logged_orphan();

        $orphans = array_column(integrity::get_orphan_instances(), null, 'id');

        $this->assertSame((int) $this->course->id, $orphans[$loggedid]->courseid);
        $this->assertTrue($orphans[$loggedid]->courseexists);
    }

    /**
     * Once the course itself is deleted, the orphan reports its course as deleted.
     */
    public function test_orphan_course_found_in_log_and_deleted(): void {
        $this->enable_standard_log();
        $loggedid = $this->create_logged_orphan();
        delete_course($this->course, false);

        $orphans = array_column(integrity::get_orphan_instances(), null, 'id');

        $this->assertSame((int) $this->course->id, $orphans[$loggedid]->courseid);
        $this->assertFalse($orphans[$loggedid]->courseexists);
    }

    /**
     * Loose rows are counted per child table, and a live instance's own rows are never loose.
     */
    public function test_count_loose_rows(): void {
        $this->assertSame(0, array_sum(integrity::count_loose_rows()));

        $this->create_loose_rows();
        $counts = integrity::count_loose_rows();

        $this->assertSame(1, $counts['block_playerhud_inventory']);
        $this->assertSame(1, $counts['block_playerhud_story_nodes']);
        $this->assertSame(1, $counts['block_playerhud_wizard_objects']);
        $this->assertSame(0, $counts['block_playerhud_choices'], 'Its scene still exists until the scene is cleaned.');
        $this->assertSame(3, array_sum($counts));
    }

    /**
     * Cleaning removes the selected orphan and every loose row (including a choice that only
     * becomes loose once its scene goes), keeps the live instance intact, and saves a copy first.
     */
    public function test_cleanup_deletes_orphan_and_loose_rows_only(): void {
        global $DB;
        $this->create_loose_rows();
        $livebefore = $this->count_instance_rows($this->liveid);

        $result = integrity::cleanup([$this->orphanid]);

        $this->assertSame(1, $result['instances']);
        $this->assertSame(0, $this->count_instance_rows($this->orphanid));
        $this->assertSame($livebefore, $this->count_instance_rows($this->liveid));
        $this->assertSame(0, array_sum(integrity::count_loose_rows()));
        $this->assertSame([], integrity::get_orphan_instance_ids());
        $this->assertFalse($DB->record_exists('user_preferences', ['name' => 'block_playerhud_avatar_' . $this->orphanid]));
        $this->assertTrue($DB->record_exists('user_preferences', ['name' => 'block_playerhud_avatar_' . $this->liveid]));

        $path = integrity::resolve_backup($result['file']);
        $this->assertNotNull($path);
        $backup = json_decode(file_get_contents($path), true);
        $this->assertSame([$this->orphanid], $backup['instances']);
        $this->assertCount(1, $backup['tables']['instance:block_playerhud_user']);
        $this->assertCount(1, $backup['tables']['instance:block_playerhud_choices']);
        $this->assertCount(1, $backup['tables']['instance:user_preferences']);
        $this->assertCount(1, $backup['tables']['loose:block_playerhud_inventory']);
        $this->assertCount(1, $backup['tables']['loose:block_playerhud_choices']);
        $rows = array_sum(array_map('count', $backup['tables']));
        $this->assertSame($rows, $result['rows']);
    }

    /**
     * A live instance id submitted with the form is ignored, and an orphan left unselected stays.
     */
    public function test_cleanup_never_touches_live_or_unselected_instances(): void {
        $livebefore = $this->count_instance_rows($this->liveid);
        $orphanbefore = $this->count_instance_rows($this->orphanid);
        $this->create_loose_rows();

        $result = integrity::cleanup([$this->liveid]);

        $this->assertSame(0, $result['instances']);
        $this->assertSame($livebefore, $this->count_instance_rows($this->liveid));
        $this->assertSame($orphanbefore, $this->count_instance_rows($this->orphanid));
        $this->assertSame(0, array_sum(integrity::count_loose_rows()));
    }

    /**
     * With nothing selected and no loose rows, no copy is written.
     */
    public function test_cleanup_with_nothing_to_delete_writes_no_copy(): void {
        $result = integrity::cleanup([]);

        $this->assertSame(['file' => '', 'instances' => 0, 'rows' => 0], $result);
        $this->assertSame([], integrity::list_backups());
    }

    /**
     * Backups are listed, downloadable only by their exact name, and deletable.
     */
    public function test_backup_files(): void {
        $result = integrity::cleanup([$this->orphanid]);

        $backups = integrity::list_backups();
        $this->assertCount(1, $backups);
        $this->assertSame($result['file'], $backups[0]->name);

        $this->assertNull(integrity::resolve_backup('../../config.php'));
        $this->assertNull(integrity::resolve_backup('orphans-20260101-000000-zzzz.json'));
        $this->assertFalse(integrity::delete_backup('orphans-20260101-000000-abcd.json'));

        $this->assertTrue(integrity::delete_backup($result['file']));
        $this->assertSame([], integrity::list_backups());
    }

    /**
     * Player rows of every orphan are counted, beyond the listed ones.
     */
    public function test_count_orphan_players(): void {
        global $DB;
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => 987654, 'userid' => $this->student->id, 'currentxp' => 1,
            'enable_gamification' => 1, 'ranking_visibility' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertSame(2, integrity::count_orphan_players());
    }

    /**
     * Only the requested number of orphans is detailed, and the total stays available.
     */
    public function test_orphan_list_is_bounded(): void {
        global $DB;
        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => 987654, 'name' => 'Stray', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $this->assertCount(2, integrity::get_orphan_instance_ids());
        $listed = integrity::get_orphan_instances(1);
        $this->assertCount(1, $listed);
        $this->assertSame($this->orphanid, $listed[0]->id, 'The lowest id is listed first.');
        $this->assertCount(2, integrity::get_orphan_instances(0));
    }

    /**
     * Orphans whose course still exists are listed before the others.
     */
    public function test_orphans_with_existing_course_come_first(): void {
        $this->enable_standard_log();
        $loggedid = $this->create_logged_orphan();

        $orphans = integrity::get_orphan_instances();

        $this->assertSame($loggedid, $orphans[0]->id);
        $this->assertTrue($orphans[0]->courseexists);
        $this->assertSame($this->orphanid, $orphans[1]->id);
    }
}
