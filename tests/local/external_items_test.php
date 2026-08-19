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
 * Tests for the external_items API.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;
use stdClass;

/**
 * Tests for external_items — requires database.
 *
 * grant() writes only to block_playerhud_stack/block_playerhud_stack_log from now on;
 * block_playerhud_inventory is never written here again — it only ever holds quantity
 * granted before this mechanism existed. Several tests below insert directly into
 * block_playerhud_inventory (bypassing grant()) specifically to simulate that pre-existing
 * "legacy" state, since nothing in this plugin migrates it forward on its own.
 *
 * @package block_playerhud
 * @covers     \block_playerhud\local\external_items
 */
final class external_items_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    private function make_instance(): int {
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
     * Creates an item belonging to the given block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $enabled Enabled flag (1 or 0).
     * @return int The new item ID.
     */
    private function make_item(int $instanceid, int $enabled = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Gold Key',
            'xp'              => 0,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Creates an item with a given XP value, belonging to the given block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $xp XP awarded per unit.
     * @param int $enabled Enabled flag (1 or 0).
     * @return int The new item ID.
     */
    private function make_item_with_xp(int $instanceid, int $xp, int $enabled = 1): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Gold Key',
            'xp'              => $xp,
            'enabled'         => $enabled,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Inserts $qty legacy block_playerhud_inventory rows directly, bypassing grant(), to
     * simulate quantity a user already held before block_playerhud_stack existed.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID.
     * @param int $qty Number of rows to insert.
     * @param int $offset Seconds to subtract from time() on every row, so callers can control
     *        FIFO order relative to other rows inserted separately.
     * @return void
     */
    private function make_legacy_inventory(int $userid, int $itemid, int $qty, int $offset = 0): void {
        global $DB;

        for ($i = 0; $i < $qty; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid'      => $userid,
                'itemid'      => $itemid,
                'dropid'      => 0,
                'source'      => 'map',
                'timecreated' => time() - $offset,
                'xpawarded'   => 0,
            ]);
        }
    }

    /**
     * An item belonging to the given block instance is recognised as such.
     *
     * @return void
     */
    public function test_belongs_to_instance_true_for_own_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);

        $this->assertTrue(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * A disabled item still belongs to its instance — belongs_to_instance() only checks
     * ownership, not the enabled flag (callers that care about enabled, e.g. grant(), check
     * that separately).
     *
     * @return void
     */
    public function test_belongs_to_instance_true_for_disabled_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid, 0);

        $this->assertTrue(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * An item belonging to a different block instance is rejected — the exact cross-course
     * leak scenario this class exists to prevent.
     *
     * @return void
     */
    public function test_belongs_to_instance_false_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item($otherinstanceid);

        $this->assertFalse(external_items::belongs_to_instance($itemid, $instanceid));
    }

    /**
     * A nonexistent item ID is rejected.
     *
     * @return void
     */
    public function test_belongs_to_instance_false_for_nonexistent_item(): void {
        $instanceid = $this->make_instance();

        $this->assertFalse(external_items::belongs_to_instance(999999, $instanceid));
    }

    /**
     * Zero or negative IDs are rejected without querying the database.
     *
     * @return void
     */
    public function test_belongs_to_instance_false_for_zero_ids(): void {
        $this->assertFalse(external_items::belongs_to_instance(0, 0));
        $this->assertFalse(external_items::belongs_to_instance(-1, -1));
    }

    /**
     * Granting a valid, enabled item increments block_playerhud_stack.qty, appends one
     * block_playerhud_stack_log row carrying the total xpawarded for this action, credits the
     * total XP once, and never touches block_playerhud_inventory.
     *
     * @return void
     */
    public function test_grant_updates_stack_and_awards_xp_for_own_enabled_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $logid = external_items::grant($instanceid, $itemid, $user->id, 2, 'playerwords', false);

        $this->assertGreaterThan(0, $logid);
        $this->assertSame(2, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));

        $log = $DB->get_record('block_playerhud_stack_log', ['id' => $logid], '*', MUST_EXIST);
        $this->assertSame(2, (int) $log->delta);
        $this->assertSame('playerwords', $log->source);
        $this->assertSame(60, (int) $log->xpawarded);

        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));

        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertSame(60, (int) $currentxp);
    }

    /**
     * A grant carrying a dropid records it on the ledger row, so drop_guard can enforce a
     * drop's own pickup limit/cooldown regardless of storage generation.
     *
     * @return void
     */
    public function test_grant_records_dropid_on_log_row(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();

        $logid = external_items::grant($instanceid, $itemid, $user->id, 1, 'map', false, 42);

        $this->assertSame(42, (int) $DB->get_field('block_playerhud_stack_log', 'dropid', ['id' => $logid]));
    }

    /**
     * Regression guard: granting a larger quantity must not cost more queries — the balance
     * upsert and the single ledger row are the same shape regardless of $qty.
     *
     * @return void
     */
    public function test_grant_does_not_scale_queries_with_quantity(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 5);
        $usersmall = $this->getDataGenerator()->create_user();
        $userlarge = $this->getDataGenerator()->create_user();

        // Warm up caches (capability checks, config, context) shared by every call below, so
        // the two measurements that follow are not skewed by first-call-only overhead — that
        // asymmetry is exactly what made this comparison flaky before this warmup was added.
        $warmupuser = $this->getDataGenerator()->create_user();
        external_items::grant($instanceid, $itemid, $warmupuser->id, 1, 'playerwords', false);

        $before = $DB->perf_get_queries();
        external_items::grant($instanceid, $itemid, $usersmall->id, 2, 'playerwords', false);
        $smallqty = $DB->perf_get_queries() - $before;

        $before = $DB->perf_get_queries();
        external_items::grant($instanceid, $itemid, $userlarge->id, 2000, 'playerwords', false);
        $largeqty = $DB->perf_get_queries() - $before;

        $this->assertSame(2000, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $userlarge->id, 'itemid' => $itemid,
        ]));
        $this->assertSame(
            $smallqty,
            $largeqty,
            'Granting 1000x more units must not cost more queries.'
        );
    }

    /**
     * Granting with $suppressxp still updates the balance, but withholds XP — the
     * anti-farming rule an external plugin applies for its own unbounded sources.
     *
     * @return void
     */
    public function test_grant_withholds_xp_when_suppressxp(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $logid = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', true);

        $this->assertGreaterThan(0, $logid);
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_stack_log', 'xpawarded', ['id' => $logid]));
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertFalse($currentxp);
    }

    /**
     * Granting an item belonging to a different block instance is a no-op — the exact
     * cross-course leak this class exists to prevent.
     *
     * @return void
     */
    public function test_grant_noop_for_other_instance_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($otherinstanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        $this->assertSame(0, $result);
        $this->assertSame(0, $DB->count_records('block_playerhud_stack', ['userid' => $user->id]));
    }

    /**
     * Granting a disabled item is a no-op — disabling stops new acquisition through every
     * channel, including external grants.
     *
     * @return void
     */
    public function test_grant_noop_for_disabled_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30, 0);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        $this->assertSame(0, $result);
        $this->assertSame(0, $DB->count_records('block_playerhud_stack', ['userid' => $user->id]));
    }

    /**
     * Consuming with enough balance in block_playerhud_stack alone spends from it, appends a
     * negative-delta ledger row, and never touches block_playerhud_inventory.
     *
     * @return void
     */
    public function test_consume_spends_from_stack_first(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        external_items::grant($instanceid, $itemid, $user->id, 2, 'playerwords', false);

        $result = external_items::consume($instanceid, $itemid, $user->id, 1);

        $this->assertTrue($result);
        $this->assertSame(1, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        $this->assertSame(1, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }

    /**
     * A user whose entire balance sits in the legacy inventory table (nothing migrates it
     * forward automatically) can still spend it — consume() falls back to the same FIFO
     * mark-as-consumed mechanism it always used once block_playerhud_stack has nothing to
     * offer.
     *
     * @return void
     */
    public function test_consume_falls_back_to_legacy_inventory_when_stack_is_empty(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        $this->make_legacy_inventory($user->id, $itemid, 3);

        $result = external_items::consume($instanceid, $itemid, $user->id, 2);

        $this->assertTrue($result);
        $this->assertSame(0, $DB->count_records('block_playerhud_stack', ['userid' => $user->id]));
        $this->assertSame(2, $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source = 'consumed'",
            ['uid' => $user->id, 'iid' => $itemid]
        ));
        $this->assertSame(1, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }

    /**
     * The delicate case flagged in the design: a balance split across both tables must spend
     * everything available in block_playerhud_stack before falling back to legacy rows for the
     * remainder — never insufficient just because one source alone would not cover it.
     *
     * @return void
     */
    public function test_consume_spends_across_both_sources_when_split(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        $this->make_legacy_inventory($user->id, $itemid, 2);
        external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        // Total available is 3 (2 legacy + 1 new); consume 3 to force both sources to empty.
        $result = external_items::consume($instanceid, $itemid, $user->id, 3);

        $this->assertTrue($result);
        $this->assertSame(0, (int) $DB->get_field('block_playerhud_stack', 'qty', [
            'userid' => $user->id, 'itemid' => $itemid,
        ]));
        $this->assertSame(2, $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source = 'consumed'",
            ['uid' => $user->id, 'iid' => $itemid]
        ));
        $this->assertSame(0, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }

    /**
     * Consuming more than the combined balance across both sources returns false and leaves
     * both tables untouched.
     *
     * @return void
     */
    public function test_consume_returns_false_when_insufficient_across_both_sources(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        $this->make_legacy_inventory($user->id, $itemid, 1);
        external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        $result = external_items::consume($instanceid, $itemid, $user->id, 3);

        $this->assertFalse($result);
        $this->assertSame(2, external_items::get_available_quantity($instanceid, $itemid, $user->id));
        $this->assertSame(0, $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source = 'consumed'",
            ['uid' => $user->id, 'iid' => $itemid]
        ));
    }

    /**
     * Consuming an item that does not belong to the given instance returns null, distinctly
     * from false — the caller waives the cost instead of blocking the user forever on an item
     * that can never be restocked.
     *
     * @return void
     */
    public function test_consume_returns_null_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item($otherinstanceid);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::consume($instanceid, $itemid, $user->id, 1);

        $this->assertNull($result);
    }

    /**
     * get_name() returns the formatted name for an item belonging to the given instance.
     *
     * @return void
     */
    public function test_get_name_returns_name_for_own_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);

        $this->assertSame('Gold Key', external_items::get_name($instanceid, $itemid));
    }

    /**
     * get_name() returns an empty string for an item belonging to a different instance.
     *
     * @return void
     */
    public function test_get_name_returns_empty_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item($otherinstanceid);

        $this->assertSame('', external_items::get_name($instanceid, $itemid));
    }

    /**
     * get_xp() returns the item's own XP value for an item belonging to the given instance.
     *
     * @return void
     */
    public function test_get_xp_returns_value_for_own_item(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30);

        $this->assertSame(30, external_items::get_xp($instanceid, $itemid));
    }

    /**
     * get_xp() returns zero for an item belonging to a different instance.
     *
     * @return void
     */
    public function test_get_xp_returns_zero_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($otherinstanceid, 30);

        $this->assertSame(0, external_items::get_xp($instanceid, $itemid));
    }

    /**
     * get_available_quantity() sums block_playerhud_inventory (legacy, excluding
     * revoked/consumed) and block_playerhud_stack.qty, for an item belonging to the given
     * instance.
     *
     * @return void
     */
    public function test_get_available_quantity_sums_both_sources(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        $this->make_legacy_inventory($user->id, $itemid, 2);
        external_items::grant($instanceid, $itemid, $user->id, 3, 'playerwords', false);
        external_items::consume($instanceid, $itemid, $user->id, 1);

        // 2 legacy + 3 granted - 1 consumed (from the new balance first) = 4.
        $this->assertSame(4, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }

    /**
     * get_available_quantity() returns zero for an item belonging to a different instance,
     * even if the user happens to hold units of it.
     *
     * @return void
     */
    public function test_get_available_quantity_zero_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item($otherinstanceid);
        $user = $this->getDataGenerator()->create_user();
        external_items::grant($otherinstanceid, $itemid, $user->id, 1, 'playerwords', false);

        $this->assertSame(0, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }
}
