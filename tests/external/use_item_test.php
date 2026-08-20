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
 * Tests for the use_item web service (deadline_extension power).
 *
 * Tests that require local_latepenalty are guarded by class_exists and skipped
 * automatically when the plugin is not installed. With local_latepenalty present
 * (as configured in ci.yml via add-plugin) all guards are lifted and the full
 * deadline_extension path is exercised.
 *
 * Happy-path tests run as admin (the default after setAdminUser() in setUp).
 * The ownership-rejection test switches to a student user to verify the
 * inventory guard.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for use_item web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\use_item
 */
final class use_item_test extends external_base_testcase {
    #[\Override]
    protected function setUp(): void {
        global $DB;

        parent::setUp();

        if (class_exists('\local_latepenalty\recalculator')) {
            $DB->delete_records('local_latepenalty_overrides', []);
        }
    }

    /**
     * A user without block/playerhud:interact must not be able to use an item.
     */
    public function test_use_item_requires_interact_capability(): void {
        $item = $this->create_deadline_item();

        $student = $this->create_student_without_interact();
        $this->give_item_to_user((int) $student->id, $item->id);
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        use_item::execute($this->instanceid, $this->course->id, $item->id, 0);
    }

    /**
     * A user who does not own the item receives itemnotfound exception.
     * The student is set as current user without any inventory record.
     */
    public function test_use_item_not_owned_throws(): void {
        $item    = $this->create_deadline_item();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        use_item::execute($this->instanceid, $this->course->id, $item->id, 0);
    }

    /**
     * Equipping an avatar item must succeed even when $OUTPUT has not yet been resolved
     * to a real renderer — the exact state at the start of every AJAX web service
     * dispatch, since lib/ajax/service.php never calls
     * $PAGE->initialise_theme_and_output() before execute() runs. Regression test for a
     * TypeError where get_avatar_html()'s \renderer_base type hint rejected the raw
     * \core\output\bootstrap_renderer placeholder that $OUTPUT still holds at that point.
     */
    public function test_use_item_equips_avatar_before_output_is_initialised(): void {
        global $OUTPUT, $USER;

        $item = $this->create_item($this->instanceid, 'Test Avatar', ['action_type' => 'avatar_profile']);
        $this->give_item_to_user((int) $USER->id, $item->id);

        $OUTPUT = new \core\output\bootstrap_renderer();

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, 0);

