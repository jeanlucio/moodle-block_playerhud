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
 * Tests for the wizard_start web service.
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
 * Tests for the wizard_start web service — the live-progress step plan, no network involved
 * (this only builds the plan and creates the run row, it never runs a step).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_start
 */
final class wizard_start_test extends external_base_testcase {
    /**
     * The plan has one step per selected module, in the same order execute() runs them, and
     * `total` matches the plan's length.
     */
    public function test_plan_has_one_step_per_selected_module(): void {
        global $DB;

        $result = wizard_start::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            true,
            true,
            false
        );

        $cleaned = external_api::clean_returnvalue(wizard_start::execute_returns(), $result);
        $this->assertSame(2, $cleaned['total']);
        $this->assertSame(['missions', 'playercoin'], array_column($cleaned['steps'], 'type'));
        $this->assertNotSame('', $cleaned['steps'][0]['label']);

        $run = $DB->get_record('block_playerhud_wizard_runs', ['id' => $cleaned['runid']], '*', MUST_EXIST);
        $this->assertSame('running', $run->status);
        $this->assertSame(['missions', 'playercoin'], json_decode($run->modules, true));
    }

    /**
     * has_slow_step is true only when the AI story chapter module is selected — the browser
     * uses this to decide whether to show the "this can take a few minutes" warning.
     */
    public function test_has_slow_step_reflects_next_chapter_selection(): void {
        $without = wizard_start::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
        $this->assertFalse($without['has_slow_step']);

        $with = wizard_start::execute(
            $this->instanceid,
            $this->course->id,
            '',
            '',
            'short',
            false,
            false,
            false,
            false,
            false,
            'fantasy',
            false,
            false,
            true
        );
        $this->assertTrue($with['has_slow_step']);
    }

    /**
     * Selecting only Missions returns a non-empty mission_xp_shares array sized to the journey,
     * and an empty item_xp_shares array — the same shared-budget split execute() computes,
     * just handed back to the browser instead of consumed server-side in one call.
     */
    public function test_xp_shares_split_matches_selected_modules(): void {
        $result = wizard_start::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);

        $this->assertSame([], $result['item_xp_shares']);
        $this->assertCount(
            \block_playerhud\local\xp_budget::compute_mission_count('short'),
            $result['mission_xp_shares']
        );
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_wizard_start_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_start::execute($this->instanceid, $this->course->id, '', '', 'short', false, true);
    }
}
