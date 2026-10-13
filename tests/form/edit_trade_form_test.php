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
 * Tests for the trade and quest forms' item selects.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use advanced_testcase;

/**
 * An item that was disabled for a while must stay selectable when its trade or quest is edited.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\form\edit_trade_form
 * @covers     \block_playerhud\form\edit_quest_form
 * @covers     \block_playerhud\utils
 */
final class edit_trade_form_test extends advanced_testcase {
    /** @var int Block instance holding the items. */
    private int $instanceid;

    /** @var \stdClass The course of the instance. */
    private \stdClass $course;

    /** @var int An enabled item. */
    private int $enabled;

    /** @var int A disabled item. */
    private int $disabled;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($this->course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        $make = fn(string $name, int $enabled) => (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => $name, 'enabled' => $enabled,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->enabled = $make('Gold coin', 1);
        $this->disabled = $make('Key', 0);
    }

    /**
     * The options list every item of the instance, a disabled one marked as such.
     */
    public function test_item_select_options_mark_the_disabled_items(): void {
        $options = \block_playerhud\utils::get_item_select_options($this->instanceid);

        $this->assertSame('Gold coin', $options[$this->enabled]);
        $this->assertSame(get_string('item_option_disabled', 'block_playerhud', 'Key'), $options[$this->disabled]);
        $this->assertSame([], \block_playerhud\utils::get_item_select_options($this->instanceid + 1000));
    }

    /**
     * Saving a trade whose requirement is a disabled item keeps that item: the select used to
     * offer enabled items only, so the submitted value was refused or turned into "none".
     */
    public function test_trade_form_accepts_a_disabled_item(): void {
        edit_trade_form::mock_submit([
            'name' => 'Swap', 'tradeid' => 0, 'courseid' => $this->course->id, 'instanceid' => $this->instanceid,
            'repeats_req' => 1, 'repeats_give' => 1,
            'req_itemid_0' => $this->disabled, 'req_qty_0' => 2,
            'give_itemid_0' => $this->enabled, 'give_qty_0' => 1,
        ]);
        $form = new edit_trade_form(null, ['instanceid' => $this->instanceid, 'courseid' => $this->course->id]);

        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame((string) $this->disabled, (string) $data->req_itemid_0);
    }

    /**
     * The quest form offers a disabled reward item too.
     */
    public function test_quest_form_offers_a_disabled_reward_item(): void {
        global $PAGE;
        $PAGE->set_url('/blocks/playerhud/manage.php');
        $PAGE->set_context(\context_course::instance($this->course->id));
        $form = new edit_quest_form(null, ['instanceid' => $this->instanceid, 'courseid' => $this->course->id]);

        ob_start();
        $form->display();
        $html = ob_get_clean();

        $this->assertStringContainsString(
            s(get_string('item_option_disabled', 'block_playerhud', 'Key')),
            $html
        );
    }
}
