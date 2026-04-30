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
use block_playerhud\game;
use block_playerhud\story_manager;

/**
 * Tests for story_manager: progress tracking, scene loading and choice processing.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\story_manager
 */
final class story_manager_test extends advanced_testcase {
    /** @var int Block instance ID shared across test methods. */
    protected int $instanceid;

    /**
     * Creates a real block instance and forces its context entry to exist.
     */
    protected function setup_block_instance(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->subpagepattern = null;
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = time();
        $bi->timemodified = time();

        $this->instanceid = $DB->insert_record('block_instances', $bi);

        // Ensure the block context row exists before prepare_node_data() calls it.
        \context_block::instance($this->instanceid);
    }

    /**
     * Inserts a chapter row for this block instance.
     *
     * @param string $title Chapter title.
     * @return \stdClass The created chapter record.
     */
    protected function create_chapter(string $title): \stdClass {
        global $DB;

        $chapter = (object) [
            'blockinstanceid' => $this->instanceid,
            'title'           => $title,
            'intro_text'      => '',
            'unlock_date'     => 0,
            'required_level'  => 0,
            'sortorder'       => 1,
        ];
        $chapter->id = $DB->insert_record('block_playerhud_chapters', $chapter);
        return $chapter;
    }

    /**
     * Inserts a story node row.
     *
     * @param int    $chapterid Chapter this node belongs to.
     * @param string $content   Node text content.
     * @param bool   $isstart   Whether this is the starting node.
     * @return \stdClass The created node record.
     */
    protected function create_node(int $chapterid, string $content, bool $isstart = false): \stdClass {
        global $DB;

        $node = (object) [
            'chapterid' => $chapterid,
            'content'   => $content,
            'is_start'  => $isstart ? 1 : 0,
        ];
        $node->id = $DB->insert_record('block_playerhud_story_nodes', $node);
        return $node;
    }

    /**
     * Inserts a choice row linking two nodes.
     *
     * @param int $nodeid     Source node ID.
     * @param string $text    Choice label text.
     * @param int $nextnodeid Destination node ID (0 = terminal).
     * @param int $karmadelta Karma change when this choice is made.
     * @return \stdClass The created choice record.
     */
    protected function create_choice(
        int $nodeid,
        string $text,
        int $nextnodeid = 0,
        int $karmadelta = 0
    ): \stdClass {
        global $DB;

        $choice = (object) [
            'nodeid'       => $nodeid,
            'text'         => $text,
            'next_nodeid'  => $nextnodeid,
            'req_class_id' => 0,
            'req_karma_min' => 0,
            'karma_delta'  => $karmadelta,
            'set_class_id' => 0,
            'cost_itemid'  => 0,
            'cost_item_qty' => 1,
        ];
        $choice->id = $DB->insert_record('block_playerhud_choices', $choice);
        return $choice;
    }

    /**
     * get_or_create_progress creates a zero-state record on first call.
     *
     * @covers ::get_or_create_progress
     */
    public function test_get_or_create_progress_creates_new_record(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        $progress = story_manager::get_or_create_progress($this->instanceid, $user->id);

        $this->assertEquals($this->instanceid, (int) $progress->blockinstanceid);
        $this->assertEquals($user->id, (int) $progress->userid);
        $this->assertEquals(0, (int) $progress->classid);
        $this->assertEquals(0, (int) $progress->karma);

        $count = $DB->count_records(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $this->assertEquals(1, $count);
    }

    /**
     * get_or_create_progress returns the existing record on repeated calls.
     *
     * @covers ::get_or_create_progress
     */
    public function test_get_or_create_progress_does_not_duplicate(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        story_manager::get_or_create_progress($this->instanceid, $user->id);
        story_manager::get_or_create_progress($this->instanceid, $user->id);

        $count = $DB->count_records(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $this->assertEquals(1, $count);
    }

    /**
     * load_scene throws a moodle_exception when the chapter does not belong to the instance.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_throws_for_invalid_chapter(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        try {
            story_manager::load_scene($this->instanceid, $user->id, 99999);
            $this->fail('Expected moodle_exception not thrown.');
        } catch (\moodle_exception $e) {
            // Any moodle_exception is acceptable; MUST_EXIST produces dml_missing_record_exception.
            $this->assertInstanceOf(\moodle_exception::class, $e);
        }
    }

    /**
     * load_scene throws when the chapter has no start node.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_throws_when_no_start_node(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Empty Chapter');

        try {
            story_manager::load_scene($this->instanceid, $user->id, $chapter->id);
            $this->fail('Expected moodle_exception with errorcode story_error_node_not_found.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_node_not_found', $e->errorcode);
        }
    }

    /**
     * load_scene returns the start node when the player has no saved progress.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_returns_start_node(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter One');
        $startnode = $this->create_node($chapter->id, 'A dark forest awaits...', true);

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertArrayHasKey('node', $result);
        $this->assertStringContainsString('dark forest', $result['node']['content']);
    }

    /**
     * load_scene saves the start-node ID to current_nodes on first visit.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_saves_start_node_to_current_nodes(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter One');
        $startnode = $this->create_node($chapter->id, 'You enter the town.', true);

        story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $currentnodes = $DB->get_field(
            'block_playerhud_rpg_progress',
            'current_nodes',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $decoded = json_decode($currentnodes, true);

        $this->assertArrayHasKey($chapter->id, $decoded);
        $this->assertContains($startnode->id, $decoded[$chapter->id]);
    }

    /**
     * load_scene resumes from the node stored in current_nodes.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_resumes_from_saved_node(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter Two');
        $startnode = $this->create_node($chapter->id, 'The road begins...', true);
        $midnode   = $this->create_node($chapter->id, 'You reach the crossroads.');

        // Simulate that the player is already at midnode.
        $progress = story_manager::get_or_create_progress($this->instanceid, $user->id);
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'current_nodes',
            json_encode([$chapter->id => [$startnode->id, $midnode->id]]),
            ['id' => $progress->id]
        );

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertStringContainsString('crossroads', $result['node']['content']);
    }

    /**
     * load_scene includes the finished flag when the chapter is already completed.
     *
     * @covers ::load_scene
     */
    public function test_load_scene_shows_finished_flag_for_completed_chapter(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Done Chapter');
        $this->create_node($chapter->id, 'The end.', true);

        $progress = story_manager::get_or_create_progress($this->instanceid, $user->id);
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'completed_chapters',
            json_encode([$chapter->id]),
            ['id' => $progress->id]
        );

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertTrue($result['finished'] ?? false);
        $this->assertEquals($chapter->id, $result['chapterid'] ?? null);
    }

    /**
     * make_choice advances to the next node when next_nodeid > 0 and the next
     * node has outgoing choices (non-terminal).
     *
     * @covers ::make_choice
     */
    public function test_make_choice_advances_to_next_node(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter');

        $nodea = $this->create_node($chapter->id, 'Node A', true);
        $nodeb = $this->create_node($chapter->id, 'Node B');
        $nodec = $this->create_node($chapter->id, 'Node C');

        // A → B (non-terminal: B has a choice pointing to C).
        $choiceab = $this->create_choice($nodea->id, 'Go to B', $nodeb->id);
        // B → C (gives B at least one outgoing choice so it is not terminal).
        $this->create_choice($nodeb->id, 'Go to C', $nodec->id);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choiceab->id);

        $this->assertArrayHasKey('node', $result);
        $this->assertStringContainsString('Node B', $result['node']['content']);
        $this->assertArrayNotHasKey('finished', $result);
    }

