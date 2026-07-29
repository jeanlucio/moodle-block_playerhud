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

/**
 * Tests for the RPG character selection screen (student view).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep — view.php never
 * gets a Behat scenario for this tab.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_class_select
 */
final class tab_class_select_test extends advanced_testcase {
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
     * Creates an RPG class row and returns its id.
     *
     * @param string $name Class name.
     * @return int The new class ID.
     */
    private function create_class(string $name): int {
        global $DB;
        return $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'description'     => '',
            'base_hp'         => 100,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * With no classes configured, the screen renders the empty state instead of crashing.
     */
    public function test_display_with_no_classes(): void {
        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_class_select(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertIsString($html);
    }

    /**
     * A class the player has not yet picked is listed, unselected.
     */
    public function test_display_lists_available_class(): void {
        $this->create_class('Ranger');
        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_class_select(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('Ranger', $html);
    }

    /**
     * A class the player already picked is marked as selected.
     */
    public function test_display_marks_selected_class(): void {
        $classid = $this->create_class('Mage');
        \block_playerhud\game::assign_class($this->instanceid, $this->user->id, $classid);

        $player = (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
        $tab = new tab_class_select(new \stdClass(), $player, $this->instanceid, $this->course->id);

        $html = $tab->display();

        $this->assertStringContainsString('Mage', $html);
    }
}
