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

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\game;
use block_playerhud\utils;

/**
 * Tests for RPG class assignment and portrait tier logic.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game
 * @covers     \block_playerhud\utils
 */
final class rpg_classes_test extends advanced_testcase {
    /** @var int Block instance ID shared across test methods. */
    protected int $instanceid;

    /**
     * Creates a real block instance in the database to satisfy FK constraints.
     */
    protected function setup_block_instance(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->subpagepattern = null;
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = time();
        $bi->timemodified = time();

        $this->instanceid = $DB->insert_record('block_instances', $bi);
    }

    /**
     * Inserts a dummy RPG class row for testing.
     *
     * @param string $name Class name.
     * @return \stdClass The created class record including id.
     */
    protected function create_dummy_class(string $name): \stdClass {
        global $DB;

        $class = new \stdClass();
        $class->blockinstanceid = $this->instanceid;
        $class->name = $name;
        $class->description = '';
        $class->base_hp = 100;
        $class->timecreated = time();
        $class->timemodified = time();
        $class->id = $DB->insert_record('block_playerhud_classes', $class);
        return $class;
    }

    /**
     * get_player_class returns false when no progress record exists yet.
     *
     * @covers ::get_player_class
     */
    public function test_get_player_class_returns_false_for_new_user(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        $result = game::get_player_class($this->instanceid, $user->id);

        $this->assertFalse($result);
    }

    /**
     * assign_class creates a progress record with the correct classid.
     *
     * @covers ::assign_class
     * @covers ::get_player_class
     */
    public function test_assign_class_creates_progress_record(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $class = $this->create_dummy_class('Mage');

        game::assign_class($this->instanceid, $user->id, $class->id);

        $progress = game::get_player_class($this->instanceid, $user->id);

        $this->assertNotFalse($progress);
        $this->assertEquals($class->id, (int) $progress->classid);
    }

    /**
     * assign_class updates the classid when a progress record already exists.
     *
     * @covers ::assign_class
     */
    public function test_assign_class_updates_existing_class(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $mage = $this->create_dummy_class('Mage');
        $warrior = $this->create_dummy_class('Warrior');

        // First assignment.
        game::assign_class($this->instanceid, $user->id, $mage->id);
        // Second assignment (class change).
        game::assign_class($this->instanceid, $user->id, $warrior->id);

        $progress = game::get_player_class($this->instanceid, $user->id);

        $this->assertEquals($warrior->id, (int) $progress->classid);
    }

    /**
     * assign_class does not create duplicate records on repeated calls.
     *
     * @covers ::assign_class
     */
    public function test_assign_class_does_not_duplicate_records(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $class = $this->create_dummy_class('Rogue');

        game::assign_class($this->instanceid, $user->id, $class->id);
        game::assign_class($this->instanceid, $user->id, $class->id);

        $count = $DB->count_records(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $this->instanceid, 'userid' => $user->id]
        );

        $this->assertEquals(1, $count);
    }

    /**
     * assign_class initialises karma at 0 for new records.
     *
     * @covers ::assign_class
     */
    public function test_assign_class_initialises_karma_at_zero(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $class = $this->create_dummy_class('Cleric');

        game::assign_class($this->instanceid, $user->id, $class->id);

        $progress = game::get_player_class($this->instanceid, $user->id);

        $this->assertEquals(0, (int) $progress->karma);
    }

    /**
     * get_class_portrait_tier returns 1 when no progress record exists.
     *
     * @covers \block_playerhud\utils::get_class_portrait_tier
     */
    public function test_get_class_portrait_tier_returns_1_when_no_progress(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        $tier = utils::get_class_portrait_tier($this->instanceid, $user->id);

        $this->assertEquals(1, $tier);
    }

    /**
     * get_class_portrait_tier returns the correct tier at every boundary.
     *
     * Thresholds: 0→1, 1→2, 2–3→3, 4–5→4, 6+→5.
     *
     * @covers \block_playerhud\utils::get_class_portrait_tier
     */
    public function test_get_class_portrait_tier_boundaries(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        // Seed a progress record so we can overwrite completed_chapters directly.
        $progress = (object) [
            'blockinstanceid'    => $this->instanceid,
            'userid'             => $user->id,
            'classid'            => 0,
            'karma'              => 0,
            'current_nodes'      => json_encode([]),
            'completed_chapters' => json_encode([]),
        ];
        $progressid = $DB->insert_record('block_playerhud_rpg_progress', $progress);

        // Each entry: [completed_chapters_array, expected_tier, description].
        $cases = [
            [[], 1, '0 chapters → tier 1'],
            [[1], 2, '1 chapter → tier 2'],
            [[1, 2], 3, '2 chapters → tier 3'],
            [[1, 2, 3], 3, '3 chapters → tier 3'],
            [[1, 2, 3, 4], 4, '4 chapters → tier 4'],
            [[1, 2, 3, 4, 5], 4, '5 chapters → tier 4'],
            [[1, 2, 3, 4, 5, 6], 5, '6 chapters → tier 5'],
        ];

        foreach ($cases as [$chapters, $expectedtier, $description]) {
            $DB->set_field(
                'block_playerhud_rpg_progress',
                'completed_chapters',
                json_encode($chapters),
                ['id' => $progressid]
            );
            $this->assertEquals(
                $expectedtier,
                utils::get_class_portrait_tier($this->instanceid, $user->id),
                $description
            );
        }
    }
}
