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

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the local_latepenalty compatibility bridge.
 *
 * Both assertions hold on every local_latepenalty release the block supports, so the same test
 * exercises whichever deadline API the installed release ships.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\latepenalty_bridge
 */
final class latepenalty_bridge_test extends advanced_testcase {
    /**
     * Skip when local_latepenalty is not installed, as the other integration tests do.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        if (!class_exists('\local_latepenalty\recalculator')) {
            $this->markTestSkipped('Requires local_latepenalty.');
        }
    }

    /**
     * Course module record in the shape the bridge expects (modname set).
     *
     * @param \stdClass $module Module record returned by the data generator.
     * @return \stdClass
     */
    private function cm_record(\stdClass $module): \stdClass {
        $cm = get_fast_modinfo($module->course)->get_cm($module->cmid);
        $record = $cm->get_course_module_record();
        $record->modname = $cm->modname;
        return $record;
    }

    /**
     * An activity with a due date reports it as the student's deadline.
     */
    public function test_get_deadline_returns_the_activity_due_date(): void {
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $duedate = time() + DAYSECS;
        $assign  = $this->getDataGenerator()->create_module('assign', [
            'course'  => $course->id,
            'duedate' => $duedate,
        ]);

        $this->assertSame($duedate, latepenalty_bridge::get_deadline($this->cm_record($assign), (int) $student->id));
    }

    /**
     * An activity with no due date has no deadline, instead of a zero timestamp.
     */
    public function test_get_deadline_returns_null_without_a_due_date(): void {
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign  = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $this->assertNull(latepenalty_bridge::get_deadline($this->cm_record($assign), (int) $student->id));
    }
}
