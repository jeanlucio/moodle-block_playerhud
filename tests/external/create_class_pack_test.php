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
 * Tests for the create_class_pack web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the create_class_pack web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\create_class_pack
 */
final class create_class_pack_test extends external_base_testcase {
    /**
     * A fresh instance receives the 3 pre-defined archetypes for the requested tone.
     */
    public function test_create_class_pack_creates_3_classes(): void {
        global $DB;

        $result = create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');

        $this->assertEquals(3, $result['created']);
        $this->assertCount(3, $result['created_class_ids']);
        $this->assertCount(3, $result['created_class_names']);
        $total = $DB->count_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(3, $total);
    }

    /**
     * The 3 archetypes carry the fixed base HP tiers (front/insight/precision).
     */
    public function test_create_class_pack_uses_expected_base_hp_tiers(): void {
        global $DB;

        create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');

        $classes = $DB->get_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]);
        $basehps = array_map(static fn($class): int => (int) $class->base_hp, array_values($classes));
        sort($basehps);
        $this->assertSame([80, 100, 150], $basehps);
    }

    /**
     * A class already present with a matching name is skipped, not duplicated.
     */
    public function test_create_class_pack_skips_existing_name(): void {
        global $DB;

        $frontname = get_string('rpg_fantasy_class_front_name', 'block_playerhud');
        $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $frontname,
            'description'     => 'Pre-existing custom warrior.',
            'base_hp'         => 999,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $result = create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');

        $this->assertEquals(2, $result['created'], 'The front archetype must be skipped, only 2 created.');
        $total = $DB->count_records('block_playerhud_classes', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(3, $total, '2 new + 1 pre-existing = 3 total.');
    }

    /**
     * Calling create_class_pack twice with the same tone returns created=0 on the second call.
     */
    public function test_create_class_pack_idempotent_second_call_creates_zero(): void {
        create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');

        $second = create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');

        $this->assertEquals(0, $second['created']);
    }

    /**
     * Different tones produce different archetype names, so both packs can coexist.
     */
    public function test_create_class_pack_different_tones_produce_different_names(): void {
        $fantasy = create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');
        $scifi = create_class_pack::execute($this->instanceid, $this->course->id, 'scifi');

        $this->assertEquals(3, $scifi['created'], 'Sci-Fi archetypes have different names, so none are skipped.');
        $this->assertEmpty(array_intersect($fantasy['created_class_names'], $scifi['created_class_names']));
    }

    /**
     * An unrecognised tone key falls back to the Fantasy pack.
     */
    public function test_create_class_pack_unknown_tone_falls_back_to_fantasy(): void {
        $result = create_class_pack::execute($this->instanceid, $this->course->id, 'unknowntone');

        $frontname = get_string('rpg_fantasy_class_front_name', 'block_playerhud');
        $this->assertContains($frontname, $result['created_class_names']);
    }

    /**
     * A student without block/playerhud:manage must not be able to create the class pack.
     */
    public function test_create_class_pack_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        create_class_pack::execute($this->instanceid, $this->course->id, 'fantasy');
    }
}
