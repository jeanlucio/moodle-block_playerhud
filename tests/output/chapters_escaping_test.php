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
 * Tests that the chapter screens and story choices escape names exactly once.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use block_playerhud\output\manage\tab_chapters as manage_tab_chapters;
use block_playerhud\output\view\tab_chapters as view_tab_chapters;
use block_playerhud\story_manager;
use block_playerhud\tests\escaping_testcase;

/**
 * Chapter titles on the teacher's and student's chapter tabs, and the item and class names a story
 * choice sends to the player script, which writes them with textContent.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_chapters
 * @covers     \block_playerhud\output\view\tab_chapters
 * @covers     \block_playerhud\story_manager
 */
final class chapters_escaping_test extends escaping_testcase {
    /** @var int The chapter id. */
    private int $chapterid;

    /**
     * Creates a chapter with a start scene and one choice that needs a class and costs an item.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $now = time();
        $this->chapterid = (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $this->blockid, 'title' => self::CANARY, 'intro_text' => self::CANARY,
            'unlock_date' => 0, 'required_level' => 0, 'sortorder' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $nodeid = (int) $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $this->chapterid, 'content' => 'Scene', 'is_start' => 1,
        ]);
        $classid = (int) $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->blockid, 'name' => self::CANARY, 'base_hp' => 10,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid' => $nodeid, 'text' => self::CANARY, 'next_nodeid' => 0, 'req_class_id' => $classid,
            'req_karma_min' => 0, 'karma_delta' => 0, 'set_class_id' => 0,
            'cost_itemid' => $this->create_item(), 'cost_item_qty' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * The teacher's chapter list shows titles once-escaped; the delete confirmation is read as text.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_manage_chapters_escapes_titles_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $tab = new manage_tab_chapters($this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display(), true);
    }

    /**
     * The student's chapter list shows titles and intro text once-escaped.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_student_chapters_escapes_titles_once(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $player = (object) ['userid' => $student->id, 'currentxp' => 0];
        $tab = new view_tab_chapters(new \stdClass(), $player, $this->blockid, (int) $this->course->id);

        $this->assert_escaped_once($tab->display());
    }

    /**
     * Choice data goes to the player script as JSON and is written with textContent, so the names it
     * carries must be plain text, not HTML.
     *
     * @dataProvider striptags_provider
     * @param int $striptags Value of formatstringstriptags.
     */
    public function test_story_choices_carry_plain_text(int $striptags): void {
        set_config('formatstringstriptags', $striptags);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $scene = story_manager::load_scene($this->blockid, (int) $student->id, $this->chapterid, false);

        $choice = $scene['node']['choices'][0];
        $this->assertSame(self::CANARY, $choice['text']);
        $this->assertSame(self::CANARY, $choice['req_class_name']);
        $this->assertSame(self::CANARY, $choice['cost_item_name']);
        $this->assertStringContainsString(self::CANARY, $choice['str_req_class']);
        $this->assertStringContainsString(self::CANARY, $choice['str_cost_item']);
    }
}
