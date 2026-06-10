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
}
