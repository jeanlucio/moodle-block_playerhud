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
 * @covers     \block_playerhud\controller\scenes
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

    /**
     * Creates a choice that leads from one scene to another.
     *
     * @param int $nodeid Scene the choice belongs to.
     * @param int $nextnodeid Scene the choice leads to.
     * @return int The new choice ID.
     */
    protected function make_choice_to(int $nodeid, int $nextnodeid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid'      => $nodeid,
            'text'        => 'Go on',
            'next_nodeid' => $nextnodeid,
        ]);
    }

    /**
     * Stores a student's saved story path.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid Student ID.
     * @param array $nodes Map of chapter ID to the list of scene IDs visited.
     * @return int The new progress ID.
     */
    protected function make_progress(int $instanceid, int $userid, array $nodes): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid'    => $instanceid,
            'userid'             => $userid,
            'classid'            => 0,
            'karma'              => 0,
            'current_nodes'      => json_encode($nodes),
            'completed_chapters' => '[]',
            'timecreated'        => time(),
            'timemodified'       => time(),
        ]);
    }

    /**
     * Deleting a scene removes its own choices and turns the choices that led to it into
     * chapter endings instead of leaving them pointing at a scene that no longer exists.
     */
    public function test_delete_scene_unlinks_choices_that_led_to_it(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $doomed] = $this->make_scene();
        $first = $this->make_node($chapterid);
        $other = $this->make_node($chapterid);
        $ownchoice = $this->make_choice_to($doomed, $other);
        $incoming = $this->make_choice_to($first, $doomed);
        $unrelated = $this->make_choice_to($first, $other);

        $this->assertTrue((new scenes())->delete_scene($doomed, $chapterid, $instanceid));

        $this->assertFalse($DB->record_exists('block_playerhud_story_nodes', ['id' => $doomed]));
        $this->assertFalse($DB->record_exists('block_playerhud_choices', ['id' => $ownchoice]));
        $this->assertEquals(0, $DB->get_field('block_playerhud_choices', 'next_nodeid', ['id' => $incoming]));
        $this->assertEquals($other, $DB->get_field('block_playerhud_choices', 'next_nodeid', ['id' => $unrelated]));
    }

    /**
     * A student who was on the deleted scene goes back to the previous one, and a student
     * whose path never touched it keeps the path untouched.
     */
    public function test_delete_scene_repairs_saved_student_paths(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $doomed] = $this->make_scene();
        $previous = $this->make_node($chapterid);
        $otherchapter = $this->make_chapter($instanceid);
        $keep = $this->make_node($otherchapter);
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[] = $this->getDataGenerator()->create_user()->id;
        }
        $onit = $this->make_progress($instanceid, $users[0], [$chapterid => [$previous, $doomed], $otherchapter => [$keep]]);
        $onlyit = $this->make_progress($instanceid, $users[1], [$chapterid => [$doomed]]);
        $untouched = $this->make_progress($instanceid, $users[2], [$chapterid => [$previous]]);

        (new scenes())->delete_scene($doomed, $chapterid, $instanceid);

        $path = json_decode($DB->get_field('block_playerhud_rpg_progress', 'current_nodes', ['id' => $onit]), true);
        $this->assertEquals([$previous], $path[$chapterid]);
        $this->assertEquals([$keep], $path[$otherchapter]);

        $path = json_decode($DB->get_field('block_playerhud_rpg_progress', 'current_nodes', ['id' => $onlyit]), true);
        $this->assertArrayNotHasKey($chapterid, $path);

        $path = json_decode($DB->get_field('block_playerhud_rpg_progress', 'current_nodes', ['id' => $untouched]), true);
        $this->assertEquals([$previous], $path[$chapterid]);
    }

    /**
     * A scene id that belongs to another chapter of the page is not deleted.
     */
    public function test_delete_scene_ignores_a_scene_from_another_chapter(): void {
        global $DB;
        $this->resetAfterTest();
        [$instanceid, $chapterid, $nodeid] = $this->make_scene();
        $otherchapter = $this->make_chapter($instanceid);

        $this->assertFalse((new scenes())->delete_scene($nodeid, $otherchapter, $instanceid));
        $this->assertTrue($DB->record_exists('block_playerhud_story_nodes', ['id' => $nodeid]));
    }

    /**
     * The list preview is plain text: entities are decoded (the template escapes it once), tags
     * are dropped, whitespace is collapsed and the cut counts characters, not bytes.
     */
    public function test_snippet_is_plain_text_cut_by_characters(): void {
        $this->assertSame(
            "Tom & Jerry's mundo",
            scenes::snippet("<p>Tom &amp; Jerry&#039;s&nbsp;\n mundo</p>")
        );

        $snippet = scenes::snippet(str_repeat('ê', 150));
        $this->assertSame(100, \core_text::strlen($snippet));
        $this->assertTrue(mb_check_encoding($snippet, 'UTF-8'));
    }
}
