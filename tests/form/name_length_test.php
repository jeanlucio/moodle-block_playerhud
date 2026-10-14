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
 * Tests for the name length limit of the forms.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * A name longer than its column must be refused by the form, not by a database error.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_chapter_form
 * @covers     \block_playerhud\form\edit_drop_form
 * @covers     \block_playerhud\form\edit_item_form
 * @covers     \block_playerhud\form\edit_quest_form
 * @covers     \block_playerhud\form\edit_trade_form
 */
final class name_length_test extends advanced_testcase {
    /**
     * Each form with the submitted fields that make it otherwise valid, and its name field.
     *
     * @return array[] Form class, name field, other submitted fields, customdata.
     */
    public static function forms_provider(): array {
        return [
            'drop' => [edit_drop_form::class, 'name', [], ['itemname' => 'Item']],
            'chapter' => [edit_chapter_form::class, 'title', ['required_level' => 1, 'sortorder' => 1],
                ['chapterid' => 0, 'nextposition' => 1]],
            'item' => [edit_item_form::class, 'name', ['xp' => 1, 'image' => 'x'],
                ['instanceid' => 0, 'lp_activities' => [], 'itemid' => 0]],
            'quest' => [edit_quest_form::class, 'name', ['target_value' => 1, 'reward_xp' => 1, 'type' => 1],
                ['instanceid' => 0, 'courseid' => 0]],
            'trade' => [edit_trade_form::class, 'name', ['repeats_req' => 1, 'repeats_give' => 1],
                ['instanceid' => 0, 'courseid' => 0]],
        ];
    }

    /**
     * A name that fits the column is accepted and one character more is refused.
     *
     * @dataProvider forms_provider
     * @param string $class Form class.
     * @param string $field Name of the name field.
     * @param array $others Other submitted fields.
     * @param array $customdata Form customdata.
     */
    public function test_name_longer_than_the_column_is_refused(
        string $class,
        string $field,
        array $others,
        array $customdata
    ): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        if ($class === edit_trade_form::class) {
            $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
                'blockinstanceid' => $instanceid, 'name' => 'Coin', 'enabled' => 1,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $others += [
                'tradeid' => 0, 'courseid' => $course->id, 'instanceid' => $instanceid,
                'req_itemid_0' => $itemid, 'req_qty_0' => 1, 'give_itemid_0' => $itemid, 'give_qty_0' => 1,
            ];
        }
        foreach (['instanceid' => $instanceid, 'courseid' => (int) $course->id] as $key => $value) {
            if (array_key_exists($key, $customdata) && $key !== 'itemid') {
                $customdata[$key] = $value;
            }
        }

        $class::mock_submit([$field => str_repeat('a', 255)] + $others);
        $this->assertNotNull((new $class(null, $customdata))->get_data(), 'A 255-character name is valid.');

        $class::mock_submit([$field => str_repeat('a', 256)] + $others);
        $this->assertNull((new $class(null, $customdata))->get_data(), 'A 256-character name is refused.');
    }
}
