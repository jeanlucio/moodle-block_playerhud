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

/**
 * Tests for cleanup of this plugin's own tables when a block instance is deleted.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\instance_cleanup
 * @covers     \block_playerhud
 */
final class instance_delete_test extends advanced_testcase {
    /** @var int Block instance under test. */
    protected int $instanceid;

    /** @var int User owning the per-instance data. */
    protected int $userid;

    /**
     * Creates a block instance and one row in every one of the plugin's own tables,
     * all pointing at that instance, directly or through a parent id.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->userid = $user->id;
        $now = time();

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = $now;
        $bi->timemodified = $now;
        $this->instanceid = $DB->insert_record('block_instances', $bi);

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->userid,
            'currentxp' => 0,
            'enable_gamification' => 1,
            'ranking_visibility' => 1,
            'last_inventory_view' => 0,
            'last_shop_view' => 0,
            'milestones' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Item',
            'xp' => 10,
            'enabled' => 1,
            'secret' => 0,
            'tradable' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $dropid = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid' => $itemid,
            'name' => 'Drop',
            'maxusage' => 1,
            'respawntime' => 0,
            'code' => 'ABC123',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->userid,
            'itemid' => $itemid,
            'dropid' => $dropid,
            'source' => 'map',
            'timecreated' => $now,
        ]);

        $classid = $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Class',
            'base_hp' => 100,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->userid,
            'classid' => $classid,
            'karma' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Quest',
            'type' => 1,
            'requirement' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid,
            'userid' => $this->userid,
            'timecreated' => $now,
        ]);

        $chapterid = $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $this->instanceid,
            'title' => 'Chapter',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $nodeid = $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid,
            'content' => 'Node',
            'is_start' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid' => $nodeid,
            'text' => 'Choice',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Trade',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid' => $itemid,
            'qty' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_trade_rewards', (object) [
            'tradeid' => $tradeid,
            'itemid' => $itemid,
            'qty' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $tradeid,
            'userid' => $this->userid,
            'timecreated' => $now,
        ]);

        $DB->insert_record('block_playerhud_ai_logs', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->userid,
            'action_type' => 'item',
            'ai_provider' => 'gemini',
            'timecreated' => $now,
        ]);

        $runid = $DB->insert_record('block_playerhud_wizard_runs', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $this->userid,
            'modules' => '[]',
            'status' => 'done',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('block_playerhud_wizard_objects', (object) [
            'runid' => $runid,
            'objecttable' => 'block_playerhud_items',
            'objectid' => $itemid,
            'timecreated' => $now,
        ]);

        $DB->insert_record('block_playerhud_wizard_shortcodes', (object) [
            'runid' => $runid,
            'dropid' => $dropid,
            'cmid' => 0,
            'field' => 'intro',
            'timecreated' => $now,
        ]);

        set_user_preference('block_playerhud_avatar_' . $this->instanceid, $itemid, $this->userid);
    }

    /**
     * Deleting the block instance through the real core path must wipe every row this
     * plugin ever wrote for it, across all of its own tables and the per-instance avatar
     * preference — nothing keyed on this blockinstanceid should survive.
     */
    public function test_deleting_instance_cleans_every_table(): void {
        global $DB;

        $instance = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
        blocks_delete_instance($instance);

        $directtables = [
            'block_playerhud_user',
            'block_playerhud_items',
            'block_playerhud_drops',
            'block_playerhud_classes',
            'block_playerhud_rpg_progress',
            'block_playerhud_quests',
            'block_playerhud_chapters',
            'block_playerhud_trades',
            'block_playerhud_ai_logs',
            'block_playerhud_wizard_runs',
        ];
        foreach ($directtables as $table) {
            $this->assertEquals(
                0,
                $DB->count_records($table, ['blockinstanceid' => $this->instanceid]),
                "Table {$table} still has rows for the deleted instance."
            );
        }

        $this->assertEquals(0, $DB->count_records('block_playerhud_inventory'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_quest_log'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_story_nodes'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_choices'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_reqs'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_rewards'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_trade_log'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_wizard_objects'));
        $this->assertEquals(0, $DB->count_records('block_playerhud_wizard_shortcodes'));

        $this->assertFalse($DB->record_exists('block_instances', ['id' => $this->instanceid]));
        $this->assertEquals(
            0,
            $DB->count_records('user_preferences', ['name' => 'block_playerhud_avatar_' . $this->instanceid])
        );
    }
}
