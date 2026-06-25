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
     * @param int $sortorder Sort order position.
     * @return int The new chapter ID.
     */
    protected function seed_chapter(int $instanceid, string $title, int $sortorder = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => $title,
            'sortorder'       => $sortorder,
        ]);
    }

    /**
     * Inserts a story node with one choice for the chapter and returns both IDs.
     *
     * @param int $chapterid Owning chapter ID.
     * @return array [int $nodeid, int $choiceid]
     */
    protected function seed_node_with_choice(int $chapterid): array {
        global $DB;

        $nodeid = (int) $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid,
            'content'   => 'Node',
            'is_start'  => 1,
        ]);
        $choiceid = (int) $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid' => $nodeid,
            'text'   => 'Go',
        ]);
        return [$nodeid, $choiceid];
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
            'chapterid'      => 0,
        ];

        $id = (new chapters())->save_chapter($data, $instanceid);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('block_playerhud_chapters', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame($instanceid, (int) $record->blockinstanceid);
        $this->assertSame('Intro', $record->title);
        $this->assertSame('Welcome', $record->intro_text);
        $this->assertSame(2, (int) $record->required_level);
        // The first chapter is appended at position one.
        $this->assertSame(1, (int) $record->sortorder);
    }

    /**
     * New chapters are appended to the end of the instance's order.
     *
     * @covers ::save_chapter
     */
    public function test_save_chapter_appends_to_end(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();

        $first = (new chapters())->save_chapter((object) ['title' => 'First', 'chapterid' => 0], $instanceid);
        $second = (new chapters())->save_chapter((object) ['title' => 'Second', 'chapterid' => 0], $instanceid);

        $this->assertSame(1, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $first]));
        $this->assertSame(2, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $second]));
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

    /**
     * Deleting a chapter removes it along with its scenes and choices.
     *
     * @covers ::delete_chapter
     */
    public function test_delete_chapter_removes_chapter_scenes_and_choices(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $chapterid = $this->seed_chapter($instanceid, 'Doomed');
        [$nodeid, $choiceid] = $this->seed_node_with_choice($chapterid);

        (new chapters())->delete_chapter($chapterid, $instanceid);

        $this->assertFalse($DB->record_exists('block_playerhud_chapters', ['id' => $chapterid]));
        $this->assertFalse($DB->record_exists('block_playerhud_story_nodes', ['id' => $nodeid]));
        $this->assertFalse($DB->record_exists('block_playerhud_choices', ['id' => $choiceid]));
    }

    /**
     * A chapter from another instance cannot be deleted.
     *
     * @covers ::delete_chapter
     */
    public function test_delete_chapter_rejects_foreign_instance(): void {
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $chapterid = $this->seed_chapter($instancea, 'Owned by A');

        $this->expectException(\dml_missing_record_exception::class);
        (new chapters())->delete_chapter($chapterid, $this->make_instance());
    }

    /**
     * Moving a chapter up swaps its sort order with the previous one.
     *
     * @covers ::move_chapter
     */
    public function test_move_chapter_up_swaps_with_previous(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $first = $this->seed_chapter($instanceid, 'First', 1);
        $second = $this->seed_chapter($instanceid, 'Second', 2);

        (new chapters())->move_chapter($second, $instanceid, 'up');

        $this->assertSame(2, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $first]));
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $second]));
    }

    /**
     * Moving a chapter down swaps its sort order with the next one.
     *
     * @covers ::move_chapter
     */
    public function test_move_chapter_down_swaps_with_next(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $first = $this->seed_chapter($instanceid, 'First', 1);
        $second = $this->seed_chapter($instanceid, 'Second', 2);

        (new chapters())->move_chapter($first, $instanceid, 'down');

        $this->assertSame(2, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $first]));
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $second]));
    }

    /**
     * Moving the first chapter up is a no-op (it has no previous neighbour).
     *
     * @covers ::move_chapter
     */
    public function test_move_chapter_at_edge_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $first = $this->seed_chapter($instanceid, 'First', 1);
        $second = $this->seed_chapter($instanceid, 'Second', 2);

        (new chapters())->move_chapter($first, $instanceid, 'up');

        $this->assertSame(1, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $first]));
        $this->assertSame(2, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $second]));
    }

    /**
     * The move works even when chapters share a sort order (legacy data),
     * renumbering them into a distinct sequence.
     *
     * @covers ::move_chapter
     */
    public function test_move_chapter_reorders_equal_sortorders(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $first = $this->seed_chapter($instanceid, 'First', 1);
        $second = $this->seed_chapter($instanceid, 'Second', 1);

        (new chapters())->move_chapter($first, $instanceid, 'down');

        $this->assertSame(2, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $first]));
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_chapters', 'sortorder', ['id' => $second]));
    }
}
