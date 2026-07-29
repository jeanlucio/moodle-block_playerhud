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
 * Tests for the student view header renderer (XP display, avatar, group badge).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep — view.php never
 * gets a Behat scenario, so this is the only safety net for this class. Unlike the
 * manage/ tabs, view.php constructs this class after $OUTPUT->header() has already
 * run, so export_for_template()'s $output can safely be typed renderer_base.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\header
 */
final class header_test extends advanced_testcase {
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
        $this->user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
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
     * A player with no equipped avatar falls back to the standard user picture, and
     * carries the student's name/XP/level without a group badge (no mod_playergroup).
     */
    public function test_export_for_template_without_avatar_or_group(): void {
        $player = (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $this->user->id,
            'currentxp'       => 150,
        ];

        $output = $this->createMock(\core_renderer::class);
        $output->method('user_picture')->willReturn('<img alt="picture">');

        $header = new header(new \stdClass(), $player, $this->user, $this->course->id);
        $data = $header->export_for_template($output);

        $this->assertSame('Ada Lovelace', $data['fullname']);
        $this->assertStringContainsString('150', $data['xp_display']);
        $this->assertFalse($data['hasgroup']);
        $this->assertSame('<img alt="picture">', $data['userpicture']);
    }

    /**
     * An equipped avatar item replaces the standard user picture.
     */
    public function test_export_for_template_with_equipped_avatar(): void {
        global $DB;

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Fox Avatar',
            'description'     => '',
            'image'           => '🦊',
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'action_type'     => 'avatar_profile',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        set_user_preference('block_playerhud_avatar_' . $this->instanceid, $itemid, $this->user->id);

        $player = (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $this->user->id,
            'currentxp'       => 0,
        ];

        $output = $this->createMock(\renderer_base::class);

        $header = new header(new \stdClass(), $player, $this->user, $this->course->id);
        $data = $header->export_for_template($output);

        $this->assertStringContainsString('🦊', $data['userpicture']);
    }
}
