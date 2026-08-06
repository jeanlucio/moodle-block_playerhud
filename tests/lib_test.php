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
use context_block;
use context_course;
use core_user\output\myprofile\tree;
use stdClass;

/**
 * Unit tests for block_playerhud's lib.php global functions.
 *
 * block_playerhud_pluginfile()'s successful-serve branch (an existing file found and handed to
 * send_stored_file()) is deliberately left uncovered: send_stored_file() ends the script, the
 * same structural limit already documented for collect::execute()'s AJAX half. Every branch that
 * returns before reaching it is covered directly.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::block_playerhud_pluginfile
 * @covers ::block_playerhud_myprofile_navigation
 * @covers ::block_playerhud_get_drop_details_by_code
 * @covers ::block_playerhud_is_visible_for_class
 */
final class lib_test extends advanced_testcase {
    /** @var stdClass Shared course for all tests. */
    protected stdClass $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Resets the test environment, pulls in lib.php for direct calls and sets up a course
     * with a PlayerHUD block instance, running as admin (bypasses require_login/enrolment).
     */
    protected function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($this->course->id);
        $this->instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Forces $OUTPUT to a real renderer before a block_playerhud_myprofile_navigation() call:
     * the exact same bootstrap_renderer trap as use_item::execute() (see
     * external/use_item_test.php) — profile_content's own export_for_template() is
     * renderer_base-typed, and nothing else in this test is guaranteed to have touched
     * $OUTPUT first. Deliberately not done in setUp(): resolving the theme this early breaks
     * the pluginfile tests' own require_login(), which still needs to set the page's course.
     */
    private function resolve_real_renderer(): void {
        global $OUTPUT, $PAGE;
        $OUTPUT = $PAGE->get_renderer('core');
    }

