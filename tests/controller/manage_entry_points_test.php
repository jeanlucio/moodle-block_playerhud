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
 * Tests for the controller entry points reached directly by a page request.
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
 * Covers the HTTP-facing halves of the management controllers — the parts that read request
 * parameters, enforce sesskey/capability, mutate data and then redirect, plus the render path
 * that turns the result into a page.
 *
 * These methods run under the real request lifecycle rather than being handed clean arguments,
 * so they are exercised here by populating the superglobals the way a browser would. A
 * `redirect()` raises `redirecterrordetected` under CLI (which PHPUnit always is), so the
 * post-conditions are asserted after catching that exception — the mutation always happens
 * before the redirect in these controllers.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\controller\drops
 * @covers     \block_playerhud\controller\scenes
 * @covers     \block_playerhud\controller\collect
 * @covers     \block_playerhud\controller\classes
 * @covers     \block_playerhud\controller\trades
 */
final class manage_entry_points_test extends advanced_testcase {
    /** @var stdClass The shared course. */
    private stdClass $course;

    /** @var int The shared block instance ID. */
    private int $instanceid;

    /**
     * Start every test from a clean request state with a course and block instance in place.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $_POST = [];
        $_GET  = [];

        $this->course     = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->make_instance($this->course);
    }

    /**
     * Leave no request state behind for the next test.
     */
    protected function tearDown(): void {
        $_POST = [];
        $_GET  = [];
        parent::tearDown();
    }

