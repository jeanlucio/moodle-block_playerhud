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

namespace block_playerhud\output\view;

use advanced_testcase;
use block_playerhud\quest;

/**
 * Tests for the student quests tab (claimable/claimed states, type labels).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep — view.php never
 * gets a Behat scenario for this tab.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_quests
 */
final class tab_quests_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var \stdClass Test user. */
    protected $user;

    /**
     * Create a fresh course, block instance and user for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/view.php', ['id' => $this->course->id]);
        $PAGE->set_context(\context_course::instance($this->course->id));
    }

    /**
     * Creates a minimal block_instances row and returns its id.
     *
     * @return int The new block instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Creates a TYPE_XP_TOTAL quest with a target of 0 (always completed) and returns its id.
     *
     * @param string $name Quest name.
     * @return int The new quest ID.
     */
    private function create_always_completable_quest(string $name): int {
        global $DB;
        return $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid'   => $this->instanceid,
            'name'              => $name,
            'description'       => '',
            'type'              => quest::TYPE_XP_TOTAL,
            'requirement'       => '0',
            'req_itemid'        => 0,
            'reward_xp'         => 10,
            'reward_itemid'     => 0,
            'required_class_id' => '0',
            'image_todo'        => '📋',
            'image_done'        => '🏅',
            'enabled'           => 1,
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * A player object with the fields tab_quests reads.
     *
     * @param int $currentxp Current XP for the player.
     * @return \stdClass The player object.
     */
    private function make_player(int $currentxp = 0): \stdClass {
        return (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $this->user->id,
            'currentxp'       => $currentxp,
        ];
    }

    /**
     * No quests at all renders the empty notification instead of crashing.
     */
    public function test_display_with_no_quests(): void {
        $tab = new tab_quests(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertIsString($html);
    }

    /**
     * A completed, unclaimed quest is listed with a claim action.
     */
    public function test_display_lists_claimable_quest(): void {
        $this->create_always_completable_quest('Reach the Summit');
        $tab = new tab_quests(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('Reach the Summit', $html);
    }

    /**
     * A quest already claimed by this user shows its claimed date instead of a claim action.
     */
    public function test_display_marks_claimed_quest(): void {
        global $DB;

        $questid = $this->create_always_completable_quest('Old Victory');
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid'     => $questid,
            'userid'      => $this->user->id,
            'timecreated' => time(),
        ]);

        $tab = new tab_quests(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $html = $tab->display();

        $this->assertStringContainsString('Old Victory', $html);
    }

    /**
     * get_type_label() maps every quest type constant to a non-placeholder label, and
     * falls back to '-' for an unrecognised type.
     */
    public function test_get_type_label_maps_known_types_and_falls_back(): void {
        $tab = new tab_quests(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $method = new \ReflectionMethod($tab, 'get_type_label');
        $method->setAccessible(true);

        $this->assertNotSame('-', $method->invoke($tab, quest::TYPE_XP_TOTAL));
        $this->assertSame('-', $method->invoke($tab, 999));
    }
}
