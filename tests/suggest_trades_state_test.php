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
 * Tests for game::suggest_trades_state — the four button states of the
 * Suggest Trades feature.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game::suggest_trades_state
 */
final class suggest_trades_state_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh block instance for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->instanceid = $this->create_block_instance();
    }

    /**
     * No PlayerCoin and no avatar items → disabled, reason='prereq'.
     */
    public function test_disabled_when_no_coin_and_no_avatars(): void {
        $state = game::suggest_trades_state($this->instanceid);

        $this->assertFalse($state['enabled']);
        $this->assertEquals('prereq', $state['reason']);
    }

    /**
     * PlayerCoin exists but no avatar items → disabled, reason='prereq'.
     */
    public function test_disabled_when_coin_exists_but_no_avatars(): void {
        $this->create_item('PlayerCoin', '');

        $state = game::suggest_trades_state($this->instanceid);

        $this->assertFalse($state['enabled']);
        $this->assertEquals('prereq', $state['reason']);
    }

    /**
     * PlayerCoin + 1 avatar, that avatar already covered by a single-reward trade
     * AND a bundle trade exists → disabled, reason='all_covered'.
     *
     * "Covered" means:
     *   1. The avatar appears as the sole reward in at least one trade.
     *   2. A trade whose rewards include every avatar item already exists.
     *
     * With a single avatar, conditions 1 and 2 are satisfied by the same trade.
     */
    public function test_disabled_when_all_avatars_already_covered(): void {
        global $DB;

        $this->create_item('PlayerCoin', '');
        $avatar = $this->create_item('Mage', 'avatar_profile');
        $coin   = $this->create_item('TestCoin', '');

        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Get Mage',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 1,
        ]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $avatar->id,
            'qty'     => 1,
        ]);

        $state = game::suggest_trades_state($this->instanceid);

        $this->assertFalse($state['enabled']);
        $this->assertEquals('all_covered', $state['reason']);
    }

    /**
     * PlayerCoin + 2 avatars, only 1 covered by a single-reward trade → enabled.
     */
    public function test_enabled_when_coverage_is_partial(): void {
        global $DB;

        $this->create_item('PlayerCoin', '');
        $avatar1 = $this->create_item('Elf', 'avatar_profile');
        $this->create_item('Vampire', 'avatar_profile');
        $coin    = $this->create_item('TestCoin', '');

        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Get Elf',
            'groupid'         => 0,
            'onetime'         => 0,
            'timecreated'     => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $coin->id,
            'qty'     => 1,
        ]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $avatar1->id,
            'qty'     => 1,
        ]);

        $state = game::suggest_trades_state($this->instanceid);

        $this->assertTrue($state['enabled']);
        $this->assertEquals('', $state['reason']);
    }

    /**
     * Insert a minimal block_instances row and return its ID.
     *
     * @return int The new instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
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
     * Insert a minimal item and return it with id set.
     *
     * @param string $name Item name.
     * @param string $actiontype action_type value (empty string = plain item).
     * @return \stdClass The inserted record.
     */
    private function create_item(string $name, string $actiontype): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid'  => $this->instanceid,
            'name'             => $name,
            'image'            => '',
            'description'      => '',
            'xp'               => 0,
            'enabled'          => 1,
            'tradable'         => 1,
            'secret'           => 0,
            'required_class_id' => '0',
            'action_type'      => $actiontype,
            'action_value'     => '',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }
}
