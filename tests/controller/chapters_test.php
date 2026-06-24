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
 * Tests for the chapters controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use stdClass;

/**
 * Tests for the chapters controller persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\chapters
 */
final class chapters_test extends advanced_testcase {
    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    protected function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Inserts a chapter directly for the given block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param string $title Chapter title.
     * @return int The new chapter ID.
     */
    protected function seed_chapter(int $instanceid, string $title): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => $title,
            'sortorder'       => 1,
        ]);
    }

    /**
     * A new chapter is inserted with its fields bound to the block instance.
     *
     * @covers ::save_chapter
     */
    public function test_save_chapter_inserts_new_record(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();

        $data = (object) [
            'title'          => 'Intro',
            'intro_text'     => 'Welcome',
            'unlock_date'    => 0,
            'required_level' => 2,
            'sortorder'      => 3,
            'chapterid'      => 0,
        ];

        $id = (new chapters())->save_chapter($data, $instanceid);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('block_playerhud_chapters', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame($instanceid, (int) $record->blockinstanceid);
        $this->assertSame('Intro', $record->title);
        $this->assertSame('Welcome', $record->intro_text);
        $this->assertSame(2, (int) $record->required_level);
        $this->assertSame(3, (int) $record->sortorder);
    }

    /**
     * Saving with a chapterid updates that chapter in place.
     *
     * @covers ::save_chapter
     */
    public function test_save_chapter_updates_existing_record(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $chapterid = $this->seed_chapter($instanceid, 'Old title');

        $data = (object) [
            'title'     => 'New title',
            'chapterid' => $chapterid,
        ];

        $returned = (new chapters())->save_chapter($data, $instanceid);

        $this->assertSame($chapterid, $returned);
        $this->assertSame(1, $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $instanceid]));
        $record = $DB->get_record('block_playerhud_chapters', ['id' => $chapterid], '*', MUST_EXIST);
        $this->assertSame('New title', $record->title);
    }

    /**
     * Optional fields fall back to their defaults when absent.
     *
     * @covers ::save_chapter
     */
    public function test_save_chapter_applies_defaults(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();

        $data = (object) ['title' => 'Bare', 'chapterid' => 0];

        $id = (new chapters())->save_chapter($data, $instanceid);

        $record = $DB->get_record('block_playerhud_chapters', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('', $record->intro_text);
        $this->assertSame(0, (int) $record->unlock_date);
        $this->assertSame(0, (int) $record->required_level);
        $this->assertSame(1, (int) $record->sortorder);
    }

    /**
     * A chapter belonging to another instance cannot be updated.
     *
     * @covers ::save_chapter
     */
    public function test_save_chapter_rejects_foreign_instance(): void {
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $chapterid = $this->seed_chapter($instancea, 'Owned by A');

        $data = (object) ['title' => 'Hijack', 'chapterid' => $chapterid];

        $this->expectException(\dml_missing_record_exception::class);
        (new chapters())->save_chapter($data, $instanceb);
    }
}
