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
 * @package block_playerhud
 * @coversDefaultClass \block_playerhud\local\external_items
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
     * An item belonging to the given block instance is recognised as such.
     *
     * @covers ::belongs_to_instance
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
     * @covers ::belongs_to_instance
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
     * @covers ::belongs_to_instance
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
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_false_for_nonexistent_item(): void {
        $instanceid = $this->make_instance();

        $this->assertFalse(external_items::belongs_to_instance(999999, $instanceid));
    }

    /**
     * Zero or negative IDs are rejected without querying the database.
     *
     * @covers ::belongs_to_instance
     * @return void
     */
    public function test_belongs_to_instance_false_for_zero_ids(): void {
        $this->assertFalse(external_items::belongs_to_instance(0, 0));
        $this->assertFalse(external_items::belongs_to_instance(-1, -1));
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
     * Granting a valid, enabled item inserts one row per unit, each carrying its own
     * xpawarded, and credits the total XP once.
     *
     * @covers ::grant
     * @return void
     */
    public function test_grant_inserts_rows_and_awards_xp_for_own_enabled_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 2, 'playerwords', false);

        $this->assertTrue($result);
        $rows = array_values($DB->get_records('block_playerhud_inventory', ['userid' => $user->id, 'itemid' => $itemid]));
        $this->assertCount(2, $rows);
        $this->assertSame(30, (int)$rows[0]->xpawarded);
        $this->assertSame(30, (int)$rows[1]->xpawarded);
        $this->assertSame('playerwords', $rows[0]->source);

        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $instanceid,
            'userid'          => $user->id,
        ]);
        $this->assertSame(60, (int)$currentxp);
    }

    /**
     * Granting with $suppressxp still creates the inventory rows, but withholds XP — the
     * anti-farming rule an external plugin applies for its own unbounded sources.
     *
     * @covers ::grant
     * @return void
     */
    public function test_grant_withholds_xp_when_suppressxp(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', true);

        $this->assertTrue($result);
        $row = $DB->get_record('block_playerhud_inventory', ['userid' => $user->id, 'itemid' => $itemid]);
        $this->assertSame(0, (int)$row->xpawarded);
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
     * @covers ::grant
     * @return void
     */
    public function test_grant_noop_for_other_instance_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($otherinstanceid, 30);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        $this->assertFalse($result);
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
    }

    /**
     * Granting a disabled item is a no-op — disabling stops new acquisition through every
     * channel, including external grants.
     *
     * @covers ::grant
     * @return void
     */
    public function test_grant_noop_for_disabled_item(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($instanceid, 30, 0);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::grant($instanceid, $itemid, $user->id, 1, 'playerwords', false);

        $this->assertFalse($result);
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
    }

    /**
     * Consuming a valid item with enough balance marks the oldest rows as consumed.
     *
     * @covers ::consume
     * @return void
     */
    public function test_consume_success_marks_rows_consumed(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        external_items::grant($instanceid, $itemid, $user->id, 2, 'playerwords', false);

        $result = external_items::consume($instanceid, $itemid, $user->id, 1);

        $this->assertTrue($result);
        $this->assertSame(1, $DB->count_records('block_playerhud_inventory', [
            'userid' => $user->id,
            'itemid' => $itemid,
            'source' => 'consumed',
        ]));
    }

    /**
     * Consuming more than the user holds returns false and leaves the inventory untouched.
     *
     * @covers ::consume
     * @return void
     */
    public function test_consume_returns_false_when_insufficient(): void {
        global $DB;

        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();

        $result = external_items::consume($instanceid, $itemid, $user->id, 1);

        $this->assertFalse($result);
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
    }

    /**
     * Consuming an item that does not belong to the given instance returns null, distinctly
     * from false — the caller waives the cost instead of blocking the user forever on an item
     * that can never be restocked.
     *
     * @covers ::consume
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
     * @covers ::get_name
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
     * @covers ::get_name
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
     * @covers ::get_xp
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
     * @covers ::get_xp
     * @return void
     */
    public function test_get_xp_returns_zero_for_other_instance_item(): void {
        $instanceid = $this->make_instance();
        $otherinstanceid = $this->make_instance();
        $itemid = $this->make_item_with_xp($otherinstanceid, 30);

        $this->assertSame(0, external_items::get_xp($instanceid, $itemid));
    }

    /**
     * get_available_quantity() counts only rows not revoked or consumed, for an item
     * belonging to the given instance.
     *
     * @covers ::get_available_quantity
     * @return void
     */
    public function test_get_available_quantity_counts_only_active_rows(): void {
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $user = $this->getDataGenerator()->create_user();
        external_items::grant($instanceid, $itemid, $user->id, 3, 'playerwords', false);
        external_items::consume($instanceid, $itemid, $user->id, 1);

        $this->assertSame(2, external_items::get_available_quantity($instanceid, $itemid, $user->id));
    }

    /**
     * get_available_quantity() returns zero for an item belonging to a different instance,
     * even if the user happens to hold units of it.
     *
     * @covers ::get_available_quantity
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
