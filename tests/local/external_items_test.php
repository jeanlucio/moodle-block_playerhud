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
 * Tests for the external_items API.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;
use stdClass;

/**
 * Tests for external_items — requires database.
 *
 * @package block_playerhud
 * @coversDefaultClass \block_playerhud\local\external_items
 */
final class external_items_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    private function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
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
     * Creates an item belonging to the given block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $enabled Enabled flag (1 or 0).
     * @return int The new item ID.
     */
    private function make_item(int $instanceid, int $enabled = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Gold Key',
            'xp'              => 0,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * An item belonging to the given block instance is recognised as such.
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_true_for_own_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);

        $this->assertTrue(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * A disabled item still belongs to its instance — belongs_to_instance() only checks
     * ownership, not the enabled flag (callers that care about enabled, e.g. grant(), check
     * that separately).
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_true_for_disabled_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 0);

        $this->assertTrue(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * An item belonging to a different block instance is rejected — the exact cross-course
     * leak scenario this class exists to prevent.
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_false_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item($otherinstanceid);

        $this->assertFalse(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * A nonexistent item ID is rejected.
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_false_for_nonexistent_item(): void {
        $instanceid = $this->make_instance();

        $this->assertFalse(external_items::belongs_to_instance(999999, $instanceid));
    }

    /**
     * Zero or negative IDs are rejected without querying the database.
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_false_for_zero_ids(): void {
        $this->assertFalse(external_items::belongs_to_instance(0, 0));
        $this->assertFalse(external_items::belongs_to_instance(-1, -1));
    }
}
