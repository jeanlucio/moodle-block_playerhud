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
 * Tests for the quests controller.
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
 * Tests for the quests controller toggle/delete logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\quests
 */
final class quests_test extends advanced_testcase {
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
     * Inserts a quest for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $rewardxp Reward XP granted on completion.
     * @param int $enabled Enabled flag (1 or 0).
     * @return int The new quest ID.
     */
    protected function seed_quest(int $instanceid, int $rewardxp = 0, int $enabled = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Quest',
            'type'            => 1,
            'requirement'     => 'xp',
            'reward_xp'       => $rewardxp,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Seeds a player progress row with the given XP for the block instance.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $xp Current XP.
     * @return void
     */
    protected function seed_player(int $instanceid, int $userid, int $xp): void {
        global $DB;

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $instanceid,
            'userid'          => $userid,
            'currentxp'       => $xp,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Logs one completion of a quest by a user.
     *
     * @param int $questid Quest ID.
     * @param int $userid User ID.
     * @param int $xpawarded XP actually paid out for this completion.
     * @return void
     */
    protected function seed_log(int $questid, int $userid, int $xpawarded = 0): void {
        global $DB;

        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid'     => $questid,
            'userid'      => $userid,
            'timecreated' => time(),
            'xpawarded'   => $xpawarded,
        ]);
    }

    /**
     * Toggling a quest flips its enabled flag and reports success.
     *
     * @covers ::toggle_quest
     */
    public function test_toggle_quest_flips_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questid = $this->seed_quest($instanceid, 0, 1);

        $result = quests::toggle_quest($questid, $instanceid);

        $this->assertTrue($result);
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_quests', 'enabled', ['id' => $questid]));
    }

    /**
     * Toggling a quest of another instance changes nothing and reports failure.
     *
     * @covers ::toggle_quest
     */
    public function test_toggle_quest_foreign_instance_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questid = $this->seed_quest($instancea, 0, 1);

        $result = quests::toggle_quest($questid, $instanceb);

        $this->assertFalse($result);
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_quests', 'enabled', ['id' => $questid]));
    }

    /**
     * Deleting a quest removes it, its log and reverts XP per completion.
     *
     * @covers ::delete_quest
     */
    public function test_delete_quest_removes_and_reverts_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questid = $this->seed_quest($instanceid, 40);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        // Two completions => deduct 2 * 40.
        $this->seed_log($questid, (int) $user->id, 40);
        $this->seed_log($questid, (int) $user->id, 40);

        $result = quests::delete_quest($questid, $instanceid);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('block_playerhud_quests', ['id' => $questid]));
        $this->assertSame(0, $DB->count_records('block_playerhud_quest_log', ['questid' => $questid]));
        $this->assertSame(20, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Deleting a zero-XP quest removes it without touching player XP.
     *
     * @covers ::delete_quest
     */
    public function test_delete_quest_zero_reward_keeps_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questid = $this->seed_quest($instanceid, 0);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        $this->seed_log($questid, (int) $user->id);

        quests::delete_quest($questid, $instanceid);

        $this->assertSame(100, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Deleting a quest reverts the XP actually recorded per completion, not the quest's
     * current reward_xp — so editing the reward afterwards never changes what a deletion
     * reverts.
     *
     * @covers ::delete_quest
     */
    public function test_delete_quest_reverts_recorded_xp_not_current_reward(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questid = $this->seed_quest($instanceid, 200);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 500);
        $this->seed_log($questid, (int) $user->id, 200);

        // Reward is edited after the claim: deletion must still deduct the original 200, not 50.
        $DB->set_field('block_playerhud_quests', 'reward_xp', 50, ['id' => $questid]);

        quests::delete_quest($questid, $instanceid);

        $this->assertSame(300, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * A quest owned by another instance is not deleted.
     *
     * @covers ::delete_quest
     */
    public function test_delete_quest_foreign_instance_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questid = $this->seed_quest($instancea, 40);

        $result = quests::delete_quest($questid, $instanceb);

        $this->assertFalse($result);
        $this->assertTrue($DB->record_exists('block_playerhud_quests', ['id' => $questid]));
    }

    /**
     * Bulk delete removes only owned quests, counts them and reverts their XP.
     *
     * @covers ::bulk_delete_quests
     */
    public function test_bulk_delete_quests_removes_only_owned_and_reverts_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questa1 = $this->seed_quest($instancea, 30);
        $questa2 = $this->seed_quest($instancea, 50);
        $questb = $this->seed_quest($instanceb, 70);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instancea, (int) $user->id, 100);
        $this->seed_log($questa1, (int) $user->id, 30);
        $this->seed_log($questa2, (int) $user->id, 50);

        $count = quests::bulk_delete_quests([$questa1, $questa2, $questb], $instancea);

        $this->assertSame(2, $count);
        $this->assertFalse($DB->record_exists('block_playerhud_quests', ['id' => $questa1]));
        $this->assertFalse($DB->record_exists('block_playerhud_quests', ['id' => $questa2]));
        $this->assertTrue($DB->record_exists('block_playerhud_quests', ['id' => $questb]));
        // 100 - (30 + 50) = 20.
        $this->assertSame(20, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instancea,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Bulk delete reverts the XP actually recorded per completion, not the quests' current
     * reward_xp.
     *
     * @covers ::bulk_delete_quests
     */
    public function test_bulk_delete_quests_reverts_recorded_xp_not_current_reward(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questa = $this->seed_quest($instanceid, 200);
        $questb = $this->seed_quest($instanceid, 100);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 500);
        $this->seed_log($questa, (int) $user->id, 200);
        $this->seed_log($questb, (int) $user->id, 100);

        // Both rewards are edited after the claims: must still deduct the original 300, not 20.
        $DB->set_field('block_playerhud_quests', 'reward_xp', 10, ['id' => $questa]);
        $DB->set_field('block_playerhud_quests', 'reward_xp', 10, ['id' => $questb]);

        quests::bulk_delete_quests([$questa, $questb], $instanceid);

        $this->assertSame(200, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Bulk delete with no ids deletes nothing and returns zero.
     *
     * @covers ::bulk_delete_quests
     */
    public function test_bulk_delete_quests_empty_returns_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $questid = $this->seed_quest($instanceid, 30);

        $count = quests::bulk_delete_quests([], $instanceid);

        $this->assertSame(0, $count);
        $this->assertTrue($DB->record_exists('block_playerhud_quests', ['id' => $questid]));
    }
}
