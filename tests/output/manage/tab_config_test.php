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

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * Tests for the config tab renderer (economy health summary + AI key fields).
 *
 * This class had no test coverage at all before ITEM 3's type-hint sweep. It is
 * dispatched by manage.php's generic tab controller before $OUTPUT->header() runs,
 * so export_for_template()'s $output parameter must stay untyped — see the
 * bootstrap_renderer case log in moodle-lessons.md.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_config
 */
final class tab_config_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh course, block instance and logged-in teacher for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
        $this->setUser($this->getDataGenerator()->create_user());

        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/manage.php', ['id' => $this->course->id]);
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
     * An instance with no items or quests still exports a well-formed empty summary,
     * instead of crashing on the economy_health() call.
     */
    public function test_export_for_template_empty_instance(): void {
        $tab = new tab_config($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertSame(0, $data['total_items_xp']);
        $this->assertSame([], $data['breakdown_rows']);
        $this->assertSame(0, $data['breakdown_count']);
        $this->assertNotEmpty($data['balance_message']);
        $this->assertStringContainsString('manage.php', $data['action_url']);
    }

    /**
     * An item with XP contributes to the breakdown and the achievable total.
     */
    public function test_export_for_template_includes_item_breakdown(): void {
        global $DB;

        $itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Gem',
            'description'     => '',
            'xp'              => 50,
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Spot',
            'maxusage'        => 1,
            'respawntime'     => 0,
            'code'            => \block_playerhud\utils::generate_drop_code($this->instanceid),
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $tab = new tab_config($this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertSame(50, $data['total_items_xp']);
        $this->assertCount(1, $data['breakdown_rows']);
        $this->assertSame(1, $data['breakdown_count']);
    }
}