    /**
     * Inserts a minimal enabled item and returns its ID.
     *
     * @param array $overrides Field overrides merged on top of sane defaults.
     * @return int The new item ID.
     */
    private function create_item(array $overrides = []): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_items', (object) array_merge([
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Test Item',
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'tradable'        => 1,
            'action_type'     => '',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ], $overrides));
    }

    // Tests for block_playerhud_pluginfile().

    /**
     * A non-block context (e.g. the course context) is rejected before touching the database.
     */
    public function test_pluginfile_rejects_non_block_context(): void {
        $coursecontext = context_course::instance($this->course->id);

        $result = block_playerhud_pluginfile(
            $this->course,
            new stdClass(),
            $coursecontext,
            'item_image',
            ['1'],
            false
        );

        $this->assertFalse($result);
    }

    /**
     * A file area outside the known item/class-portrait set is rejected.
     */
    public function test_pluginfile_rejects_unknown_filearea(): void {
        $blockcontext = context_block::instance($this->instanceid);

        $result = block_playerhud_pluginfile(
            $this->course,
            new stdClass(),
            $blockcontext,
            'not_a_real_area',
            ['1'],
            false
        );

        $this->assertFalse($result);
    }

    /**
     * A valid file area with no stored file for the given item id returns false instead of
     * calling send_stored_file() on nothing.
     */
    public function test_pluginfile_returns_false_when_no_file_exists(): void {
        $blockcontext = context_block::instance($this->instanceid);

        $result = block_playerhud_pluginfile(
            $this->course,
            new stdClass(),
            $blockcontext,
            'item_image',
            ['999'],
            false
        );

        $this->assertFalse($result);
    }

    // Tests for block_playerhud_myprofile_navigation().

    /**
     * A null course (viewing a profile outside any course context) is a no-op.
     */
    public function test_myprofile_navigation_noop_without_course(): void {
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, null);

        $this->assertEmpty($tree->categories);
    }

    /**
     * The site course (SITEID) never carries a PlayerHUD block instance of its own; a no-op.
     */
    public function test_myprofile_navigation_noop_on_site_course(): void {
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, (object) ['id' => SITEID]);

        $this->assertEmpty($tree->categories);
    }

    /**
     * A course with no PlayerHUD block instance at all is a no-op.
     */
    public function test_myprofile_navigation_noop_without_instance(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, $othercourse);

        $this->assertEmpty($tree->categories);
    }

    /**
     * A user with no player record yet (never visited the block) is a no-op.
     */
    public function test_myprofile_navigation_noop_without_player_record(): void {
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, $this->course);

        $this->assertEmpty($tree->categories);
    }

    /**
     * A player who opted out of gamification gets no profile section.
     */
    public function test_myprofile_navigation_noop_when_gamification_disabled(): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 0,
            'enable_gamification' => 0,
            'ranking_visibility'  => 1,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, $this->course);

        $this->assertEmpty($tree->categories);
    }

    /**
     * An opted-in player with at least one collected item gets the profile section added,
     * with hasitems true (exercising the AMD init branch).
     */
    public function test_myprofile_navigation_adds_section_for_active_player(): void {
        global $DB;

        $this->resolve_real_renderer();
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 1,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);
        $itemid = $this->create_item(['name' => 'Collected Sword']);
        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => 2,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'drop',
            'timecreated' => time(),
        ]);
        $tree = new tree();

        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], true, $this->course);

        $this->assertArrayHasKey('playerhud', $tree->categories);
        $this->assertArrayHasKey('playerhud', $tree->nodes);
    }

    /**
     * A regular student viewer (not admin) with the default capabilities intact still sees the
     * section. Regression coverage alongside the two denied-capability tests below: the new
     * guard must not accidentally block the common case for a real, unprivileged viewer.
     */
    public function test_myprofile_navigation_adds_section_for_a_regular_student_viewer(): void {
        global $DB;

        $this->resolve_real_renderer();
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 1,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'student');
        $this->setUser($viewer);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], false, $this->course);

        $this->assertArrayHasKey('playerhud', $tree->categories);
    }

    /**
     * A viewer without block/playerhud:view in the block context (denied by a role override)
     * gets no profile section, even for an active, opted-in player. Regression test for the
     * security-audit finding: the callback previously mounted profile_content for ANY viewer
     * able to open the profile page at all, without checking the block's own view capability.
     */
    public function test_myprofile_navigation_noop_when_view_capability_denied(): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 1,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'student');
        $blockcontext = context_block::instance($this->instanceid);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability('block/playerhud:view', CAP_PROHIBIT, $studentrole->id, $blockcontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], false, $this->course);

        $this->assertEmpty($tree->categories);
    }

    /**
     * A viewer without moodle/block:view in the block context (the block is effectively hidden
     * from their role) also gets no profile section — this generic core capability is what
     * governs whether a block instance is visible at all, independent of the plugin's own
     * :view capability.
     */
    public function test_myprofile_navigation_noop_when_block_view_capability_denied(): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 1,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'student');
        $blockcontext = context_block::instance($this->instanceid);
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability('moodle/block:view', CAP_PROHIBIT, $studentrole->id, $blockcontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewer);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], false, $this->course);

        $this->assertEmpty($tree->categories);
    }

    /**
     * Regression test for the security-audit finding: a student who opted out of the public
     * leaderboard (ranking_visibility = 0) must not still have their XP, level and inventory
     * exposed to other course participants through the profile page — the same opt-out
     * game::get_leaderboard() already respects.
     */
    public function test_myprofile_navigation_noop_when_ranking_visibility_hidden_from_other_student(): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 0,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'student');
        $this->setUser($viewer);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], false, $this->course);

        $this->assertEmpty($tree->categories);
    }

    /**
     * The owner viewing their own profile always sees their section, even after opting out of
     * the public leaderboard — the opt-out hides data from other participants, not from
     * themselves.
     */
    public function test_myprofile_navigation_shows_own_hidden_profile_to_owner(): void {
        global $DB;

        $owner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($owner->id, $this->course->id, 'student');
        $this->resolve_real_renderer();
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => $owner->id,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 0,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);
        $this->setUser($owner);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => $owner->id], true, $this->course);

        $this->assertArrayHasKey('playerhud', $tree->categories);
    }

    /**
     * A viewer with block/playerhud:manage (a teacher) still sees a hidden profile — teachers
     * are exempt from the opt-out, matching get_leaderboard()'s own $isteacher exemption.
     */
    public function test_myprofile_navigation_shows_hidden_profile_to_manager(): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid'     => $this->instanceid,
            'userid'              => 2,
            'currentxp'           => 30,
            'enable_gamification' => 1,
            'ranking_visibility'  => 0,
            'timecreated'         => time(),
            'timemodified'        => time(),
        ]);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $this->resolve_real_renderer();
        $this->setUser($teacher);

        $tree = new tree();
        block_playerhud_myprofile_navigation($tree, (object) ['id' => 2], false, $this->course);

        $this->assertArrayHasKey('playerhud', $tree->categories);
    }

    // Tests for block_playerhud_get_drop_details_by_code().

    /**
     * An existing, enabled drop's code resolves to its item details.
     */
    public function test_get_drop_details_by_code_returns_the_matching_drop(): void {
        global $DB;

        $itemid = $this->create_item(['name' => 'Ancient Relic', 'xp' => 15]);
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Location',
            'code'            => 'ABC123',
            'maxusage'        => 1,
            'respawntime'     => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $result = block_playerhud_get_drop_details_by_code('ABC123', $this->instanceid);

        $this->assertNotFalse($result);
        $this->assertSame('Ancient Relic', $result->itemname);
        $this->assertEquals(15, $result->xp);
        $this->assertEquals($itemid, $result->itemid);
    }

    /**
     * A code that does not exist at all returns false.
     */
    public function test_get_drop_details_by_code_unknown_code_returns_false(): void {
        $this->assertFalse(block_playerhud_get_drop_details_by_code('NOPE99', $this->instanceid));
    }

    /**
     * A real code scoped to a different block instance is rejected — the same
     * cross-instance isolation guarantee tested throughout this plugin.
     */
    public function test_get_drop_details_by_code_rejects_foreign_instance(): void {
        global $DB;

        $itemid = $this->create_item();
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Location',
            'code'            => 'XYZ789',
            'maxusage'        => 1,
            'respawntime'     => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $this->assertFalse(block_playerhud_get_drop_details_by_code('XYZ789', $this->instanceid + 1));
    }

    /**
     * A drop pointing at a disabled item is excluded, matching the 'i.enabled = 1' guard.
     */
    public function test_get_drop_details_by_code_excludes_disabled_item(): void {
        global $DB;

        $itemid = $this->create_item(['enabled' => 0]);
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Location',
            'code'            => 'OFF001',
            'maxusage'        => 1,
            'respawntime'     => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $this->assertFalse(block_playerhud_get_drop_details_by_code('OFF001', $this->instanceid));
    }

    // Tests for block_playerhud_is_visible_for_class().

    /**
     * An empty required-class value means public content, visible regardless of user class.
     */
    public function test_is_visible_for_class_empty_requirement_is_public(): void {
        $this->assertTrue(block_playerhud_is_visible_for_class('', 3));
    }

    /**
     * A literal '0' requirement also means public content.
     */
    public function test_is_visible_for_class_zero_requirement_is_public(): void {
        $this->assertTrue(block_playerhud_is_visible_for_class('0', 3));
    }

    /**
     * A user whose class id is in the comma-separated allow-list sees the content.
     */
    public function test_is_visible_for_class_matching_id_is_visible(): void {
        $this->assertTrue(block_playerhud_is_visible_for_class('1,2,3', 2));
    }

    /**
     * A user whose class id is absent from the allow-list does not see the content.
     */
    public function test_is_visible_for_class_non_matching_id_is_hidden(): void {
        $this->assertFalse(block_playerhud_is_visible_for_class('1,3', 2));
    }

    /**
     * A '0' present anywhere inside a non-empty list still means public, alongside the
     * explicitly listed classes.
     */
    public function test_is_visible_for_class_zero_inside_list_is_public(): void {
        $this->assertTrue(block_playerhud_is_visible_for_class('0,5', 9));
    }
}
