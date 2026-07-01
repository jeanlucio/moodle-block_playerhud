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
 * Tests for the wizard_generate web service (Missions module; no network involved).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;
use core_external\external_api;

/**
 * Tests for the wizard_generate web service.
 *
 * The Items & Trade module calls the AI generator and is exercised manually
 * (see .docs/plano-wizard-octalysis.md); these tests cover the Missions
 * module, which is fully deterministic and needs no network access.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_generate
 */
final class wizard_generate_test extends external_base_testcase {
    /**
     * Requesting only the Missions module creates quests and a rollback manifest,
     * without touching the item/drop tables.
     */
    public function test_missions_only_creates_quests_and_manifest(): void {
        global $DB;

        $this->create_item($this->instanceid, 'Sword');
        $this->create_item($this->instanceid, 'Shield');

        $result = wizard_generate::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['created_quests']);
        $this->assertSame([], $result['created_items']);

        $cleaned = external_api::clean_returnvalue(wizard_generate::execute_returns(), $result);
        $this->assertTrue($cleaned['success']);

        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]);
        $this->assertCount(count($result['created_quests']), $quests);

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $result['runid']], '*', MUST_EXIST);
        $this->assertSame('done', $run->status);
        $this->assertSame(['missions'], json_decode($run->modules, true));

        $manifest = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $result['runid']]);
        $this->assertCount(count($quests), $manifest);
        foreach ($manifest as $entry) {
            $this->assertSame('block_playerhud_quests', $entry->objecttable);
        }
    }

    /**
     * Rolling back a Missions-only run removes the created quests.
     */
    public function test_missions_only_run_can_be_rolled_back(): void {
        global $DB;

        $result = wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
        $this->assertTrue($result['success']);

        $deleted = \block_playerhud\local\wizard::rollback($result['runid'], $this->instanceid);

        $this->assertGreaterThan(0, $deleted);
        $this->assertEquals(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_wizard_generate_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_generate::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
    }
}
