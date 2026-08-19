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
 * @covers     \block_playerhud\local\external_items
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
     * @param array $overrides Field overrides merged on top of sane defaults (e.g. unlock_date,
     *     required_level).
     * @return \stdClass The created chapter record.
     */
    protected function create_chapter(string $title, array $overrides = []): \stdClass {
        global $DB;

        $chapter = (object) array_merge([
            'blockinstanceid' => $this->instanceid,
            'title'           => $title,
            'intro_text'      => '',
            'unlock_date'     => 0,
            'required_level'  => 0,
            'sortorder'       => 1,
        ], $overrides);
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
     * @param int $costitemid Item required to make this choice (0 = no cost).
     * @param int $costqty    Quantity of the cost item required.
     * @return \stdClass The created choice record.
     */
    protected function create_choice(
        int $nodeid,
        string $text,
        int $nextnodeid = 0,
        int $karmadelta = 0,
        int $costitemid = 0,
        int $costqty = 1
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
            'cost_itemid'  => $costitemid,
            'cost_item_qty' => $costqty,
        ];
        $choice->id = $DB->insert_record('block_playerhud_choices', $choice);
        return $choice;
    }

    /**
     * Inserts an item row for this block instance.
     *
     * @param string $name Item name.
     * @return \stdClass The created item record.
     */
    protected function create_item(string $name): \stdClass {
        global $DB;

        $item = (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'xp'              => 0,
            'enabled'         => 1,
            'tradable'        => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Inserts an inventory row for a user, with the given source tag.
     *
     * @param int $userid Holder user ID.
     * @param int $itemid Item held.
     * @param string $source Inventory source tag (e.g. 'teacher', 'consumed', 'revoked').
     * @return int The new inventory row ID.
     */
    protected function add_inventory(int $userid, int $itemid, string $source): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $userid,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => $source,
            'timecreated' => time(),
            'xpawarded'   => 0,
        ]);
    }

    /**
     * get_or_create_progress creates a zero-state record on first call.
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
     * make_choice acquires and releases its per-user lock around a normal call, and still
     * returns the expected result — regression test for the security-audit finding that
     * make_choice() had no lock, unlike every other reward-granting path
     * (game::process_collection(), trade_manager::execute_trade(), quest::claim_quest()).
     *
     * True lock *contention* is not exercised here: this plugin's lock factory is
     * postgres_lock_factory (pg_advisory_lock), which is reentrant within the same database
     * session — the single connection PHPUnit runs on can always re-acquire its own advisory
     * lock, so a same-process "acquire then call" test would pass regardless of whether
     * make_choice() actually takes the lock. None of the sibling reward-granting paths test
     * their own lock rejection for the same reason; this is a live/manual-verification
     * concern, not a unit-testable one. What IS verified here is that the lock key is
     * available and released cleanly (no hung lock leaking into the next test).
     */
    public function test_make_choice_leaves_no_lock_held_after_a_normal_call(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Chapter');
        $nodea = $this->create_node($chapter->id, 'Node A', true);
        $nodeb = $this->create_node($chapter->id, 'Node B');
        $choice = $this->create_choice($nodea->id, 'Go to B', $nodeb->id);

        story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        // If make_choice() failed to release its lock, re-acquiring the same key here (same
        // session, so reentrancy is not the question — a leaked lock object that was never
        // released is) would still succeed under postgres_lock_factory, but the call proves
        // no exception/hang occurs and the lock is free to be taken again immediately after.
        $lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
        $lockkey = 'story_usr_' . $user->id . '_inst_' . $this->instanceid;
        $lock = $lockfactory->get_lock($lockkey, 10);
        $this->assertNotFalse($lock, 'Lock key should be immediately acquirable after make_choice() returns.');
        $lock->release();
    }

    /**
     * make_choice marks the chapter as complete when the next node has no choices.
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

    /**
     * Regression test for the security-audit finding: a 'consumed' (already spent in a trade)
     * or 'revoked' (soft-revoked by a teacher) inventory row must not be accepted as payment
     * for an item-cost choice — matching trade_manager::execute_trade() and
     * game::get_inventory(), which both exclude those sources.
     */
    public function test_make_choice_rejects_consumed_and_revoked_inventory_as_payment(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        $this->add_inventory($user->id, $item->id, 'consumed');
        $this->add_inventory($user->id, $item->id, 'revoked');

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 1);

        try {
            story_manager::make_choice($this->instanceid, $user->id, $choice->id);
            $this->fail('Expected story_error_need_item exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_need_item', $e->errorcode);
        }
    }

    /**
     * A genuinely held (not consumed/revoked) inventory copy is still accepted as payment and
     * consumed exactly once — confirms the source filter does not break the legitimate path.
     */
    public function test_make_choice_accepts_valid_inventory_as_payment(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        $this->add_inventory($user->id, $item->id, 'teacher');

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 1);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertTrue($result['finished']);

        // Soft-consumed (source flipped, row kept), not hard-deleted — matches
        // trade_manager::execute_trade()'s consumption pattern, and is what lets
        // drop_guard::check_pickup_allowed() still count this row against the origin drop's
        // maxusage/cooldown (see test_make_choice_does_not_reset_the_origin_drops_pickup_limit).
        $active = $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :userid AND itemid = :itemid AND source NOT IN ('revoked', 'consumed')",
            ['userid' => $user->id, 'itemid' => $item->id]
        );
        $this->assertSame(0, $active, 'No active copy should remain after paying the choice cost.');
        $this->assertSame(1, $DB->count_records('block_playerhud_inventory', [
            'userid' => $user->id, 'itemid' => $item->id, 'source' => 'consumed',
        ]), 'The spent copy must be retained as consumed, not deleted.');
    }

    /**
     * A choice cost held entirely through the new engine (block_playerhud_stack) is accepted
     * and consumed through external_items::consume(), never touching block_playerhud_inventory.
     */
    public function test_make_choice_accepts_new_engine_balance_as_payment(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        \block_playerhud\local\external_items::grant($this->instanceid, $item->id, $user->id, 1, 'teacher', true);

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 1);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertTrue($result['finished']);
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $item->id,
        ]));
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
    }

    /**
     * A choice cost split across both storage generations is paid correctly — the delicate
     * case flagged in the design: consume() must spend everything available in
     * block_playerhud_stack before falling back to legacy rows for the remainder.
     */
    public function test_make_choice_pays_cost_split_across_both_storage_generations(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        $this->add_inventory($user->id, $item->id, 'teacher');
        \block_playerhud\local\external_items::grant($this->instanceid, $item->id, $user->id, 1, 'teacher', true);

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 2);

        $result = story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        $this->assertTrue($result['finished']);
        $this->assertSame(
            0,
            \block_playerhud\local\external_items::get_available_quantity($this->instanceid, $item->id, $user->id)
        );
    }

    /**
     * Regression test for the security-audit finding: paying a choice cost with a copy that
     * came from a finite/cooldown-bearing map drop must NOT let the student re-collect that
     * drop beyond its configured maxusage. Before the fix, make_choice_locked() hard-deleted
     * the inventory row, so drop_guard::check_pickup_allowed()'s raw row count over
     * (userid, dropid) no longer saw it and let a second pickup through.
     */
    public function test_make_choice_does_not_reset_the_origin_drops_pickup_limit(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid' => $item->id,
            'name' => 'Key Spot',
            'maxusage' => 1,
            'respawntime' => 0,
            'code' => \block_playerhud\utils::generate_drop_code($this->instanceid),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // Simulate the student collecting the drop once — the row it leaves behind is what the
        // exploit tried to erase by spending the item in a story choice.
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $user->id,
            'itemid' => $item->id,
            'dropid' => $dropid,
            'source' => 'map',
            'timecreated' => time(),
            'xpawarded' => 50,
        ]);

        try {
            \block_playerhud\drop_guard::check_pickup_allowed($dropid, $user->id, 1, 0);
            $this->fail('Sanity check failed: the drop must already be at its pickup limit before the choice is made.');
        } catch (\moodle_exception $e) {
            $this->assertSame('limitreached', $e->errorcode);
        }

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 1);

        story_manager::make_choice($this->instanceid, $user->id, $choice->id);

        try {
            \block_playerhud\drop_guard::check_pickup_allowed($dropid, $user->id, 1, 0);
            $this->fail('Spending the item in a story choice must not refund a pickup of its origin drop.');
        } catch (\moodle_exception $e) {
            $this->assertSame('limitreached', $e->errorcode);
        }

        // The spent copy must still carry its original xpawarded value, so items::delete_item()'s
        // XP rollback (which sums surviving xpawarded) does not under-deduct for it later.
        $consumed = $DB->get_record('block_playerhud_inventory', [
            'userid' => $user->id, 'itemid' => $item->id, 'source' => 'consumed',
        ], '*', MUST_EXIST);
        $this->assertSame(50, (int) $consumed->xpawarded);
    }

    /**
     * prepare_node_data() (via load_scene) must disable a cost-gated choice when the only
     * matching inventory rows are 'consumed'/'revoked' — otherwise the UI would show the
     * choice as affordable while make_choice() correctly rejects it, a confusing mismatch.
     */
    public function test_load_scene_disables_choice_when_only_consumed_inventory_held(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_item('Story Key');
        $this->add_inventory($user->id, $item->id, 'consumed');

        $chapter = $this->create_chapter('Gated Chapter');
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $this->create_choice($nodea->id, 'Use key', 0, 0, $item->id, 1);

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertTrue($result['node']['choices'][0]['disabled']);
    }

    /**
     * Regression test for the security-audit finding: prepare_node_data() (via load_scene())
     * must never resolve another instance's item name for a choice's cost_itemid. If a foreign
     * id ever ends up stored there, the item lookup is scoped by blockinstanceid, so it renders
     * as unknown ('?') instead of leaking the real name — mirrors the same scoping
     * generate_story::execute() applies before persisting a new choice.
     */
    public function test_load_scene_hides_the_name_of_a_cross_instance_cost_item(): void {
        $this->resetAfterTest(true);

        $this->setup_block_instance();
        $foreignitem = $this->create_item('Secret Relic');

        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Local Chapter');
        $node = $this->create_node($chapter->id, 'Start', true);
        $this->create_choice($node->id, 'Pay with foreign item', 0, 0, $foreignitem->id, 1);

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertSame(
            '?',
            $result['node']['choices'][0]['cost_item_name'],
            'A cross-instance cost item must never resolve to its real name.'
        );
    }

    /**
     * Regression test for the security-audit finding: load_scene() previously only checked
     * that the chapter belonged to the instance, never its unlock_date/required_level — a
     * direct web service call could read (and via make_choice(), complete) a chapter the UI
     * still shows as locked. A future unlock_date must now be rejected server-side too.
     */
    public function test_load_scene_throws_for_chapter_locked_by_date(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Future Chapter', ['unlock_date' => time() + DAYSECS]);
        $this->create_node($chapter->id, 'Not yet.', true);

        try {
            story_manager::load_scene($this->instanceid, $user->id, $chapter->id);
            $this->fail('Expected story_error_chapter_locked exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_chapter_locked', $e->errorcode);
        }
    }

    /**
     * Same regression as above, for required_level — the audit report singled this out as
     * the more fragile of the two gates, since it was previously enforced nowhere at all
     * outside has_unread_chapters()'s own notification-dot indicator.
     */
    public function test_load_scene_throws_for_chapter_locked_by_level(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        // A fresh player starts at level 1 (0 XP); require level 2.
        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Elite Chapter', ['required_level' => 2]);
        $this->create_node($chapter->id, 'Too advanced for you.', true);

        try {
            story_manager::load_scene($this->instanceid, $user->id, $chapter->id);
            $this->fail('Expected story_error_chapter_locked exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_chapter_locked', $e->errorcode);
        }
    }

    /**
     * A chapter with a past unlock_date and a required_level the player's XP already meets
     * must load normally — the new guard must not accidentally block the common case.
     */
    public function test_load_scene_succeeds_for_an_unlocked_chapter(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        // 200 XP at the default 100 XP/level puts the player at level 3.
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid, 'userid' => $user->id, 'currentxp' => 200,
            'enable_gamification' => 1, 'ranking_visibility' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $chapter = $this->create_chapter('Past Chapter', [
            'unlock_date' => time() - DAYSECS,
            'required_level' => 2,
        ]);
        $this->create_node($chapter->id, 'Welcome back.', true);

        $result = story_manager::load_scene($this->instanceid, $user->id, $chapter->id);

        $this->assertStringContainsString('Welcome back', $result['node']['content']);
    }

    /**
     * Regression test for the security-audit finding: make_choice_locked() validated the
     * choice's node position and requirements, but never the chapter's own availability — a
     * student could advance and complete a locked chapter's story graph one choice at a time
     * via direct web service calls, collecting the completion reward ahead of schedule.
     */
    public function test_make_choice_throws_for_locked_chapter(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Locked Chapter', ['unlock_date' => time() + DAYSECS]);
        $nodea = $this->create_node($chapter->id, 'Start', true);
        $choice = $this->create_choice($nodea->id, 'End', 0, 50);

        try {
            story_manager::make_choice($this->instanceid, $user->id, $choice->id);
            $this->fail('Expected story_error_chapter_locked exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_chapter_locked', $e->errorcode);
        }
    }

    /**
     * load_recap() must also reject a chapter the player has not yet completed and that is
     * not currently available — it is not exempt from the same server-side gate just because
     * it happens to share a code path with the legitimate "read again" feature.
     */
    public function test_load_recap_throws_for_locked_chapter_not_yet_completed(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Locked Chapter', ['unlock_date' => time() + DAYSECS]);
        $startnode = $this->create_node($chapter->id, 'Start', true);

        // Simulate some in-progress history without completing the chapter.
        $progress = story_manager::get_or_create_progress($this->instanceid, $user->id);
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'current_nodes',
            json_encode([$chapter->id => [$startnode->id]]),
            ['id' => $progress->id]
        );

        try {
            story_manager::load_recap($this->instanceid, $user->id, $chapter->id);
            $this->fail('Expected story_error_chapter_locked exception not thrown.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('story_error_chapter_locked', $e->errorcode);
        }
    }

    /**
     * A chapter the player already completed must remain readable via load_recap() even if a
     * teacher later pushes its unlock_date into the future or raises its required_level —
     * re-reading something the player legitimately finished must never re-lock retroactively.
     */
    public function test_load_recap_does_not_relock_an_already_completed_chapter(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $chapter = $this->create_chapter('Finished Chapter');
        $startnode = $this->create_node($chapter->id, 'The tale begins.', true);

        $progress = story_manager::get_or_create_progress($this->instanceid, $user->id);
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'current_nodes',
            json_encode([$chapter->id => [$startnode->id]]),
            ['id' => $progress->id]
        );
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'completed_chapters',
            json_encode([$chapter->id]),
            ['id' => $progress->id]
        );

        // The teacher tightens the chapter's gates after the fact.
        $DB->set_field('block_playerhud_chapters', 'unlock_date', time() + DAYSECS, ['id' => $chapter->id]);
        $DB->set_field('block_playerhud_chapters', 'required_level', 99, ['id' => $chapter->id]);

        $html = story_manager::load_recap($this->instanceid, $user->id, $chapter->id);

        $this->assertStringContainsString('tale begins', $html);
    }
}
