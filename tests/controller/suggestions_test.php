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
 * Tests for the suggestions controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use stdClass;

/**
 * Tests for the quest/trade suggestion persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\suggestions
 */
final class suggestions_test extends advanced_testcase {
    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    protected function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Creates an item for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new item ID.
     */
    protected function make_item(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Item',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Builds a quest suggestion entry.
     *
     * @param string $uid Suggestion unique key.
     * @param string $name Quest name.
     * @return array
     */
    protected function quest_suggestion(string $uid, string $name): array {
        return [
            'uid'         => $uid,
            'name'        => $name,
            'type'        => 1,
            'requirement' => '100',
            'reward_xp'   => 50,
            'image_todo'  => '',
            'image_done'  => '',
        ];
    }

    /**
     * Builds a trade suggestion entry.
     *
     * @param string $uid Suggestion unique key.
     * @param string $name Trade name.
     * @param int $costitemid Item paid for the trade.
     * @param int $rewarditemid Item received from the trade.
     * @return array
     */
    protected function trade_suggestion(string $uid, string $name, int $costitemid, int $rewarditemid): array {
        return [
            'uid'         => $uid,
            'name'        => $name,
            'cost_itemid' => $costitemid,
            'cost_qty'    => 2,
            'rewards'     => [['id' => $rewarditemid, 'qty' => 1]],
        ];
    }

    /**
     * Only the ticked quest suggestions are inserted, and the count is returned.
     *
     * @covers ::save_quest_suggestions
     */
    public function test_save_quest_suggestions_inserts_only_selected(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $suggestions = [
            $this->quest_suggestion('a', 'Picked'),
            $this->quest_suggestion('b', 'Skipped'),
        ];
        // Tick only the first suggestion.
        $formdata = (object) ['sug_a' => 1, 'sug_b' => 0];

        $count = suggestions::save_quest_suggestions($instanceid, $suggestions, $formdata);

        $this->assertSame(1, $count);
        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid]);
        $this->assertCount(1, $quests);
        $quest = reset($quests);
        $this->assertSame('Picked', $quest->name);
        $this->assertSame(50, (int) $quest->reward_xp);
    }

    /**
     * No ticked quest suggestion inserts nothing and returns zero.
     *
     * @covers ::save_quest_suggestions
     */
    public function test_save_quest_suggestions_none_selected(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $suggestions = [$this->quest_suggestion('a', 'Picked')];
        $formdata = (object) ['sug_a' => 0];

        $count = suggestions::save_quest_suggestions($instanceid, $suggestions, $formdata);

        $this->assertSame(0, $count);
        $this->assertSame(0, $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $instanceid]));
    }

    /**
     * Only the ticked trade suggestions are created with their reqs and rewards.
     *
     * @covers ::save_trade_suggestions
     */
    public function test_save_trade_suggestions_creates_only_selected(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $costitem = $this->make_item($instanceid);
        $rewarditem = $this->make_item($instanceid);
        $suggestions = [
            $this->trade_suggestion('a', 'Picked', $costitem, $rewarditem),
            $this->trade_suggestion('b', 'Skipped', $costitem, $rewarditem),
        ];
        $formdata = (object) ['sug_a' => 1, 'sug_b' => 0];

        $count = suggestions::save_trade_suggestions($instanceid, $suggestions, $formdata);

        $this->assertSame(1, $count);
        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $instanceid]);
        $this->assertCount(1, $trades);
        $trade = reset($trades);
        $this->assertSame('Picked', $trade->name);

        $req = $DB->get_record('block_playerhud_trade_reqs', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame($costitem, (int) $req->itemid);
        $this->assertSame(2, (int) $req->qty);
        $reward = $DB->get_record('block_playerhud_trade_rewards', ['tradeid' => $trade->id], '*', MUST_EXIST);
        $this->assertSame($rewarditem, (int) $reward->itemid);
    }

    /**
     * No ticked trade suggestion creates nothing and returns zero.
     *
     * @covers ::save_trade_suggestions
     */
    public function test_save_trade_suggestions_none_selected(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $item = $this->make_item($instanceid);
        $suggestions = [$this->trade_suggestion('a', 'Picked', $item, $item)];
        $formdata = (object) ['sug_a' => 0];

        $count = suggestions::save_trade_suggestions($instanceid, $suggestions, $formdata);

        $this->assertSame(0, $count);
        $this->assertSame(0, $DB->count_records('block_playerhud_trades', ['blockinstanceid' => $instanceid]));
    }
}
