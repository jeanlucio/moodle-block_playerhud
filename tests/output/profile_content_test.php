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
 * Tests for the profile PlayerHUD section renderable.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use advanced_testcase;
use stdClass;

/**
 * Regression coverage for the item image field, which is not routed through the file API for a
 * plain emoji/markup value — every sibling view of the same field (tab_collection, tab_items,
 * tab_shop, tab_trades, tab_history, tab_reports, utils::get_avatar_html) applies strip_tags(),
 * and this renderable had been left behind.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\profile_content
 */
final class profile_content_test extends advanced_testcase {
    /**
     * Regression test for the security-audit finding: the raw item image field must be
     * stripped of tags before reaching the template, matching every other view of the same
     * data.
     */
    public function test_export_for_template_strips_tags_from_image_content(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);

        $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Marked Item',
            'image'           => '<b>Not an emoji</b>',
            'xp'              => 0,
            'enabled'         => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
            'currentxp'       => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        $DB->insert_record('block_playerhud_stack', (object) [
            'userid'       => $user->id,
            'itemid'       => $itemid,
            'qty'          => 1,
            'timemodified' => time(),
        ]);

        $renderable = new profile_content($instanceid, (int) $user->id);
        $context = $renderable->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($context->items);
        $this->assertStringNotContainsString('<b>', $context->items[0]['imagecontent']);
        $this->assertStringContainsString('Not an emoji', $context->items[0]['imagecontent']);
    }
}
