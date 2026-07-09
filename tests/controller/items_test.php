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
use stdClass;

/**
 * Tests for the items controller toggle/grant/revoke logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\items
 */
final class items_test extends advanced_testcase {
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
     *
     * @covers ::toggle_item
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
     *
     * @covers ::toggle_item
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
     * Granting an item stores a teacher-sourced inventory row and awards its XP.
     *
     * @covers ::grant_item
     */
    public function test_grant_item_adds_inventory_and_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 50);

        items::grant_item($itemid, (int) $user->id, $instanceid);

        $inv = $DB->get_record('block_playerhud_inventory', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame($itemid, (int) $inv->itemid);
        $this->assertSame(0, (int) $inv->dropid);
        $this->assertSame('teacher', $inv->source);
        $this->assertSame(30, (int) $inv->xpawarded);
        $this->assertSame(80, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Granting a zero-XP item stores the row without changing player XP.
     *
     * @covers ::grant_item
     */
    public function test_grant_item_zero_xp_keeps_xp(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 0);
        $user = $this->getDataGenerator()->create_user();
        $this->seed_player($instanceid, (int) $user->id, 50);

        items::grant_item($itemid, (int) $user->id, $instanceid);

        $this->assertSame(1, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_inventory', 'xpawarded', ['userid' => $user->id]));
        $this->assertSame(50, (int) $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]));
    }

    /**
     * Granting an item of another instance is rejected before any side effect.
     *
     * @covers ::grant_item
     */
    public function test_grant_item_rejects_foreign_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itemid = $this->make_item($instancea, 30);
        $user = $this->getDataGenerator()->create_user();

        try {
            items::grant_item($itemid, (int) $user->id, $instanceb);
            $this->fail('Expected a dml_missing_record_exception.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        }
    }

    /**
     * Revoking a finite-drop item marks it revoked and deducts its XP.
     *
     * @covers ::revoke_item
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
     *
     * @covers ::revoke_item
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
     *
     * @covers ::revoke_item
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
     *
     * @covers ::revoke_item
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
     * A trade keeping other items after the removal is reported as surviving.
     *
     * @covers ::find_affected_surviving_trades
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
     *
     * @covers ::find_affected_surviving_trades
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
     *
     * @covers ::find_affected_surviving_trades
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
     *
     * @covers ::find_xp_impact
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
     *
     * @covers ::find_xp_impact
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
     *
     * @covers ::find_xp_impact
     */
    public function test_find_xp_impact_empty_ids_returns_zero(): void {
        $this->resetAfterTest();

        $impact = items::find_xp_impact([]);

        $this->assertSame(0, $impact->studentcount);
        $this->assertSame(0, $impact->totalxp);
    }
}
