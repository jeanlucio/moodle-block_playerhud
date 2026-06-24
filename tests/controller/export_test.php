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
 * Tests for the export controller.
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
 * Tests for the export controller business logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\export
 */
final class export_test extends advanced_testcase {
    /** @var stdClass Course holding the block instance under test. */
    protected stdClass $course;

    /** @var int Block instance ID shared across test methods. */
    protected int $instanceid;

    /**
     * Creates a course and a block instance carrying the given level settings.
     *
     * @param int $xpperlevel XP required to advance one level.
     * @param int $maxlevels Level cap.
     * @return void
     */
    protected function make_instance(int $xpperlevel = 100, int $maxlevels = 20): void {
        global $DB;

        $this->course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($this->course->id);

        $config = (object) ['xp_per_level' => $xpperlevel, 'max_levels' => $maxlevels];

        $this->instanceid = $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize($config)),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Seeds a player progress row with the given XP for the block instance.
     *
     * @param int $userid User ID.
     * @param int $xp Current XP.
     * @return void
     */
    protected function seed_player(int $userid, int $xp): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $userid,
            'currentxp'       => $xp,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates an item belonging to the block instance.
     *
     * @return int The new item ID.
     */
    protected function make_item(): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Sword',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Grants an item to a user with the given source.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID.
     * @param string $source Inventory source (e.g. map, revoked, consumed).
     * @return void
     */
    protected function give_item(int $userid, int $itemid, string $source = 'map'): void {
        global $DB;

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $userid,
            'itemid'      => $itemid,
            'source'      => $source,
            'timecreated' => time(),
        ]);
    }

    /**
     * A student row carries the name, email, derived level, XP and live item count.
     *
     * @covers ::build_export
     */
    public function test_build_export_row_fields_and_level(): void {
        $this->resetAfterTest();
        $this->make_instance(100, 20);

        $student = $this->getDataGenerator()->create_user([
            'firstname' => 'Ana',
            'lastname'  => 'Silva',
            'email'     => 'ana@example.com',
        ]);
        $this->seed_player($student->id, 250);

        $itemid = $this->make_item();
        $this->give_item($student->id, $itemid);
        $this->give_item($student->id, $itemid);
        $this->give_item($student->id, $itemid, 'revoked');

        [, $rows] = (new export())->build_export($this->course->id, $this->instanceid);

        $this->assertCount(1, $rows);
        // Level = floor(250 / 100) + 1 = 3; revoked item is not counted.
        $this->assertSame(['Ana', 'Silva', 'ana@example.com', 3, 250, 2], $rows[0]);
    }

    /**
     * Rows are ordered by XP descending.
     *
     * @covers ::build_export
     */
    public function test_build_export_orders_by_xp_descending(): void {
        $this->resetAfterTest();
        $this->make_instance();

        $low = $this->getDataGenerator()->create_user(['email' => 'low@example.com']);
        $high = $this->getDataGenerator()->create_user(['email' => 'high@example.com']);
        $this->seed_player($low->id, 100);
        $this->seed_player($high->id, 500);

        [, $rows] = (new export())->build_export($this->course->id, $this->instanceid);

        $this->assertCount(2, $rows);
        $this->assertSame('high@example.com', $rows[0][2]);
        $this->assertSame('low@example.com', $rows[1][2]);
    }

    /**
     * The derived level never exceeds the configured maximum.
     *
     * @covers ::build_export
     */
    public function test_build_export_caps_level_at_max(): void {
        $this->resetAfterTest();
        $this->make_instance(100, 5);

        $student = $this->getDataGenerator()->create_user();
        $this->seed_player($student->id, 999999);

        [, $rows] = (new export())->build_export($this->course->id, $this->instanceid);

        $this->assertSame(5, $rows[0][3]);
    }

    /**
     * Teachers and managers are excluded from the export.
     *
     * @covers ::build_export
     */
    public function test_build_export_excludes_managers(): void {
        $this->resetAfterTest();
        $this->make_instance();

        $student = $this->getDataGenerator()->create_user(['email' => 'student@example.com']);
        $this->seed_player($student->id, 100);

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->seed_player($teacher->id, 999);

        [, $rows] = (new export())->build_export($this->course->id, $this->instanceid);

        $this->assertCount(1, $rows);
        $this->assertSame('student@example.com', $rows[0][2]);
    }

    /**
     * With no players the rows are empty and the localized columns are returned.
     *
     * @covers ::build_export
     */
    public function test_build_export_without_players_returns_localized_columns(): void {
        $this->resetAfterTest();
        $this->make_instance();

        [$columns, $rows] = (new export())->build_export($this->course->id, $this->instanceid);

        $this->assertSame([], $rows);
        $this->assertCount(6, $columns);
        $this->assertSame(get_string('firstname'), $columns[0]);
    }
}
