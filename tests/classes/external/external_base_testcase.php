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
 * Shared base test case for PlayerHUD external web service tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\tests\external;

use advanced_testcase;

/**
 * Provides a course, a block instance and common fixture helpers for external tests.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class external_base_testcase extends advanced_testcase {
    /** @var \stdClass Shared course for all tests. */
    protected $course;

    /** @var int Primary block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh course and block instance for each test, running as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->course     = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
    }

    /**
     * Insert a minimal block_instances row for the shared course and return its ID.
     *
     * @param array $config Optional block configuration stored in configdata.
     * @return int The new instance ID.
     */
    protected function create_block_instance(array $config = []): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize((object) $config)),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Insert an item record and return it with id set.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $name Item name.
     * @param array $overrides Field overrides merged on top of sane defaults.
     * @return \stdClass The inserted record.
     */
    protected function create_item(int $instanceid, string $name, array $overrides = []): \stdClass {
        global $DB;
        $item = (object) array_merge([
            'blockinstanceid'   => $instanceid,
            'name'              => $name,
            'image'             => '🪙',
            'description'       => '',
            'xp'                => 0,
            'enabled'           => 1,
            'tradable'          => 1,
            'secret'            => 0,
            'required_class_id' => '0',
            'action_type'       => '',
            'action_value'      => '',
            'timecreated'       => time(),
            'timemodified'      => time(),
        ], $overrides);
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Add one unit of an item to a user's inventory and return the record ID.
     *
     * @param int $userid Target user ID.
     * @param int $itemid Item ID.
     * @param array $overrides Field overrides merged on top of sane defaults.
     * @return int Inventory record ID.
     */
    protected function give_item_to_user(int $userid, int $itemid, array $overrides = []): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_inventory', (object) array_merge([
            'userid'      => $userid,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'drop',
            'timecreated' => time(),
        ], $overrides));
    }

    /**
     * Create a student enrolled in the course but explicitly denied the view
     * capability on the block context.
     *
     * Enrolment lets the user pass require_login (so validate_context succeeds),
     * while the prohibited capability triggers a required_capability_exception
     * at the block's view check.
     *
     * @return \stdClass The created user record.
     */
    protected function create_student_without_view(): \stdClass {
        global $DB;
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $blockcontext = \context_block::instance($this->instanceid);
        assign_capability('block/playerhud:view', CAP_PROHIBIT, $studentrole->id, $blockcontext->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        return $student;
    }

    /**
     * Insert a story chapter for the shared block instance.
     *
     * @param string $title Chapter title.
     * @return \stdClass The created chapter record.
     */
    protected function create_chapter(string $title): \stdClass {
        global $DB;
        $chapter = (object) [
            'blockinstanceid' => $this->instanceid,
            'title'           => $title,
            'intro_text'      => '',
            'unlock_date'     => 0,
            'required_level'  => 0,
            'sortorder'       => 1,
        ];
        $chapter->id = $DB->insert_record('block_playerhud_chapters', $chapter);
        return $chapter;
    }

    /**
     * Insert a story node.
     *
     * @param int $chapterid Chapter this node belongs to.
     * @param string $content Node text content.
     * @param bool $isstart Whether this is the starting node.
     * @return \stdClass The created node record.
     */
    protected function create_node(int $chapterid, string $content, bool $isstart = false): \stdClass {
        global $DB;
        $node = (object) [
            'chapterid' => $chapterid,
            'content'   => $content,
            'is_start'  => $isstart ? 1 : 0,
        ];
        $node->id = $DB->insert_record('block_playerhud_story_nodes', $node);
        return $node;
    }

    /**
     * Insert a choice linking two nodes.
     *
     * @param int $nodeid Source node ID.
     * @param string $text Choice label text.
     * @param int $nextnodeid Destination node ID (0 = terminal).
     * @return \stdClass The created choice record.
     */
    protected function create_choice(int $nodeid, string $text, int $nextnodeid = 0): \stdClass {
        global $DB;
        $choice = (object) [
            'nodeid'        => $nodeid,
            'text'          => $text,
            'next_nodeid'   => $nextnodeid,
            'req_class_id'  => 0,
            'req_karma_min' => 0,
            'karma_delta'   => 0,
            'set_class_id'  => 0,
            'cost_itemid'   => 0,
            'cost_item_qty' => 1,
        ];
        $choice->id = $DB->insert_record('block_playerhud_choices', $choice);
        return $choice;
    }
}