        $this->assertTrue($result['success'], 'use_item failed: ' . $result['message']);
        $this->assertEquals('avatar_profile', $result['action']);
        $this->assertTrue($result['equipped']);
        $this->assertNotEmpty($result['avatar_html']);
    }

    /**
     * Unequipping an avatar item (the $OUTPUT->user_picture() branch) must equally
     * survive an unresolved $OUTPUT at call time. Same precondition and rationale as
     * {@see test_use_item_equips_avatar_before_output_is_initialised()}.
     */
    public function test_use_item_unequips_avatar_before_output_is_initialised(): void {
        global $OUTPUT, $USER;

        $item = $this->create_item($this->instanceid, 'Test Avatar', ['action_type' => 'avatar_profile']);
        $this->give_item_to_user((int) $USER->id, $item->id);
        set_user_preference('block_playerhud_avatar_' . $this->instanceid, $item->id);

        $OUTPUT = new \core\output\bootstrap_renderer();

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, 0);

        $this->assertTrue($result['success'], 'use_item failed: ' . $result['message']);
        $this->assertFalse($result['equipped']);
        $this->assertNotEmpty($result['avatar_html']);
    }

    /**
     * Regression test: an avatar granted via external_items::grant() (block_playerhud_stack,
     * the storage every new grant uses from now on) is recognised as owned, not just one
     * granted the legacy way via give_item_to_user()/block_playerhud_inventory directly. Before
     * use_item switched to external_items::get_available_quantity(), this ownership check read
     * block_playerhud_inventory only and would have wrongly thrown itemnotfound here.
     */
    public function test_use_item_equips_avatar_granted_via_new_storage(): void {
        global $USER;

        $item = $this->create_item($this->instanceid, 'Test Avatar', ['action_type' => 'avatar_profile']);
        $granted = \block_playerhud\local\external_items::grant(
            $this->instanceid,
            $item->id,
            (int) $USER->id,
            1,
            'quest',
            true
        );
        $this->assertGreaterThan(0, $granted, 'Setup failed: grant() did not record the item.');

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, 0);

        $this->assertTrue($result['success'], 'use_item failed: ' . $result['message']);
        $this->assertTrue($result['equipped']);
    }

    /**
     * When no cmid is resolvable use_item returns pick_activity message.
     * Runs as admin (the default after setUp::setAdminUser).
     */
    public function test_use_item_deadline_no_cmid_returns_pick_activity(): void {
        global $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $item = $this->create_deadline_item(1, 0);
        $this->give_item_to_user((int) $USER->id, $item->id);

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, 0);

        $this->assertFalse($result['success']);
        $this->assertEquals('deadline_extension', $result['action']);
        $this->assertEquals(
            get_string('item_use_pick_activity', 'block_playerhud'),
            $result['message']
        );
    }

    /**
     * When the target CM has no active latepenalty rule use_item returns lp_warning.
     * Runs as admin.
     */
    public function test_use_item_deadline_no_rule_returns_warning(): void {
        global $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $item   = $this->create_deadline_item(1, 0);
        $this->give_item_to_user((int) $USER->id, $item->id);

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, $assign->cmid);

        $this->assertFalse($result['success']);
        $this->assertEquals(
            get_string('item_lp_warning', 'block_playerhud'),
            $result['message']
        );
    }

    /**
     * Happy path: creates an override and marks the item as consumed.
     * Runs as admin.
     */
    public function test_use_item_deadline_creates_override_and_consumes_item(): void {
        global $DB, $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $duedate = time() + DAYSECS;
        $assign  = $this->getDataGenerator()->create_module('assign', [
            'course'  => $this->course->id,
            'duedate' => $duedate,
        ]);
        $this->create_lp_rule($assign->cmid);
        $item  = $this->create_deadline_item(2, 0);
        $invid = $this->give_item_to_user((int) $USER->id, $item->id);

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, $assign->cmid);

        $this->assertTrue($result['success'], 'use_item failed: ' . $result['message']);
        $this->assertEquals('deadline_extension', $result['action']);
        $this->assertNotEmpty($result['new_deadline']);

        // Override must be created with deadline extended by 2 days.
        $override = $DB->get_record('local_latepenalty_overrides', [
            'cmid'   => $assign->cmid,
            'userid' => (int) $USER->id,
        ]);
        $this->assertNotEmpty($override);
        $this->assertEquals($duedate + (2 * DAYSECS), (int) $override->deadline);

        // Inventory item must be marked as consumed.
        $inv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);
        $this->assertEquals('consumed', $inv->source);
    }

    /**
     * When an override already exists it is updated in place — no duplicate created.
     * Runs as admin.
     */
    public function test_use_item_deadline_updates_existing_override(): void {
        global $DB, $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $assign  = $this->getDataGenerator()->create_module('assign', [
            'course'  => $this->course->id,
            'duedate' => time() + DAYSECS,
        ]);
        $this->create_lp_rule($assign->cmid);
        $base = time() + (3 * DAYSECS);
        $this->create_lp_override($assign->cmid, (int) $USER->id, $base);

        $item = $this->create_deadline_item(1, 0);
        $this->give_item_to_user((int) $USER->id, $item->id);

        $result = use_item::execute($this->instanceid, $this->course->id, $item->id, $assign->cmid);

        $this->assertTrue($result['success'], 'use_item failed: ' . $result['message']);

        $overrides = $DB->get_records('local_latepenalty_overrides', [
            'cmid'   => $assign->cmid,
            'userid' => (int) $USER->id,
        ]);
        $this->assertCount(1, $overrides, 'No duplicate override must be created.');
        $this->assertEquals($base + DAYSECS, (int) reset($overrides)->deadline);
    }

    /**
     * Regression test for the security-audit race-condition finding: the deadline_extension
     * branch now consumes the item atomically via external_items::consume() before writing the
     * override, instead of a non-atomic read-modify-write. This test cannot reproduce the
     * original race itself (a single-process PHPUnit test shares one DB session, and the
     * plugin's postgres advisory locks are reentrant within a session, so two same-process
     * calls never actually contend for the lock the way two real concurrent requests do — the
     * same structural limitation already documented for story_manager::make_choice()). What it
     * does prove: two genuinely legitimate sequential uses, each backed by its own inventory
     * unit, still work correctly under the new atomic gate — each call consumes a distinct row
     * and the deadline accumulates by exactly one day-block per call, with no double-extension
     * from either call individually. The actual race closure is verified live via real
     * concurrent HTTP requests, not here.
     */
    public function test_use_item_deadline_two_sequential_uses_each_consume_a_distinct_unit(): void {
        global $DB, $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $duedate = time() + DAYSECS;
        $assign  = $this->getDataGenerator()->create_module('assign', [
            'course'  => $this->course->id,
            'duedate' => $duedate,
        ]);
        $this->create_lp_rule($assign->cmid);
        $item   = $this->create_deadline_item(2, 0);
        $invid1 = $this->give_item_to_user((int) $USER->id, $item->id);
        $invid2 = $this->give_item_to_user((int) $USER->id, $item->id);

        $result1 = use_item::execute($this->instanceid, $this->course->id, $item->id, $assign->cmid);
        $this->assertTrue($result1['success'], 'First use_item call failed: ' . $result1['message']);

        $result2 = use_item::execute($this->instanceid, $this->course->id, $item->id, $assign->cmid);
        $this->assertTrue($result2['success'], 'Second use_item call failed: ' . $result2['message']);

        $override = $DB->get_record('local_latepenalty_overrides', [
            'cmid'   => $assign->cmid,
            'userid' => (int) $USER->id,
        ]);
        $this->assertEquals(
            $duedate + (2 * 2 * DAYSECS),
            (int) $override->deadline,
            'Two calls with two separate items must extend by exactly 2 days each, not stack unevenly.'
        );

        // Both distinct inventory rows must be consumed — never the same row twice.
        $this->assertEquals('consumed', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid1]));
        $this->assertEquals('consumed', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid2]));
    }

    /**
     * Regression test for the security-audit finding: a targetcmid outside this block's own
     * course must be rejected even when an override already exists for that cmid+userid pair
     * (e.g. set up through a deadline-extension item in a different course). Before the fix,
     * an existing override skipped the get_fast_modinfo($courseid)->get_cm($cmid) check that
     * would otherwise reject a foreign cmid.
     */
    public function test_use_item_deadline_rejects_foreign_course_cmid_with_existing_override(): void {
        global $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        // A second, unrelated course with its own late-penalty-enabled activity.
        $othercourse = $this->getDataGenerator()->create_course();
        $foreignassign = $this->getDataGenerator()->create_module('assign', [
            'course'  => $othercourse->id,
            'duedate' => time() + DAYSECS,
        ]);
        $this->create_lp_rule($foreignassign->cmid);
        // An override already exists for this user on the FOREIGN course's cmid.
        $this->create_lp_override($foreignassign->cmid, (int) $USER->id, time() + (3 * DAYSECS));

        $item = $this->create_deadline_item(1, 0);
        $this->give_item_to_user((int) $USER->id, $item->id);

        // Execute against THIS block's own course, but targeting the foreign cmid.
        $this->expectException(\moodle_exception::class);
        use_item::execute($this->instanceid, $this->course->id, $item->id, $foreignassign->cmid);
    }

    // Helpers.

    /**
     * Create a deadline_extension item and return it.
     *
     * @param int $days Extension days encoded in action_value.
     * @param int $cmid Fixed CM (0 = user must pick at runtime).
     * @return \stdClass
     */
    private function create_deadline_item(int $days = 1, int $cmid = 0): \stdClass {
        return $this->create_item($this->instanceid, 'Extension Pass', [
            'image'        => '⏰',
            'tradable'     => 0,
            'action_type'  => 'deadline_extension',
            'action_value' => json_encode(['days' => $days, 'cmid' => $cmid]),
        ]);
    }

    /**
     * Enable the latepenalty rule that local_latepenalty_coursemodule_edit_post_actions
     * automatically creates (with enabled=0) when create_module runs.
     *
     * We never INSERT here to avoid duplicate-key conflicts on the unique cmid index.
     *
     * @param int $cmid Target course module ID.
     * @return \stdClass
     */
    private function create_lp_rule(int $cmid): \stdClass {
        global $DB;
        $rule = $DB->get_record('local_latepenalty_rules', ['cmid' => $cmid], '*', MUST_EXIST);
        $rule->enabled       = 1;
        $rule->daily_penalty = 10.0;
        $rule->max_penalty   = 100.0;
        $DB->update_record('local_latepenalty_rules', $rule);
        return $rule;
    }

    /**
     * Insert a latepenalty override with a specific deadline.
     *
     * @param int $cmid Target course module ID.
     * @param int $userid Target user ID.
     * @param int $deadline Unix timestamp.
     * @return \stdClass
     */
    private function create_lp_override(int $cmid, int $userid, int $deadline): \stdClass {
        global $DB;
        $override = (object) [
            'cmid'          => $cmid,
            'userid'        => $userid,
            'deadline'      => $deadline,
            'daily_penalty' => null,
            'max_penalty'   => null,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $override->id = $DB->insert_record('local_latepenalty_overrides', $override);
        return $override;
    }

    /**
     * Regression test for the security-audit finding: a cmid outside the block's own course
     * must be rejected before the local_latepenalty_rules lookup runs, not after. Before the
     * fix, a foreign cmid with no rule returned the item_lp_warning JSON (success=false)
     * instead of throwing — the ownership check only ran afterwards, so its outcome ("no rule
     * here") was observable for any cmid site-wide without ever confirming ownership. A foreign
     * cmid must always be rejected the same way regardless of whether a rule exists for it.
     */
    public function test_use_item_rejects_a_foreign_cmid_before_probing_the_latepenalty_rule(): void {
        global $USER;

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }

        $othercourse = $this->getDataGenerator()->create_course();
        $foreignassign = $this->getDataGenerator()->create_module('assign', ['course' => $othercourse->id]);

        $item = $this->create_deadline_item(1, 0);
        $this->give_item_to_user((int) $USER->id, $item->id);

        $this->expectException(\moodle_exception::class);
        use_item::execute($this->instanceid, $this->course->id, $item->id, $foreignassign->cmid);
    }
}
