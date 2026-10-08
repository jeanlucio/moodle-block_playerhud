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
 * Tests for the game master actions and their effect on the leaderboard.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;
use block_playerhud\controller\items;
use block_playerhud\controller\quests;
use context_block;

/**
 * The ranking breaks ties by the earliest time a player reached their XP, so a game master
 * correcting a student's XP downwards (revoking an item, deleting an item or a quest) must not
 * move that date, while a grant, being new progress, does. These tests drive the real controller
 * actions the management panel calls.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\controller\items
 * @covers     \block_playerhud\controller\quests
 */
final class gamemaster_test extends advanced_testcase {
    /** @var int Block instance ID. */
    private int $instanceid;

    /** @var int Course ID the block belongs to. */
    private int $courseid;

    /** @var int Time the seeded player reached their XP: five days ago. */
    private int $reachedat;

    /**
     * Creates a course with a block instance.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $this->courseid = (int) $course->id;
        $this->instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        $this->reachedat = time() - (5 * DAYSECS);
    }

    /**
     * Enrols a user and gives them a player row that reached the given XP five days ago.
     *
     * @param int $xp The player's XP.
     * @return \stdClass The user.
     */
    private function make_player(int $xp): \stdClass {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->courseid);
        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid'          => $user->id,
            'currentxp'       => $xp,
            'timecreated'     => $this->reachedat,
            'timemodified'    => $this->reachedat,
        ]);

        return $user;
    }

    /**
     * Creates an item of this instance.
     *
     * @param int $xp XP the item awards.
     * @return int The item ID.
     */
    private function make_item(int $xp): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => 'Shield',
            'xp'              => $xp,
            'enabled'         => 1,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Gives a user a legacy inventory copy that was worth the given XP.
     *
     * @param int $userid The holder.
     * @param int $itemid The item.
     * @param int $xp XP recorded for this copy.
     * @return int The inventory row ID.
     */
    private function give_copy(int $userid, int $itemid, int $xp): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $userid,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'map',
            'timecreated' => $this->reachedat,
            'xpawarded'   => $xp,
        ]);
    }

    /**
     * Reads a player's XP and last-progress date.
     *
     * @param int $userid The user.
     * @return \stdClass Row with currentxp and timemodified.
     */
    private function player(int $userid): \stdClass {
        global $DB;

        return $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $this->instanceid, 'userid' => $userid,
        ], 'currentxp, timemodified', MUST_EXIST);
    }

    /**
     * A grant is new progress: it adds the item's XP and moves the date forward.
     */
    public function test_grant_item_adds_xp_and_moves_the_date(): void {
        $user = $this->make_player(100);
        $itemid = $this->make_item(50);

        items::grant_item($itemid, (int) $user->id, $this->instanceid);

        $player = $this->player((int) $user->id);
        $this->assertSame(150, (int) $player->currentxp);
        $this->assertGreaterThan($this->reachedat, (int) $player->timemodified);
    }

    /**
     * Revoking a copy takes its XP back but leaves the date the player reached their XP alone, so
     * the tie-breaker is not broken by a correction.
     */
    public function test_revoke_item_deducts_xp_and_keeps_the_date(): void {
        global $DB;

        $user = $this->make_player(500);
        $invid = $this->give_copy((int) $user->id, $this->make_item(200), 200);

        $this->assertTrue(items::revoke_item($invid, $this->instanceid));

        $player = $this->player((int) $user->id);
        $this->assertSame(300, (int) $player->currentxp);
        $this->assertSame($this->reachedat, (int) $player->timemodified);
        $this->assertSame('revoked', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid]));
    }

    /**
     * A revoke worth more than the player's XP stops at zero.
     */
    public function test_revoke_item_never_makes_xp_negative(): void {
        $user = $this->make_player(100);
        $invid = $this->give_copy((int) $user->id, $this->make_item(300), 300);

        items::revoke_item($invid, $this->instanceid);

        $this->assertSame(0, (int) $this->player((int) $user->id)->currentxp);
    }

    /**
     * Deleting an item takes back the XP its holders earned from it, without moving their date.
     */
    public function test_delete_item_takes_back_xp_and_keeps_the_date(): void {
        global $DB;

        $user = $this->make_player(500);
        $itemid = $this->make_item(200);
        $this->give_copy((int) $user->id, $itemid, 200);

        items::delete_item(
            $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST),
            $this->instanceid,
            context_block::instance($this->instanceid)
        );

        $player = $this->player((int) $user->id);
        $this->assertSame(300, (int) $player->currentxp);
        $this->assertSame($this->reachedat, (int) $player->timemodified);
    }

    /**
     * The same for a bulk deletion of several items.
     */
    public function test_bulk_delete_items_takes_back_xp_and_keeps_the_date(): void {
        global $DB;

        $user = $this->make_player(500);
        $first = $this->make_item(100);
        $second = $this->make_item(150);
        $this->give_copy((int) $user->id, $first, 100);
        $this->give_copy((int) $user->id, $second, 150);

        items::bulk_delete_items(
            $DB->get_records_list('block_playerhud_items', 'id', [$first, $second]),
            $this->instanceid,
            context_block::instance($this->instanceid)
        );

        $player = $this->player((int) $user->id);
        $this->assertSame(250, (int) $player->currentxp);
        $this->assertSame($this->reachedat, (int) $player->timemodified);
    }

    /**
     * Deleting a quest takes back the XP it paid out, without moving the date.
     */
    public function test_delete_quest_takes_back_xp_and_keeps_the_date(): void {
        global $DB;

        $user = $this->make_player(500);
        $questid = (int) $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Quest', 'description' => '', 'type' => 2,
            'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 120, 'reward_itemid' => 0,
            'reward_itemqty' => 1, 'required_class_id' => '0', 'image_todo' => '', 'image_done' => '',
            'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => $user->id, 'xpawarded' => 120, 'timecreated' => $this->reachedat,
        ]);

        $this->assertTrue(quests::delete_quest($questid, $this->instanceid));

        $player = $this->player((int) $user->id);
        $this->assertSame(380, (int) $player->currentxp);
        $this->assertSame($this->reachedat, (int) $player->timemodified);
    }
}
