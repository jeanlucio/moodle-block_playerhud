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

namespace block_playerhud\output\view;

use advanced_testcase;

/**
 * Tests for the student story chapter list (available/locked/completed states).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep — distinct from
 * classes/output/manage/tab_chapters.php, which is a different class already fully
 * typed and covered separately.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_chapters
 */
final class tab_chapters_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var \stdClass Test user. */
    protected $user;

    /**
     * Create a fresh course, block instance and user for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Creates a minimal block_instances row and returns its id.
     *
     * @return int The new block instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Creates a chapter with a starting scene and returns its id.
     *
     * @param string $title Chapter title.
     * @param int $unlockdate Unix timestamp the chapter unlocks at (0 = always unlocked).
     * @return int The new chapter ID.
     */
    private function create_chapter_with_start(string $title, int $unlockdate = 0): int {
        global $DB;
        $chapterid = $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $this->instanceid,
            'title'           => $title,
            'intro_text'      => '',
            'unlock_date'     => $unlockdate,
            'required_level'  => 0,
            'sortorder'       => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid'    => $chapterid,
            'content'      => 'Once upon a time...',
            'is_start'     => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        return $chapterid;
    }

    /**
     * No chapters at all renders the empty state instead of crashing.
     */
    public function test_display_with_no_chapters(): void {
        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_chapters(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertIsString($html);
    }

    /**
     * An unlocked, uncompleted chapter is listed as available.
     */
    public function test_display_lists_available_chapter(): void {
        $this->create_chapter_with_start('The Awakening');
        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_chapters(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('The Awakening', $html);
    }

    /**
     * A chapter recorded in the player's completed_chapters list renders as completed.
     */
    public function test_display_marks_completed_chapter(): void {
        global $DB;

        $chapterid = $this->create_chapter_with_start('Finished Tale');
        $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid'      => $this->instanceid,
            'userid'               => $this->user->id,
            'classid'              => 0,
            'karma'                => 0,
            'current_nodes'        => '{}',
            'completed_chapters'   => json_encode([$chapterid]),
        ]);

        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_chapters(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('Finished Tale', $html);
        $this->assertStringContainsString('ph-chapter-item--completed', $html);
    }

    /**
     * A chapter with a future unlock date renders as locked.
     */
    public function test_display_marks_locked_chapter(): void {
        $this->create_chapter_with_start('Future Saga', time() + DAYSECS);
        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_chapters(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('Future Saga', $html);
        $this->assertStringContainsString('ph-chapter-item--locked', $html);
    }
}
