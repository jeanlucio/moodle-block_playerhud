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
 * Tests for the wizard_rollback web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;
use block_playerhud\local\wizard;
use core_external\external_api;

/**
 * Tests for the wizard_rollback web service — the WS entry point around
 * {@see \block_playerhud\local\wizard::rollback()}, whose own logic is already covered directly.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_rollback
 */
final class wizard_rollback_test extends external_base_testcase {
    /**
     * A finished run's generated item is deleted and the reported count matches the number
     * of objects the run recorded.
     */
    public function test_wizard_rollback_deletes_generated_objects(): void {
        global $DB, $USER;

        $itemid = $this->create_item($this->instanceid, 'Wizard Item')->id;
        $runid  = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);
        wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
        wizard::finish_run($runid, 'done');

        $result = wizard_rollback::execute($this->instanceid, $this->course->id, $runid);

        $cleaned = external_api::clean_returnvalue(wizard_rollback::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);
        $this->assertSame(1, $cleaned['deleted']);
        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $itemid]));
    }

    /**
     * A run ID belonging to a different block instance must never be rolled back through
     * this entry point — the underlying wizard::rollback() enforces this by instanceid.
     */
    public function test_wizard_rollback_rejects_mismatched_instance(): void {
        global $USER;

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);

        $this->expectException(\dml_missing_record_exception::class);
        wizard_rollback::execute($this->instanceid + 999, $this->course->id, $runid);
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_wizard_rollback_requires_manage_capability(): void {
        global $USER;

        $runid = wizard::start_run($this->instanceid, (int) $USER->id, ['items']);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_rollback::execute($this->instanceid, $this->course->id, $runid);
    }
}
