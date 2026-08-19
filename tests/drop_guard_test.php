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
use block_playerhud\drop_guard;

/**
 * Tests for the drop pickup guard (limit and cooldown enforcement).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\drop_guard
 */
final class drop_guard_test extends advanced_testcase {
    /** @var int Dummy drop ID. */
    protected int $dropid;

    /** @var int Dummy user ID. */
    protected int $userid;

    /** @var int Dummy block instance ID. */
    protected int $instanceid;

    /**
     * Set up a minimal environment for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = time();
        $bi->timemodified = time();
        $this->instanceid = $DB->insert_record('block_instances', $bi);

        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = 'Pill';
        $item->xp = 0;
        $item->image = '';
        $item->description = '';
        $item->enabled = 1;
        $item->secret = 0;
        $item->timecreated = time();
        $item->timemodified = time();
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        $drop = new \stdClass();
        $drop->blockinstanceid = $this->instanceid;
        $drop->itemid = $itemid;
        $drop->name = 'Forum drop';
        $drop->maxusage = 5;
        $drop->respawntime = 0;
        $drop->timecreated = time();
        $drop->timemodified = time();
        $this->dropid = $DB->insert_record('block_playerhud_drops', $drop);

        $user = $this->getDataGenerator()->create_user();
        $this->userid = $user->id;
    }

    /**
     * Inserts N legacy inventory records for the configured drop and user.
     *
     * @param int $qty Number of records to insert.
     * @param string $source Source value for each record.
     */
    protected function seed_pickup_records(int $qty, string $source = 'map'): void {
        global $DB;
        $records = [];
        for ($i = 0; $i < $qty; $i++) {
            $records[] = (object)[
                'userid'      => $this->userid,
                'dropid'      => $this->dropid,
                'itemid'      => 0,
                'source'      => $source,
                'timecreated' => time() - $i,
            ];
        }
        $DB->insert_records('block_playerhud_inventory', $records);
    }

    /**
     * Inserts N block_playerhud_stack_log pickup records for the configured drop and user —
     * the storage generation every new collection uses from now on.
     *
     * @param int $qty Number of records to insert.
     * @param int $offset Seconds to subtract from time() on the first record (each subsequent
     *        one one second older), so callers can control ordering relative to legacy rows.
     */
    protected function seed_new_pickup_records(int $qty, int $offset = 0): void {
        global $DB;
        $records = [];
        for ($i = 0; $i < $qty; $i++) {
            $records[] = (object)[
                'userid'      => $this->userid,
                'itemid'      => 0,
                'dropid'      => $this->dropid,
                'delta'       => 1,
                'source'      => 'map',
                'xpawarded'   => 0,
                'timecreated' => time() - $offset - $i,
            ];
        }
        $DB->insert_records('block_playerhud_stack_log', $records);
    }

    /**
     * Test 1: Pickup is allowed when the student has not reached the limit.
     */
    public function test_pickup_allowed_below_limit(): void {
        $this->seed_pickup_records(4);
        // Must not throw.
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
        $this->assertTrue(true);
    }

    /**
     * Test 2: Pickup is blocked when the limit is reached via active items.
     */
    public function test_pickup_blocked_at_limit(): void {
        $this->seed_pickup_records(5);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionCode(0);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
    }

    /**
     * Test 3: Pickup is blocked when the limit is reached via CONSUMED items.
     *
     * This is the core regression test: items traded away (source='consumed')
     * must still count towards the drop limit so the student cannot re-collect
     * after spending their pickups in a trade.
     */
    public function test_pickup_blocked_after_items_consumed_in_trade(): void {
        $this->seed_pickup_records(5, 'consumed');

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
    }

    /**
     * Test 4: Pickup is blocked by a mix of active and consumed records.
     *
     * Student collected 3 (active) + traded 2 (consumed) = 5 total.
     * Must not be allowed to collect a 6th.
     */
    public function test_pickup_blocked_mixed_active_and_consumed(): void {
        $this->seed_pickup_records(3, 'map');
        $this->seed_pickup_records(2, 'consumed');

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
    }

    /**
     * Test 5: Unlimited drops (maxusage = 0) are never blocked by count.
     */
    public function test_pickup_unlimited_drop_never_blocked(): void {
        $this->seed_pickup_records(100, 'map');
        // Must not throw for unlimited drop.
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 0, 0);
        $this->assertTrue(true);
    }

    /**
     * Test 6: Cooldown blocks pickup before the respawn time elapses.
     */
    public function test_pickup_blocked_by_cooldown(): void {
        global $DB;
        // Insert a record timestamped "now" with a 1-hour cooldown.
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $this->userid,
            'dropid'      => $this->dropid,
            'itemid'      => 0,
            'source'      => 'map',
            'timecreated' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 3600);
    }

    /**
     * Test 7: Cooldown allows pickup after the respawn time has elapsed.
     */
    public function test_pickup_allowed_after_cooldown(): void {
        global $DB;
        // Insert a record timestamped 2 hours ago.
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $this->userid,
            'dropid'      => $this->dropid,
            'itemid'      => 0,
            'source'      => 'map',
            'timecreated' => time() - 7200,
        ]);

        // 1-hour cooldown has passed — must not throw.
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 3600);
        $this->assertTrue(true);
    }

    /**
     * Test 8: the limit is enforced from block_playerhud_stack_log alone — the storage
     * generation every new collection writes to.
     */
    public function test_pickup_blocked_at_limit_from_new_storage_alone(): void {
        $this->seed_new_pickup_records(5);

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
    }

    /**
     * Test 9: the limit is enforced by summing legacy inventory rows and new ledger rows — a
     * user who collected before and after the storage cutover cannot exceed maxusage across
     * the two combined. This is the exact scenario drop_guard::get_pickup_count() exists for:
     * without it, a drop with maxusage reached partly via legacy rows would look like it still
     * has pickups left, because the new storage alone would undercount.
     */
    public function test_pickup_blocked_by_combining_legacy_and_new_storage(): void {
        $this->seed_pickup_records(3, 'map');
        $this->seed_new_pickup_records(2);

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
    }

    /**
     * Test 10: below the limit when combining both sources, pickup is still allowed.
     */
    public function test_pickup_allowed_below_limit_combining_both_sources(): void {
        $this->seed_pickup_records(2, 'map');
        $this->seed_new_pickup_records(1);

        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 0);
        $this->assertTrue(true);
    }

    /**
     * Test 11: cooldown is enforced against the most recent pickup across both sources, even
     * when that most recent pickup is the new one and older rows are legacy.
     */
    public function test_pickup_blocked_by_cooldown_from_most_recent_new_pickup(): void {
        $this->seed_pickup_records(1, 'map');
        // The legacy helper always timestamps "now"; simulate the new pickup happening after
        // it by not offsetting it at all while the legacy row already carries an offset of 0 —
        // insert the legacy row further in the past explicitly instead.
        global $DB;
        $DB->set_field('block_playerhud_inventory', 'timecreated', time() - 7200, [
            'userid' => $this->userid, 'dropid' => $this->dropid,
        ]);
        $this->seed_new_pickup_records(1);

        $this->expectException(\moodle_exception::class);
        drop_guard::check_pickup_allowed($this->dropid, $this->userid, 5, 3600);
    }
}
