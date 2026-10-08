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
 * Tests that the quest screens escape names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\output\manage\tab_quests as manage_tab_quests;
use block_playerhud\output\view\tab_quests as view_tab_quests;
use block_playerhud\quest;
use block_playerhud\tests\escaping_testcase;

/**
 * Quest, item, trade and chapter names shown in the teacher's quest list and the student's quest tab.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_quests
 * @covers     \block_playerhud\output\view\tab_quests
 */
final class quests_escaping_test extends escaping_testcase {
    /**
     * Creates one quest per kind of requirement that shows a related name.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $now = time();
        $tradeid = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'groupid' => 0,
            'centralized' => 1, 'onetime' => 0, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $chapterid = (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $this->blockid, 'title' => self::CANARY, 'intro_text' => self::CANARY,
            'unlock_date' => 0, 'required_level' => 0, 'sortorder' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);

        $quest = [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'reward_xp' => 5,
            'reward_itemid' => $this->create_item(), 'reward_itemqty' => 1, 'enabled' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ];
        $DB->insert_record('block_playerhud_quests', (object) ($quest + [
            'type' => quest::TYPE_SPECIFIC_ITEM, 'requirement' => 2, 'req_itemid' => $this->create_item(),
        ]));
        $DB->insert_record('block_playerhud_quests', (object) ($quest + [
            'type' => quest::TYPE_SPECIFIC_TRADE, 'requirement' => 1, 'req_itemid' => $tradeid,
        ]));
        $DB->insert_record('block_playerhud_quests', (object) ($quest + [
            'type' => quest::TYPE_CHAPTER, 'requirement' => $chapterid, 'req_itemid' => 0,
        ]));
    }

    /**
     * The teacher's quest list shows the quest, requirement and reward names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_manage_quests_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $tab = new manage_tab_quests($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display());
    }

    /**
     * The student's quest tab shows the quest and reward names once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_student_quests_escapes_names_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $player = (object) ['userid' => $student->id, 'currentxp' => 0];
        $tab = new view_tab_quests(new \stdClass(), $player, $this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display());
    }
}
