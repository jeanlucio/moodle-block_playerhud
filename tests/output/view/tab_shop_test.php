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
 * Tests for the student shop tab (affordability, one-time completion).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep. Tests
 * export_for_template() directly rather than display(), for the same
 * bootstrap_renderer reason documented on the other view/ tab tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_shop
 */
final class tab_shop_test extends advanced_testcase {
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
     * Creates an item and returns its id.
     *
     * @param string $name Item name.
     * @return int The new item ID.
     */
    private function create_item(string $name): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'description'     => '',
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates a centralized, available trade requiring $reqitemid x1 and rewarding
     * $rewitemid x1, and returns its id.
     *
     * @param string $name Trade name.
     * @param int $reqitemid Required item ID.
     * @param int $rewitemid Reward item ID.
     * @param bool $onetime Whether the trade can only be performed once per user.
     * @return int The new trade ID.
     */
    private function create_trade(string $name, int $reqitemid, int $rewitemid, bool $onetime = false): int {
        global $DB;
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'groupid'         => 0,
            'centralized'     => 1,
            'onetime'         => $onetime ? 1 : 0,
            'timecreated'     => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $reqitemid,
            'qty'     => 1,
        ]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $rewitemid,
            'qty'     => 1,
        ]);
        return $tradeid;
    }

    /**
     * A player object with the fields tab_shop reads.
     *
     * @return \stdClass The player object.
     */
    private function make_player(): \stdClass {
        return (object) ['blockinstanceid' => $this->instanceid, 'userid' => $this->user->id];
    }

    /**
     * No trades at all renders the empty state instead of crashing.
     */
    public function test_export_for_template_with_no_trades(): void {
        $tab = new tab_shop(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertFalse($data['has_trades']);
        $this->assertSame([], $data['trades']);
    }

    /**
     * A student holding enough of the required item can afford the trade.
     */
    public function test_export_for_template_affordable_trade(): void {
        global $DB;

        $coinid = $this->create_item('Coin');
        $potionid = $this->create_item('Potion');
        $this->create_trade('Buy Potion', $coinid, $potionid);

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $this->user->id, 'itemid' => $coinid, 'dropid' => 0,
            'source' => 'map', 'timecreated' => time(),
        ]);

        $tab = new tab_shop(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertCount(1, $data['trades']);
        $this->assertTrue($data['trades'][0]['can_afford']);
    }

    /**
     * A student with none of the required item cannot afford the trade.
     */
    public function test_export_for_template_unaffordable_trade(): void {
        $coinid = $this->create_item('Coin');
        $potionid = $this->create_item('Potion');
        $this->create_trade('Buy Potion', $coinid, $potionid);

        $tab = new tab_shop(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertCount(1, $data['trades']);
        $this->assertFalse($data['trades'][0]['can_afford']);
    }

    /**
     * A one-time trade the student already completed is marked as such.
     */
    public function test_export_for_template_marks_completed_onetime_trade(): void {
        global $DB;

        $coinid = $this->create_item('Coin');
        $hatid = $this->create_item('Hat');
        $tradeid = $this->create_trade('Trade Hat', $coinid, $hatid, true);

        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $tradeid, 'userid' => $this->user->id, 'timecreated' => time(),
        ]);

        $tab = new tab_shop(new \stdClass(), $this->make_player(), $this->instanceid, $this->course->id);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertCount(1, $data['trades']);
        $this->assertTrue($data['trades'][0]['is_completed']);
    }
}