    /**
     * make_choice marks the chapter as complete when the next node has no choices.
     *
     * @covers ::make_choice
     */
    public function test_make_choice_marks_chapter_complete_at_terminal_node(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter');

        $nodea = $this->create_node($chapter->id, 'Start', true);
        $nodeend = $this->create_node($chapter->id, 'The End');  // No choices → terminal.

        $choice = $this->create_choice($nodea->id, 'Finish', $nodeend->id);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertTrue($result['finished'] ?? false);
        $this->assertEquals($chapter->id, $result['chapterid']);

        $completedjson = $DB->get_field(
            'block_playerhud_rpg_progress',
            'completed_chapters',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $completed = json_decode($completedjson, true);
        $this->assertContains($chapter->id, array_map('intval', $completed));
    }

    /**
     * make_choice marks the chapter complete when next_nodeid is 0 (no next scene).
     *
     * @covers ::make_choice
     */
    public function test_make_choice_marks_chapter_complete_when_next_nodeid_is_zero(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Short Story');

        $nodea = $this->create_node($chapter->id, 'Only node', true);
        $choice = $this->create_choice($nodea->id, 'End here', 0);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertTrue($result['finished'] ?? false);

        $completedjson = $DB->get_field(
            'block_playerhud_rpg_progress',
            'completed_chapters',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $this->assertContains($chapter->id, array_map('intval', json_decode($completedjson, true)));
    }

    /**
     * make_choice applies the karma_delta from the chosen choice.
     *
     * @covers ::make_choice
     */
    public function test_make_choice_applies_karma_delta(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Karma Chapter');

        $nodea = $this->create_node($chapter->id, 'A moral dilemma.', true);

        // Seed existing progress so adjust_karma has a record to update.
        story_manager::get_or_create_progress($this->instanceid, $user->id);

        $choice = $this->create_choice($nodea->id, 'Do good', 0, 50);

        story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertEquals(50, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * make_choice adds the chapter to completed_chapters exactly once on the first call.
     *
     * @covers ::make_choice
     */
    public function test_make_choice_records_chapter_completion_once(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('One-shot Chapter');

        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'End', 0);

        story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $completedjson = $DB->get_field(
            'block_playerhud_rpg_progress',
            'completed_chapters',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );
        $completed = array_map('intval', json_decode($completedjson, true));
        $this->assertCount(1, array_unique($completed));
        $this->assertContains($chapter->id, $completed);
    }

    /**
     * make_choice throws story_error_invalid_choice when the chapter is already completed,
     * preventing karma/class/reward re-farming.
     *
     * @covers ::make_choice
     */
    public function test_make_choice_throws_for_completed_chapter(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Completed Chapter');

        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'End', 0, 50);

        // First call: legitimate completion.
        story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        // Second call: chapter is now complete — must be rejected.
        try {
            story_manager::make_choice($this->instanceid, $user->id, $choice->id);
            $this->fail('Expected story_error_invalid_choice exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_invalid_choice', $e->errorcode);
        }
    }

    /**
     * make_choice throws story_error_invalid_choice when the choice does not belong
     * to the player's current node (intra-instance story bypass attempt).
     *
     * @covers ::make_choice
     */
    public function test_make_choice_throws_for_out_of_sequence_choice(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Sequential Chapter');

        $nodea = $this->create_node($chapter->id, 'Node A', true);
        $nodeb = $this->create_node($chapter->id, 'Node B');
        $nodec = $this->create_node($chapter->id, 'Node C');

        // A → B → C.
        $this->create_choice($nodea->id, 'Go to B', $nodeb->id);
        $choicebc = $this->create_choice($nodeb->id, 'Go to C', $nodec->id);

        // Player is at Node A (start). Submitting a choice that belongs to Node B is invalid.
        try {
            story_manager::make_choice($this->instanceid, $user->id, $choicebc->id);
            $this->fail('Expected story_error_invalid_choice exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_invalid_choice', $e->errorcode);
        }
    }
}
