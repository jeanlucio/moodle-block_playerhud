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
 * Tests for the wizard_list_runs web service.
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
 * Tests for the wizard_list_runs web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_list_runs
 */
final class wizard_list_runs_test extends external_base_testcase {
    /**
     * Starts a run and drives it through a single step, run to completion — reproducing what
     * the browser does for a one-mechanic wizard call, without going through wizard_start.
     *
     * @param string $steptype The step type identifier.
     * @param string $tonekey Narrative tone key.
     * @return int The run ID, already finished (status 'done').
     */
    private function run_single_step_to_completion(string $steptype, string $tonekey = 'fantasy'): int {
        $runid = \block_playerhud\local\wizard::start_run($this->instanceid, 2, [$steptype]);
        $result = wizard_run_step::execute(
            $this->instanceid,
            $this->course->id,
            $runid,
            $steptype,
            '',
            '',
            $tonekey,
            'short',
            [],
            [],
            [],
            true
        );
        $this->assertTrue($result['success']);

        return $runid;
    }

    /**
     * A completed run appears with a human-readable summary of what it created.
     */
    public function test_list_runs_returns_summary_for_active_run(): void {
        $runid = $this->run_single_step_to_completion('missions');

        $result = wizard_list_runs::execute($this->instanceid, $this->course->id);

        $cleaned = external_api::clean_returnvalue(wizard_list_runs::execute_returns(), $result);
        $this->assertCount(1, $cleaned['runs']);
        $this->assertSame($runid, $cleaned['runs'][0]['runid']);
        $this->assertStringContainsString(
            get_string('wizard_history_quests', 'block_playerhud'),
            $cleaned['runs'][0]['summary']
        );
        $this->assertNotEmpty($cleaned['runs'][0]['timecreated']);
    }

    /**
     * An RPG Classes run (no items/quests involved) still gets a non-empty summary,
     * covering the classes/chapters object tables specifically.
     */
    public function test_list_runs_summarises_rpg_run(): void {
        $this->run_single_step_to_completion('rpg');

        $result = wizard_list_runs::execute($this->instanceid, $this->course->id);

        $this->assertCount(1, $result['runs']);
        $summary = $result['runs'][0]['summary'];
        $this->assertNotSame('', $summary);
        $this->assertStringContainsString(get_string('wizard_history_classes', 'block_playerhud'), $summary);
        $this->assertStringContainsString(get_string('wizard_history_chapters', 'block_playerhud'), $summary);
    }

    /**
     * A rolled-back run no longer appears in the list.
     */
    public function test_list_runs_excludes_rolledback_runs(): void {
        $runid = $this->run_single_step_to_completion('missions');
        \block_playerhud\local\wizard::rollback($runid, $this->instanceid, $this->course->id);

        $result = wizard_list_runs::execute($this->instanceid, $this->course->id);

        $this->assertSame([], $result['runs']);
    }

    /**
     * A student without block/playerhud:manage must be rejected.
     */
    public function test_list_runs_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        wizard_list_runs::execute($this->instanceid, $this->course->id);
    }
}
