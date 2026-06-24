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
 * Tests for the scenes controller.
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
 * Tests for the scenes controller choice persistence.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\scenes
 */
final class scenes_test extends advanced_testcase {
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
     * Creates a chapter for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new chapter ID.
     */
    protected function make_chapter(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => 'Chapter',
            'sortorder'       => 1,
        ]);
    }

    /**
     * Creates a story node in the chapter.
     *
     * @param int $chapterid Owning chapter ID.
     * @param string $content Node content.
     * @return int The new node ID.
     */
    protected function make_node(int $chapterid, string $content = 'Node'): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid,
            'content'   => $content,
            'is_start'  => 0,
        ]);
    }

    /**
     * Creates an instance, a chapter and a node, returned as a triple.
     *
     * @return array [int $instanceid, int $chapterid, int $nodeid]
     */
    protected function make_scene(): array {
        $instanceid = $this->make_instance();
        $chapterid  = $this->make_chapter($instanceid);
        $nodeid     = $this->make_node($chapterid);
        return [$instanceid, $chapterid, $nodeid];
    }

    /**
     * Creates an RPG class for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new class ID.
     */
    protected function make_class(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Hero',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates an item for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new item ID.
     */
    protected function make_item(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Key',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Regression: a choice that grants a class keeps its set_class_id on save.
     *
     * @covers ::save_choices
     */
    public function test_save_choices_persists_a_granted_class(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();
        $classid = $this->make_class($instanceid);

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => 'Become a Hero', 'set_class_id' => $classid],
        ]);

        $choice = $DB->get_record('block_playerhud_choices', ['nodeid' => $nodeid], '*', MUST_EXIST);
        $this->assertSame($classid, (int) $choice->set_class_id);
    }

    /**
     * A granted class belonging to another instance is rejected (stored as zero).
     *
     * @covers ::save_choices
     */
    public function test_save_choices_zeroes_a_foreign_class(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();
        $foreignclass = $this->make_class($this->make_instance());

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => 'Hijack', 'set_class_id' => $foreignclass],
        ]);

        $choice = $DB->get_record('block_playerhud_choices', ['nodeid' => $nodeid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $choice->set_class_id);
    }

    /**
     * A valid next node and item cost are persisted.
     *
     * @covers ::save_choices
     */
    public function test_save_choices_persists_next_node_and_item_cost(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();
        $destnode = $this->make_node($chapterid, 'Destination');
        $itemid = $this->make_item($instanceid);

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => 'Advance', 'next_nodeid' => $destnode, 'cost_itemid' => $itemid, 'cost_item_qty' => 3],
        ]);

        $choice = $DB->get_record('block_playerhud_choices', ['nodeid' => $nodeid], '*', MUST_EXIST);
        $this->assertSame($destnode, (int) $choice->next_nodeid);
        $this->assertSame($itemid, (int) $choice->cost_itemid);
        $this->assertSame(3, (int) $choice->cost_item_qty);
    }

    /**
     * Choices with empty text are skipped.
     *
     * @covers ::save_choices
     */
    public function test_save_choices_skips_empty_text(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => ''],
            ['text' => 'Kept'],
        ]);

        $this->assertSame(1, $DB->count_records('block_playerhud_choices', ['nodeid' => $nodeid]));
    }

    /**
     * A next-node value of -1 creates a fresh follow-up node and links to it.
     *
     * @covers ::save_choices
     */
    public function test_save_choices_auto_creates_next_node(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();
        $before = $DB->count_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => 'Continue', 'next_nodeid' => -1],
        ]);

        $after = $DB->count_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $this->assertSame($before + 1, $after);

        $choice = $DB->get_record('block_playerhud_choices', ['nodeid' => $nodeid], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int) $choice->next_nodeid);
        $this->assertTrue($DB->record_exists('block_playerhud_story_nodes', ['id' => $choice->next_nodeid]));
    }

    /**
     * The auto-created node stores the choice text raw, not HTML-escaped.
     *
     * @covers ::save_choices
     */
    public function test_save_choices_auto_created_node_keeps_raw_choice_text(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();

        (new scenes())->save_choices($nodeid, $chapterid, $instanceid, [
            ['text' => 'Say "Nami"', 'next_nodeid' => -1],
        ]);

        $choice = $DB->get_record('block_playerhud_choices', ['nodeid' => $nodeid], '*', MUST_EXIST);
        $newnode = $DB->get_record('block_playerhud_story_nodes', ['id' => $choice->next_nodeid], '*', MUST_EXIST);
        $this->assertStringContainsString('Say "Nami"', $newnode->content);
        $this->assertStringNotContainsString('&quot;', $newnode->content);
    }
}
