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

/**
 * Tests for the karma subsystem (get_player_karma and adjust_karma).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game
 */
final class karma_test extends advanced_testcase {
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
     * Seeds a progress record with the given karma value.
     *
     * @param int $userid User ID.
     * @param int $karma  Initial karma value.
     * @return int The inserted record ID.
     */
    protected function seed_progress(int $userid, int $karma): int {
        global $DB;

        return $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid'    => $this->instanceid,
            'userid'             => $userid,
            'classid'            => 0,
            'karma'              => $karma,
            'current_nodes'      => json_encode([]),
            'completed_chapters' => json_encode([]),
        ]);
    }

    /**
     * get_player_karma returns 0 when no progress record exists.
     *
     * @covers ::get_player_karma
     */
    public function test_get_player_karma_returns_zero_when_no_record(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        $this->assertEquals(0, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * get_player_karma returns the stored value when a progress record exists.
     *
     * @covers ::get_player_karma
     */
    public function test_get_player_karma_returns_stored_value(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 42);

        $this->assertEquals(42, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * get_player_karma returns negative stored values correctly.
     *
     * @covers ::get_player_karma
     */
    public function test_get_player_karma_returns_negative_value(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, -150);

        $this->assertEquals(-150, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * adjust_karma returns 0 and does nothing when no progress record exists.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_returns_zero_when_no_record(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        $result = game::adjust_karma($this->instanceid, $user->id, 10);

        $this->assertEquals(0, $result);
    }

    /**
     * adjust_karma increases karma by a positive delta.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_positive_delta(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 50);

        $result = game::adjust_karma($this->instanceid, $user->id, 30);

        $this->assertEquals(80, $result);
        $this->assertEquals(80, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * adjust_karma decreases karma by a negative delta.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_negative_delta(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 100);

        $result = game::adjust_karma($this->instanceid, $user->id, -40);

        $this->assertEquals(60, $result);
        $this->assertEquals(60, game::get_player_karma($this->instanceid, $user->id));
    }

    /**
     * adjust_karma clamps the result to the maximum of 999.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_clamped_at_maximum(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 990);

        $result = game::adjust_karma($this->instanceid, $user->id, 50);

        $this->assertEquals(999, $result);
    }

    /**
     * adjust_karma clamps the result to the minimum of -999.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_clamped_at_minimum(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, -990);

        $result = game::adjust_karma($this->instanceid, $user->id, -50);

        $this->assertEquals(-999, $result);
    }

    /**
     * adjust_karma clamped exactly at boundary 999 stays at 999.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_does_not_exceed_boundary_999(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 999);

        $result = game::adjust_karma($this->instanceid, $user->id, 1);

        $this->assertEquals(999, $result);
    }

    /**
     * adjust_karma clamped exactly at boundary -999 stays at -999.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_does_not_fall_below_boundary_minus999(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, -999);

        $result = game::adjust_karma($this->instanceid, $user->id, -1);

        $this->assertEquals(-999, $result);
    }

    /**
     * Successive adjust_karma calls accumulate correctly.
     *
     * @covers ::adjust_karma
     */
    public function test_adjust_karma_successive_calls_accumulate(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_progress($user->id, 0);

        game::adjust_karma($this->instanceid, $user->id, 20);
        game::adjust_karma($this->instanceid, $user->id, 30);
        $result = game::adjust_karma($this->instanceid, $user->id, -5);

        $this->assertEquals(45, $result);
    }
}