    /**
     * Creates a PlayerHUD block instance on the given course.
     *
     * @param stdClass $course The owning course.
     * @return int The new block instance ID.
     */
    private function make_instance(stdClass $course): int {
        global $DB;

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
     * Creates an item owned by a block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param string $name Item name.
     * @param int $xp XP the item is worth.
     * @return int The new item ID.
     */
    private function make_item(int $instanceid, string $name = 'Item', int $xp = 0): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => $name,
            'xp'              => $xp,
            'enabled'         => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates a drop for an item.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $itemid Item the drop belongs to.
     * @param string $name Drop name.
     * @param int $maxusage Collection limit (0 = infinite).
     * @return int The new drop ID.
     */
    private function make_drop(int $instanceid, int $itemid, string $name = 'Spot', int $maxusage = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid'          => $itemid,
            'name'            => $name,
            'maxusage'        => $maxusage,
            'respawntime'     => 0,
            'code'            => \block_playerhud\utils::generate_drop_code($instanceid),
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates a story chapter.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new chapter ID.
     */
    private function make_chapter(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => 'Chapter',
            'intro_text'      => '',
            'unlock_date'     => 0,
            'required_level'  => 0,
            'sortorder'       => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates a story node with one choice hanging off it.
     *
     * @param int $chapterid Owning chapter ID.
     * @param string $content Node text.
     * @return array{0: int, 1: int} The node ID and its choice ID.
     */
    private function make_node_with_choice(int $chapterid, string $content = 'A crossroads'): array {
        global $DB;

        $nodeid = (int) $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid'    => $chapterid,
            'content'      => $content,
            'is_start'     => 1,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        $choiceid = (int) $DB->insert_record('block_playerhud_choices', (object) [
            'nodeid'        => $nodeid,
            'text'          => 'Go left',
            'next_nodeid'   => 0,
            'req_class_id'  => 0,
            'req_karma_min' => 0,
            'karma_delta'   => 0,
            'set_class_id'  => 0,
            'cost_itemid'   => 0,
            'cost_item_qty' => 1,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        return [$nodeid, $choiceid];
    }

    /**
     * Enrols a fresh student in the shared course and logs them in.
     *
     * @return stdClass The student user record.
     */
    private function login_as_student(): stdClass {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        return $student;
    }

    /**
     * Runs a controller callable that is expected to end in a redirect, and asserts it did.
     *
     * @param callable $action The controller call to run.
     */
    private function assert_redirects(callable $action): void {
        try {
            $action();
            $this->fail('Expected the controller to end in a redirect.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode);
        }
    }

    // Drops — delete and bulk-delete actions.

    /**
     * The delete action removes the drop before redirecting back to the listing.
     */
    public function test_drops_delete_action_removes_the_drop(): void {
        global $DB;

        $this->setAdminUser();
        $itemid = $this->make_item($this->instanceid);
        $dropid = $this->make_drop($this->instanceid, $itemid);

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
            'action'     => 'delete',
            'dropid'     => $dropid,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new drops())->view_manage_page());

        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
    }

    /**
     * A drop id belonging to another block instance is never deleted, even though the caller
     * holds the manage capability on their own instance — the id is filtered through its own
     * item's blockinstanceid, not trusted as given.
     */
    public function test_drops_delete_action_ignores_a_foreign_instance_drop(): void {
        global $DB;

        $this->setAdminUser();
        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $otheritem     = $this->make_item($otherinstance);
        $foreigndrop   = $this->make_drop($otherinstance, $otheritem);

        $ownitem = $this->make_item($this->instanceid);

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $ownitem,
            'action'     => 'delete',
            'dropid'     => $foreigndrop,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new drops())->view_manage_page());

        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $foreigndrop]));
    }

    /**
     * The bulk-delete action removes every selected drop that belongs to the instance.
     */
    public function test_drops_bulk_delete_removes_the_selected_drops(): void {
        global $DB;

        $this->setAdminUser();
        $itemid = $this->make_item($this->instanceid);
        $first  = $this->make_drop($this->instanceid, $itemid, 'First');
        $second = $this->make_drop($this->instanceid, $itemid, 'Second');
        $kept   = $this->make_drop($this->instanceid, $itemid, 'Kept');

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
            'action'     => 'bulk_delete',
            'bulk_ids'   => [$first, $second],
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new drops())->view_manage_page());

        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $first]));
        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $second]));
        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $kept]));
    }

    /**
     * A wrong sesskey leaves the drop alone: confirm_sesskey() gates the action branch, so the
     * request falls through to a plain listing render instead of deleting anything.
     */
    public function test_drops_delete_action_with_a_wrong_sesskey_deletes_nothing(): void {
        global $DB;

        $this->setAdminUser();
        $itemid = $this->make_item($this->instanceid);
        $dropid = $this->make_drop($this->instanceid, $itemid);

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
            'action'     => 'delete',
            'dropid'     => $dropid,
            'sesskey'    => 'not-the-real-sesskey',
        ];

        (new drops())->view_manage_page();

        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
    }

    /**
     * A student without block/playerhud:manage cannot reach the drops management page at all.
     */
    public function test_drops_page_requires_the_manage_capability(): void {
        $itemid = $this->make_item($this->instanceid);
        $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
        ];

        $this->expectException(\required_capability_exception::class);
        (new drops())->view_manage_page();
    }

    // Drops — listing render.

    /**
     * The rendered listing shows the instance's own drops and never leaks another instance's.
     */
    public function test_drops_listing_renders_own_drops_only(): void {
        $this->setAdminUser();
        $itemid = $this->make_item($this->instanceid);
        $this->make_drop($this->instanceid, $itemid, 'Library Corner');

        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $otheritem     = $this->make_item($otherinstance);
        $this->make_drop($otherinstance, $otheritem, 'Foreign Vault');

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
        ];

        $html = (new drops())->view_manage_page();

        $this->assertStringContainsString('Library Corner', $html);
        $this->assertStringNotContainsString('Foreign Vault', $html);
    }

    /**
     * An unknown sort column falls back to the default instead of reaching the query, so a
     * crafted `sort` parameter can never be interpolated into the ORDER BY clause.
     */
    public function test_drops_listing_rejects_an_unknown_sort_column(): void {
        $this->setAdminUser();
        $itemid = $this->make_item($this->instanceid);
        $this->make_drop($this->instanceid, $itemid, 'Sorted Spot');

        $_POST = [
            'instanceid' => $this->instanceid,
            'id'         => $this->course->id,
            'itemid'     => $itemid,
            'sort'       => 'evilcolumn',
            'dir'        => 'nonsense',
        ];

        $html = (new drops())->view_manage_page();

        $this->assertStringContainsString('Sorted Spot', $html);
    }

    // Scenes — delete action.

    /**
     * Deleting a scene removes the node and cascades to the choices hanging off it.
     */
    public function test_scenes_delete_removes_the_node_and_its_choices(): void {
        global $DB;

        $this->setAdminUser();
        $chapterid = $this->make_chapter($this->instanceid);
        [$nodeid, $choiceid] = $this->make_node_with_choice($chapterid);

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'chapterid'  => $chapterid,
            'action'     => 'delete_scene',
            'nodeid'     => $nodeid,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new scenes())->view_manage_page());

        $this->assertFalse($DB->record_exists('block_playerhud_story_nodes', ['id' => $nodeid]));
        $this->assertFalse($DB->record_exists('block_playerhud_choices', ['id' => $choiceid]));
    }

    /**
     * A node id from a different chapter is left untouched — the lookup is scoped by chapterid,
     * so a crafted nodeid cannot delete a scene the page is not managing.
     */
    public function test_scenes_delete_ignores_a_node_from_another_chapter(): void {
        global $DB;

        $this->setAdminUser();
        $chapterid      = $this->make_chapter($this->instanceid);
        $otherchapterid = $this->make_chapter($this->instanceid);
        [$foreignnode]  = $this->make_node_with_choice($otherchapterid);

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'chapterid'  => $chapterid,
            'action'     => 'delete_scene',
            'nodeid'     => $foreignnode,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new scenes())->view_manage_page());

        $this->assertTrue($DB->record_exists('block_playerhud_story_nodes', ['id' => $foreignnode]));
    }

    /**
     * A chapter belonging to another block instance is rejected outright, before any action.
     */
    public function test_scenes_page_rejects_a_chapter_from_another_instance(): void {
        $this->setAdminUser();
        $othercourse    = $this->getDataGenerator()->create_course();
        $otherinstance  = $this->make_instance($othercourse);
        $foreignchapter = $this->make_chapter($otherinstance);

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'chapterid'  => $foreignchapter,
        ];

        $this->expectException(\dml_missing_record_exception::class);
        (new scenes())->view_manage_page();
    }

    /**
     * A student without block/playerhud:manage cannot reach the scenes management page.
     */
    public function test_scenes_page_requires_the_manage_capability(): void {
        $chapterid = $this->make_chapter($this->instanceid);
        $this->login_as_student();

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'chapterid'  => $chapterid,
        ];

        $this->expectException(\required_capability_exception::class);
        (new scenes())->view_manage_page();
    }

    /**
     * Regression test for the security-audit DoS finding: 'repeats' is client-supplied and
     * directly drove two unbounded loops (the add_choice_btn re-render branch, reachable via a
     * plain GET with no sesskey, and the real-submission branch) — each iteration reading 8
     * optional_param() values. clamp_repeats() must cap both at MAX_REPEATS regardless of how
     * large the request value is.
     */
    public function test_scenes_clamp_repeats_caps_an_oversized_value(): void {
        $method = new \ReflectionMethod(scenes::class, 'clamp_repeats');
        $method->setAccessible(true);

        $this->assertSame(scenes::MAX_REPEATS, $method->invoke(null, 99999999));
        $this->assertSame(5, $method->invoke(null, 5));
        $this->assertSame(scenes::MAX_REPEATS, $method->invoke(null, scenes::MAX_REPEATS));
    }

    // Scenes — edit form save path.

    /**
     * Regression test mirroring the item-description incident: pasting the visible text of an
     * already-open item modal into a scene's content editor drags along its DOM skeleton. The
     * stored node content must come out as only the real text, with the nested
     * <div class="modal">...</div> structure and its yui_* ids stripped by
     * utils::sanitize_rich_description() before scenes::handle_edit_form() persists it.
     */
    public function test_scenes_edit_form_sanitizes_pasted_modal_skeleton_in_content(): void {
        global $DB;

        $this->setAdminUser();
        $chapterid = $this->make_chapter($this->instanceid);

        $poisoned = '<div id="ph-item-modal-view" class="modal fade ph-modal-zindex ph-item-modal show">'
            . '<div id="yui_3_18_1_1_1786538342166_237" class="modal-content border-0 shadow-lg">'
            . '<div id="phModalDescView" class="text-muted text-break mb-3">'
            . '<p id="yui_3_18_1_1_1786538342166_242">Voce acorda em uma cela escura e umida.</p>'
            . '</div></div></div>';

        $formidentifier = str_replace('\\', '_', \block_playerhud\form\edit_scene_form::class);
        $_POST = [
            'courseid'                => $this->course->id,
            'instanceid'              => $this->instanceid,
            'chapterid'               => $chapterid,
            'nodeid'                  => 0,
            'content'                 => ['text' => $poisoned, 'format' => FORMAT_HTML],
            'is_start'                => 1,
            'sesskey'                 => sesskey(),
            '_qf__' . $formidentifier => 1,
        ];

        $this->assert_redirects(fn() => (new scenes())->handle_edit_form());

        $node = $DB->get_record('block_playerhud_story_nodes', ['chapterid' => $chapterid], '*', MUST_EXIST);
        $this->assertStringNotContainsString('<div', $node->content);
        $this->assertStringNotContainsString('modal', $node->content);
        $this->assertStringNotContainsString('yui_3_18_1_1', $node->content);
        $this->assertStringContainsString('Voce acorda em uma cela escura e umida.', $node->content);
    }

    /**
     * Ordinary WYSIWYG formatting must survive the save path unchanged.
     */
    public function test_scenes_edit_form_preserves_basic_formatting_in_content(): void {
        global $DB;

        $this->setAdminUser();
        $chapterid = $this->make_chapter($this->instanceid);
        $html = '<p>The door creaks open to reveal a <strong>hidden passage</strong>.</p>';

        $formidentifier = str_replace('\\', '_', \block_playerhud\form\edit_scene_form::class);
        $_POST = [
            'courseid'                => $this->course->id,
            'instanceid'              => $this->instanceid,
            'chapterid'               => $chapterid,
            'nodeid'                  => 0,
            'content'                 => ['text' => $html, 'format' => FORMAT_HTML],
            'is_start'                => 1,
            'sesskey'                 => sesskey(),
            '_qf__' . $formidentifier => 1,
        ];

        $this->assert_redirects(fn() => (new scenes())->handle_edit_form());

        $node = $DB->get_record('block_playerhud_story_nodes', ['chapterid' => $chapterid], '*', MUST_EXIST);
        $this->assertStringContainsString('<strong>hidden passage</strong>', $node->content);
    }

    // Collect — the non-AJAX pickup path.

    /**
     * A student collecting a finite drop gets the item and its XP, then is redirected back to
     * the course.
     */
    public function test_collect_awards_the_item_and_its_xp(): void {
        $itemid = $this->make_item($this->instanceid, 'Rare Gem', 50);
        $dropid = $this->make_drop($this->instanceid, $itemid);
        $student = $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'dropid'     => $dropid,
            'courseid'   => $this->course->id,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new collect())->execute());

        $this->assertTrue(\block_playerhud\game::has_item((int) $student->id, $itemid));
        $player = \block_playerhud\game::get_player($this->instanceid, (int) $student->id);
        $this->assertSame(50, (int) $player->currentxp);
    }

    /**
     * An infinite drop still hands over the item but pays no XP — the anti-farm rule, asserted
     * here through the real request path rather than against game::process_collection() directly.
     */
    public function test_collect_from_an_infinite_drop_pays_no_xp(): void {
        $itemid = $this->make_item($this->instanceid, 'Endless Berry', 30);
        $dropid = $this->make_drop($this->instanceid, $itemid, 'Bush', 0);
        $student = $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'dropid'     => $dropid,
            'courseid'   => $this->course->id,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new collect())->execute());

        $this->assertTrue(\block_playerhud\game::has_item((int) $student->id, $itemid));
        $player = \block_playerhud\game::get_player($this->instanceid, (int) $student->id);
        $this->assertSame(0, (int) $player->currentxp);
    }

    /**
     * A drop id owned by a different block instance yields nothing: the failure is reported
     * through the same redirect path, and no inventory row is created.
     */
    public function test_collect_rejects_a_drop_from_another_instance(): void {
        global $DB;

        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $otheritem     = $this->make_item($otherinstance, 'Foreign Relic', 99);
        $foreigndrop   = $this->make_drop($otherinstance, $otheritem);

        $student = $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'dropid'     => $foreigndrop,
            'courseid'   => $this->course->id,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new collect())->execute());

        $this->assertFalse($DB->record_exists('block_playerhud_inventory', ['userid' => $student->id]));
    }

    /**
     * A disabled item cannot be picked up even when its drop is otherwise valid.
     */
    public function test_collect_rejects_a_disabled_item(): void {
        global $DB;

        $itemid = $this->make_item($this->instanceid, 'Retired Coin', 10);
        $DB->set_field('block_playerhud_items', 'enabled', 0, ['id' => $itemid]);
        $dropid = $this->make_drop($this->instanceid, $itemid);
        $student = $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'dropid'     => $dropid,
            'courseid'   => $this->course->id,
            'sesskey'    => sesskey(),
        ];

        $this->assert_redirects(fn() => (new collect())->execute());

        $this->assertFalse($DB->record_exists('block_playerhud_inventory', ['userid' => $student->id]));
    }

    // Class and trade edit forms — the guards reached before any submission.

    /**
     * Opening the class editor for a class owned by another block instance is rejected, so a
     * crafted classid cannot be loaded into (and then saved through) this instance's form.
     */
    public function test_class_editor_rejects_a_class_from_another_instance(): void {
        global $DB;

        $this->setAdminUser();
        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $foreignclass  = (int) $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $otherinstance,
            'name'            => 'Foreign Archetype',
            'description'     => '',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'classid'    => $foreignclass,
        ];

        $this->expectException(\dml_missing_record_exception::class);
        (new classes())->handle_edit_form();
    }

    /**
     * A student without block/playerhud:manage cannot open the class editor.
     */
    public function test_class_editor_requires_the_manage_capability(): void {
        $this->login_as_student();

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
        ];

        $this->expectException(\required_capability_exception::class);
        (new classes())->handle_edit_form();
    }

    /**
     * Opening the trade editor for a trade owned by another block instance is rejected.
     */
    public function test_trade_editor_rejects_a_trade_from_another_instance(): void {
        global $DB;

        $this->setAdminUser();
        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $foreigntrade  = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $otherinstance,
            'name'            => 'Foreign Bargain',
            'groupid'         => 0,
            'centralized'     => 1,
            'onetime'         => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
            'tradeid'    => $foreigntrade,
        ];

        $this->expectException(\dml_missing_record_exception::class);
        (new trades())->handle_edit_form();
    }

    /**
     * A genuinely submitted request (the moodleform "_qf__" marker present, as a real POST
     * carries it) for a trade owned by another block instance must still be rejected before
     * trade_reqs/trade_rewards are ever read for it — otherwise their row counts leak into the
     * re-rendered form's repeats_req/repeats_give sizing as an oracle for a foreign trade's
     * requirement/reward counts, since form validation failing (no 'name' etc. supplied) would
     * fall through to render instead of throwing.
     */
    public function test_trade_editor_rejects_a_submitted_trade_from_another_instance(): void {
        global $DB;

        $this->setAdminUser();
        $othercourse   = $this->getDataGenerator()->create_course();
        $otherinstance = $this->make_instance($othercourse);
        $foreigntrade  = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $otherinstance,
            'name'            => 'Foreign Bargain',
            'groupid'         => 0,
            'centralized'     => 1,
            'onetime'         => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $foreigntrade, 'itemid' => $this->make_item($otherinstance), 'qty' => 1,
        ]);

        $formidentifier = str_replace('\\', '_', \block_playerhud\form\edit_trade_form::class);
        $_POST = [
            'courseid'                  => $this->course->id,
            'instanceid'                => $this->instanceid,
            'tradeid'                   => $foreigntrade,
            'sesskey'                   => sesskey(),
            '_qf__' . $formidentifier   => 1,
        ];

        $this->expectException(\dml_missing_record_exception::class);
        (new trades())->handle_edit_form();
    }

    /**
     * Regression test for the security-audit DoS finding: a plain GET (no submission, no
     * sesskey) with an oversized repeats_req/repeats_give must not make the form allocate an
     * unbounded number of MoodleQuickForm elements. Uses 200 (not the report's PoC value of
     * millions) so the pre-fix run stays fast enough to assert against directly.
     */
    public function test_trade_editor_clamps_an_oversized_repeats_counter(): void {
        $this->setAdminUser();

        $_GET = [
            'courseid'     => $this->course->id,
            'instanceid'   => $this->instanceid,
            'repeats_req'  => 200,
            'repeats_give' => 200,
        ];

        $html = (new trades())->handle_edit_form();

        $this->assertStringContainsString('req_itemid_49', $html, 'Up to the clamp must still render.');
        $this->assertStringNotContainsString('req_itemid_50', $html, 'Must not render past the clamp.');
        $this->assertStringContainsString('give_itemid_49', $html, 'Up to the clamp must still render.');
        $this->assertStringNotContainsString('give_itemid_50', $html, 'Must not render past the clamp.');
    }

    /**
     * A student without block/playerhud:manage cannot open the trade editor.
     */
    public function test_trade_editor_requires_the_manage_capability(): void {
        $this->login_as_student();

        $_POST = [
            'courseid'   => $this->course->id,
            'instanceid' => $this->instanceid,
        ];

        $this->expectException(\required_capability_exception::class);
        (new trades())->handle_edit_form();
    }

    /**
     * Collecting without a valid sesskey is rejected before anything is written.
     */
    public function test_collect_requires_a_valid_sesskey(): void {
        global $DB;

        $itemid = $this->make_item($this->instanceid, 'Guarded Gem', 10);
        $dropid = $this->make_drop($this->instanceid, $itemid);
        $student = $this->login_as_student();

        $_POST = [
            'instanceid' => $this->instanceid,
            'dropid'     => $dropid,
            'courseid'   => $this->course->id,
            'sesskey'    => 'not-the-real-sesskey',
        ];

        try {
            (new collect())->execute();
            $this->fail('Expected the sesskey check to reject the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsesskey', $e->errorcode);
        }

        $this->assertFalse($DB->record_exists('block_playerhud_inventory', ['userid' => $student->id]));
    }
}
