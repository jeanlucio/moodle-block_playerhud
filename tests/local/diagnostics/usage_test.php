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
 * Tests for the site-wide usage figures of the diagnostics page.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\diagnostics\usage
 */
final class usage_test extends advanced_testcase {
    /**
     * Inserts a PlayerHUD block instance in a new course.
     *
     * @param \stdClass|null $config Block configuration, or null for an unconfigured block.
     * @return int Block instance id.
     */
    private function create_instance(?\stdClass $config = null): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('block_instances', (object) [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => $config ? base64_encode(serialize($config)) : '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        \context_block::instance($id);
        return $id;
    }

    /**
     * Inserts a player row.
     *
     * @param int $instanceid Block instance id.
     * @param int $xp Current XP.
     * @param int $lastgain Timestamp of the last XP gain (timemodified).
     * @param int $enabled Whether gamification is on.
     * @return void
     */
    private function create_player(int $instanceid, int $xp, int $lastgain, int $enabled = 1): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid, 'userid' => $user->id, 'currentxp' => $xp,
            'enable_gamification' => $enabled, 'ranking_visibility' => 1,
            'timecreated' => $lastgain, 'timemodified' => $lastgain,
        ]);
    }

    /**
     * Inserts an item.
     *
     * @param int $instanceid Block instance id.
     * @return int Item id.
     */
    private function create_item(int $instanceid): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid, 'name' => 'Item', 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Adoption counts courses, instances in use, instances without items and features left on
     * (a missing flag counts as on, as view.php defaults it).
     */
    public function test_adoption(): void {
        $this->resetAfterTest(true);

        $withitems = $this->create_instance((object) ['enable_rpg' => 0, 'enable_quests' => 1]);
        $this->create_item($withitems);
        $playersonly = $this->create_instance((object) ['enable_ranking' => 0]);
        $this->create_player($playersonly, 10, time());
        $this->create_instance();

        $adoption = usage::get_adoption();

        $this->assertSame(3, $adoption['courses']);
        $this->assertSame(3, $adoption['instances']);
        $this->assertSame(2, $adoption['inuse']);
        $this->assertSame(2, $adoption['withoutitems']);
        $this->assertSame(
            ['enable_rpg' => 2, 'enable_quests' => 3, 'enable_ranking' => 2, 'enable_items' => 3],
            $adoption['features']
        );
    }

    /**
     * Engagement counts players, opt-outs and XP across live instances, and activity only
     * within the window; rows of a removed instance are left out.
     */
    public function test_engagement(): void {
        global $DB;
        $this->resetAfterTest(true);

        $now = time();
        $since = $now - usage::WINDOWDAYS * DAYSECS;
        $old = $since - DAYSECS;
        $live = $this->create_instance();

        $this->create_player($live, 100, $now);
        $this->create_player($live, 50, $old);
        $this->create_player($live, 0, $now, 0);

        $itemid = $this->create_item($live);
        $userid = $this->getDataGenerator()->create_user()->id;
        foreach ([$now, $old] as $time) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid' => $userid, 'itemid' => $itemid, 'source' => 'map', 'timecreated' => $time,
            ]);
        }
        foreach ([[3, $now], [-1, $now], [5, $old]] as [$delta, $time]) {
            $DB->insert_record('block_playerhud_stack_log', (object) [
                'userid' => $userid, 'itemid' => $itemid, 'delta' => $delta, 'source' => 'map', 'timecreated' => $time,
            ]);
        }
        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $live, 'name' => 'Quest', 'requirement' => '1', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $userid, 'timecreated' => $now,
        ]);
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $live, 'name' => 'Trade', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $tradeid, 'userid' => $userid, 'timecreated' => $old,
        ]);
        foreach (['Gemini', 'Gemini', 'Groq'] as $provider) {
            $DB->insert_record('block_playerhud_ai_logs', (object) [
                'blockinstanceid' => $live, 'userid' => $userid, 'action_type' => 'item',
                'ai_provider' => $provider, 'timecreated' => $now,
            ]);
        }
        $DB->insert_record('block_playerhud_wizard_runs', (object) [
            'blockinstanceid' => $live, 'userid' => $userid, 'status' => 'done', 'timecreated' => $now, 'timemodified' => $now,
        ]);

        // A removed instance (no block_instances row) whose rows must not be counted.
        $this->create_player(999999, 500, $now);
        $DB->insert_record('block_playerhud_ai_logs', (object) [
            'blockinstanceid' => 999999, 'userid' => $userid, 'action_type' => 'item',
            'ai_provider' => 'Groq', 'timecreated' => $now,
        ]);

        $engagement = usage::get_engagement($since);

        $this->assertSame(3, $engagement['players']);
        $this->assertSame(1, $engagement['active']);
        $this->assertSame(1, $engagement['optedout']);
        $this->assertSame(150, $engagement['xp']);
        $this->assertSame(4, $engagement['itemsreceived'], 'One recent inventory row plus a recent ledger gain of 3.');
        $this->assertSame(1, $engagement['questscompleted']);
        $this->assertSame(0, $engagement['trades']);
        $this->assertSame(['Gemini' => 2, 'Groq' => 1], $engagement['airequests']);
        $this->assertSame(1, $engagement['wizardruns']);
    }
}
