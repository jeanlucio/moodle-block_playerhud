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
 * Tests for the items controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use block_playerhud\local\external_items;
use stdClass;

/**
 * Tests for the items controller toggle/grant/revoke logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\controller\items
 * @covers     \block_playerhud\local\external_items
 */
final class items_test extends advanced_testcase {
    /** @var int Course ID created by the most recent make_instance() call. */
    protected int $lastcourseid = 0;

    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * The owning course ID is exposed via $this->lastcourseid for tests that need to enrol a
     * user in it (e.g. grant_item()'s enrolment check).
     *
     * @return int The new block instance ID.
     */
    protected function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $this->lastcourseid = (int) $course->id;
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
     * Creates an item carrying the given XP and enabled flag.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $xp XP awarded by the item.
     * @param int $enabled Enabled flag (1 or 0).
     * @return int The new item ID.
     */
    protected function make_item(int $instanceid, int $xp = 0, int $enabled = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Gem',
            'xp'              => $xp,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates a drop with the given usage limit and returns its ID.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $itemid Item the drop belongs to.
     * @param int $maxusage Usage limit (0 = infinite).
     * @return int The new drop ID.
     */
    protected function make_drop(int $instanceid, int $itemid, int $maxusage): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid'          => $itemid,
            'name'            => 'Spot',
            'maxusage'        => $maxusage,
            'respawntime'     => 0,
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
     * Seeds an inventory row and returns its ID.
     *
     * @param int $userid Holder user ID.
     * @param int $itemid Item held.
     * @param int $dropid Originating drop (0 = none).
     * @param string $source Inventory source tag.
     * @param int $xpawarded XP actually paid out for this copy.
     * @return int The new inventory row ID.
     */
    protected function seed_inventory(
        int $userid,
        int $itemid,
        int $dropid,
        string $source,
        int $xpawarded = 0
    ): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_inventory', (object) [
            'userid'      => $userid,
            'itemid'      => $itemid,
            'dropid'      => $dropid,
            'source'      => $source,
            'timecreated' => time(),
            'xpawarded'   => $xpawarded,
        ]);
    }

    /**
     * Seeds a trade with the given requirement and reward items.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int[] $reqitemids Requirement item IDs.
     * @param int[] $rewarditemids Reward item IDs.
     * @return int The new trade ID.
     */
    protected function seed_trade_with(int $instanceid, array $reqitemids, array $rewarditemids): int {
        global $DB;

        $tradeid = (int) $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Trade',
            'timecreated'     => time(),
        ]);
        foreach ($reqitemids as $iid) {
            $DB->insert_record('block_playerhud_trade_reqs', (object) ['tradeid' => $tradeid, 'itemid' => $iid, 'qty' => 1]);
        }
        foreach ($rewarditemids as $iid) {
            $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $iid, 'qty' => 1]);
        }

        return $tradeid;
    }

    /**
     * Toggling an item flips its enabled flag and reports success.
     */
    public function test_toggle_item_flips_enabled(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 0, 1);

        $result = items::toggle_item($itemid, $instanceid);

        $this->assertTrue($result);
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_items', 'enabled', ['id' => $itemid]));
    }

    /**
     * Toggling an item of another instance changes nothing and reports failure.
     */
    public function test_toggle_item_foreign_instance_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itemid = $this->make_item($instancea, 0, 1);

        $result = items::toggle_item($itemid, $instanceb);

        $this->assertFalse($result);
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_items', 'enabled', ['id' => $itemid]));
    }

    /**
     * Granting an item records a teacher-sourced ledger entry and awards its XP.
     */
    public function test_grant_item_adds_inventory_and_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $courseid = $this->lastcourseid;
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid);
        $this->seed_player($instanceid, (int) $user->id, 50);

        items::grant_item($itemid, (int) $user->id, $instanceid, $courseid);

        $log = $DB->get_record('block_playerhud_stack_log', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame($itemid, (int) $log->itemid);
        $this->assertSame(0, (int) $log->dropid);
        $this->assertSame('teacher', $log->source);
        $this->assertSame(30, (int) $log->xpawarded);
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));
        $this->assertSame(80, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Granting a zero-XP item records the ledger entry without changing player XP.
     */
    public function test_grant_item_zero_xp_keeps_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $courseid = $this->lastcourseid;
        $itemid = $this->make_item($instanceid, 0);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid);
        $this->seed_player($instanceid, (int) $user->id, 50);

        items::grant_item($itemid, (int) $user->id, $instanceid, $courseid);

        $this->assertSame(1, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_stack_log', 'xpawarded', ['userid' => $user->id]));
        $this->assertSame(50, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Granting an item of another instance is rejected before any side effect.
     */
    public function test_grant_item_rejects_foreign_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $courseb = $this->lastcourseid;
        $itemid = $this->make_item($instancea, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseb);

        try {
            items::grant_item($itemid, (int) $user->id, $instanceb, $courseb);
            $this->fail('Expected a dml_missing_record_exception.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        }
    }

    /**
     * Regression test for the security-audit finding: grant_item() must reject a userid that
     * is not enrolled in the block instance's own course, before any inventory/XP side effect.
     */
    public function test_grant_item_rejects_unenrolled_user(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $courseid = $this->lastcourseid;
        $itemid = $this->make_item($instanceid, 30);
        // Deliberately not enrolled in $courseid.
        $user = $this->getDataGenerator()->create_user();

        try {
            items::grant_item($itemid, (int) $user->id, $instanceid, $courseid);
            $this->fail('Expected a moodle_exception for an unenrolled user.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invaliduserid', $e->errorcode);
            $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
            $this->assertSame(0, $DB->count_records('block_playerhud_user', ['userid' => $user->id]));
        }
    }

    /**
     * Revoking a finite-drop item marks it revoked and deducts its XP.
     */
    public function test_revoke_item_marks_revoked_and_deducts_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $dropid = $this->make_drop($instanceid, $itemid, 5);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        $invid = $this->seed_inventory((int) $user->id, $itemid, $dropid, 'map', 30);

        items::revoke_item($invid, $instanceid);

        $this->assertSame('revoked', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid]));
        $this->assertSame(70, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Revoking a copy with xpawarded = 0 (e.g. an infinite drop) marks it revoked but keeps the
     * XP intact — revoke_item() no longer inspects the drop at all, it only reads what was
     * actually recorded for this copy.
     */
    public function test_revoke_item_infinite_drop_keeps_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $dropid = $this->make_drop($instanceid, $itemid, 0);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        $invid = $this->seed_inventory((int) $user->id, $itemid, $dropid, 'map', 0);

        items::revoke_item($invid, $instanceid);

        $this->assertSame('revoked', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid]));
        $this->assertSame(100, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Revoking a copy deducts what was actually recorded at grant time, not the item's
     * current xp — so editing the item afterwards never changes what a revoke deducts.
     */
    public function test_revoke_item_deducts_recorded_xp_not_current_item_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 100);
        $dropid = $this->make_drop($instanceid, $itemid, 5);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 150);
        $invid = $this->seed_inventory((int) $user->id, $itemid, $dropid, 'map', 100);

        // Item is edited after the grant: revoke must still deduct the original 100, not 30.
        $DB->set_field('block_playerhud_items', 'xp', 30, ['id' => $itemid]);

        items::revoke_item($invid, $instanceid);

        $this->assertSame(50, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Revoking an inventory row of another instance changes nothing.
     */
    public function test_revoke_item_foreign_instance_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itemid = $this->make_item($instancea, 30);
        $dropid = $this->make_drop($instancea, $itemid, 5);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instancea, (int) $user->id, 100);
        $invid = $this->seed_inventory((int) $user->id, $itemid, $dropid, 'map');

        items::revoke_item($invid, $instanceb);

        $this->assertSame('map', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $invid]));
        $this->assertSame(100, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instancea,
            'userid'          => $user->id,
        ]));
    }

    /**
     * grant_item() with $qty > 1 grants that many units in one ledger entry and awards XP
     * scaled by the same quantity.
     */
    public function test_grant_item_with_qty_grants_multiple_units(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $courseid = $this->lastcourseid;
        $itemid = $this->make_item($instanceid, 10);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid);
        $this->seed_player($instanceid, (int) $user->id, 0);

        items::grant_item($itemid, (int) $user->id, $instanceid, $courseid, 5);

        $this->assertSame(5, external_items::get_available_quantity($instanceid, $itemid, (int) $user->id));
        $this->assertSame(50, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Revoking a block_playerhud_stack_log entry decrements the balance by its delta and
     * deducts its recorded xpawarded — the storage generation every grant_item()/quest
     * reward/trade reward/drop collection writes to from now on.
     */
    public function test_revoke_stack_log_entry_decrements_balance_and_deducts_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        $logid = external_items::grant($instanceid, $itemid, (int) $user->id, 2, 'teacher', false);

        $this->assertTrue(items::revoke_stack_log_entry($logid, $instanceid));

        $this->assertSame(0, external_items::get_available_quantity($instanceid, $itemid, (int) $user->id));
        // Net zero: the grant of 2x30xp added 60, the revoke deducts that same 60 back.
        $this->assertSame(100, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
        // Revoking is recorded as a new negative-delta row, never by deleting the original.
        $this->assertSame(2, $DB->count_records('block_playerhud_stack_log', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));
    }

    /**
     * Revoking a stack_log entry only removes what remains in the balance — an item already
     * partly spent since this grant is capped at the current balance rather than driving it
     * negative. The XP deducted still matches this entry's full recorded amount (the accepted
     * simplification: revoke does not reconstruct XP per remaining unit).
     */
    public function test_revoke_stack_log_entry_caps_removal_at_current_balance(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 200);
        $logid = external_items::grant($instanceid, $itemid, (int) $user->id, 5, 'teacher', false);
        external_items::consume($instanceid, $itemid, (int) $user->id, 3);

        $this->assertTrue(items::revoke_stack_log_entry($logid, $instanceid));

        $this->assertSame(0, external_items::get_available_quantity($instanceid, $itemid, (int) $user->id));
        // Net zero: the grant of 5x30xp added 150, the revoke deducts that same full 150 back
        // even though only 2 of the 5 units were still in the balance to actually remove.
        $this->assertSame(200, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * A negative-delta (consume) log row has nothing to give back and is a no-op.
     */
    public function test_revoke_stack_log_entry_noop_for_consume_row(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        external_items::grant($instanceid, $itemid, (int) $user->id, 3, 'teacher', false);
        external_items::consume($instanceid, $itemid, (int) $user->id, 1);

        global $DB;
        $consumelogid = (int) $DB->get_field('block_playerhud_stack_log', 'id', [
            'userid' => $user->id, 'itemid' => $itemid, 'delta' => -1,
        ]);

        $this->assertFalse(items::revoke_stack_log_entry($consumelogid, $instanceid));

        $this->assertSame(2, external_items::get_available_quantity($instanceid, $itemid, (int) $user->id));
    }

    /**
     * Revoking the same grant entry twice is a safe no-op the second time — it must not remove
     * a second batch of units from an unrelated later grant, nor deduct XP twice. This is the
     * exact scenario a teacher can trigger by clicking an already-processed revoke link again
     * (the UI leaves the button visible after a revoke, since the append-only ledger design
     * does not retroactively mark the original grant row as spent).
     */
    public function test_revoke_stack_log_entry_twice_is_idempotent_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 100);
        $logid = external_items::grant($instanceid, $itemid, (int) $user->id, 2, 'teacher', false);

        $this->assertTrue(items::revoke_stack_log_entry($logid, $instanceid));
        $this->assertFalse(items::revoke_stack_log_entry($logid, $instanceid));

        $this->assertSame(0, external_items::get_available_quantity($instanceid, $itemid, (int) $user->id));
        $this->assertSame(100, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Revoking a stack_log entry belonging to another instance changes nothing.
     */
    public function test_revoke_stack_log_entry_foreign_instance_is_noop(): void {
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itemid = $this->make_item($instancea, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instancea, (int) $user->id, 100);
        $logid = external_items::grant($instancea, $itemid, (int) $user->id, 1, 'teacher', false);

        $this->assertFalse(items::revoke_stack_log_entry($logid, $instanceb));

        $this->assertSame(1, external_items::get_available_quantity($instancea, $itemid, (int) $user->id));
    }

    /**
     * A trade keeping other items after the removal is reported as surviving.
     */
    public function test_find_affected_surviving_trades_returns_trimmed_trade(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $deleted = $this->make_item($instanceid);
        $other = $this->make_item($instanceid);
        // Reward side has both items; removing $deleted still leaves $other.
        $tradeid = $this->seed_trade_with($instanceid, [$other], [$deleted, $other]);

        $surviving = items::find_affected_surviving_trades($instanceid, [$deleted]);

        $this->assertArrayHasKey($tradeid, $surviving);
    }

    /**
     * A trade that would be orphaned is excluded from the surviving list.
     */
    public function test_find_affected_surviving_trades_excludes_orphaned(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $deleted = $this->make_item($instanceid);
        $other = $this->make_item($instanceid);
        // Reward side has only the deleted item, so removing it empties that side.
        $tradeid = $this->seed_trade_with($instanceid, [$other], [$deleted]);

        $surviving = items::find_affected_surviving_trades($instanceid, [$deleted]);

        $this->assertArrayNotHasKey($tradeid, $surviving);
        // It is reported by the orphaned detector instead.
        $this->assertArrayHasKey($tradeid, items::find_orphaned_trades($instanceid, [$deleted]));
    }

    /**
     * A trade not referencing the deleted item is not reported as affected.
     */
    public function test_find_affected_surviving_trades_ignores_unrelated(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $deleted = $this->make_item($instanceid);
        $other = $this->make_item($instanceid);
        $this->seed_trade_with($instanceid, [$other], [$other]);

        $surviving = items::find_affected_surviving_trades($instanceid, [$deleted]);

        $this->assertEmpty($surviving);
    }

    /**
     * The XP impact aggregates only copies that actually earned XP, across all holders of
     * any of the given items — a copy from an infinite drop (xpawarded = 0) does not count.
     */
    public function test_find_xp_impact_aggregates_earned_copies_only(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itema = $this->make_item($instanceid, 100);
        $itemb = $this->make_item($instanceid, 50);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        $this->seed_inventory((int) $usera->id, $itema, 0, 'map', 100);
        $this->seed_inventory((int) $usera->id, $itemb, 0, 'map', 0);
        $this->seed_inventory((int) $userb->id, $itemb, 0, 'map', 50);

        $impact = items::find_xp_impact([$itema, $itemb]);

        $this->assertSame(2, $impact->studentcount);
        $this->assertSame(150, $impact->totalxp);
    }

    /**
     * No matching inventory rows produce a zero-impact summary.
     */
    public function test_find_xp_impact_empty_for_unheld_item(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 100);

        $impact = items::find_xp_impact([$itemid]);

        $this->assertSame(0, $impact->studentcount);
        $this->assertSame(0, $impact->totalxp);
    }

    /**
     * An empty item list is a no-op, not a DB error.
     */
    public function test_find_xp_impact_empty_ids_returns_zero(): void {
        $this->resetAfterTest();

        $impact = items::find_xp_impact([]);

        $this->assertSame(0, $impact->studentcount);
        $this->assertSame(0, $impact->totalxp);
    }
}
