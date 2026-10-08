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
 * Tests that the report and history screens escape names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\output\manage\tab_reports;
use block_playerhud\output\view\tab_history;
use block_playerhud\quest;
use block_playerhud\tests\escaping_testcase;

/**
 * Item, trade and quest names in the teacher's reports (KPIs, quest ranking, audit log) and in the
 * student's own history.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_reports
 * @covers     \block_playerhud\output\view\tab_history
 */
final class reports_escaping_test extends escaping_testcase {
    /** @var \stdClass The student whose activity is logged. */
    private \stdClass $student;

    /**
     * Logs one collected item, one completed trade (paid with a canary item) and one claimed quest.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $now = time();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->blockid, 'userid' => $this->student->id, 'currentxp' => 10,
            'timecreated' => $now, 'timemodified' => $now,
        ]);

        $itemid = $this->create_item();
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->student->id, 'itemid' => $itemid, 'dropid' => 0, 'source' => 'map',
            'timecreated' => $now, 'xpawarded' => 0,
        ]);

        $tradeid = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'groupid' => 0,
            'centralized' => 1, 'onetime' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid, 'itemid' => $itemid, 'qty' => 2, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $tradeid, 'userid' => $this->student->id, 'timecreated' => $now,
        ]);

        $questid = (int) $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'type' => quest::TYPE_LEVEL,
            'requirement' => 1, 'req_itemid' => 0, 'reward_xp' => 5, 'reward_itemid' => 0, 'reward_itemqty' => 1,
            'enabled' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $this->student->id, 'timecreated' => $now, 'xpawarded' => 5,
        ]);
    }

    /**
     * The reports overview shows the most collected item and the quest ranking once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_reports_overview_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $tab = new tab_reports($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display());
    }

    /**
     * The audit log of one student shows item, trade (with its cost) and quest names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_reports_audit_log_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);
        $_GET['r_userid'] = $this->student->id;

        $tab = new tab_reports($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display(), 'none');
    }

    /**
     * The student's own history shows item, trade (with its cost) and quest names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_student_history_escapes_names_once(int $striptags): void {
        global $PAGE;

        set_config('formatstringstriptags', $striptags);
        $this->setUser($this->student);
        $_GET['id'] = $this->course->id;

        $tab = new tab_history(new \stdClass(), (object) ['userid' => $this->student->id], $this->blockid);

        // The test $OUTPUT is the bootstrap renderer, which the tab's typed parameters reject.
        $output = $PAGE->get_renderer('core');
        $this->assert_escaped_once(
            $output->render_from_template('block_playerhud/tab_history', $tab->export_for_template($output))
        );
    }
}
