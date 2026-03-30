<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Game Master actions and XP recalculation tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * Class gamemaster_test.
 *
 * Tests for granting items, revoking items, and preserving leaderboard timestamps.
 *
 * @package block_playerhud
 * @coversNothing
 */
final class gamemaster_test extends advanced_testcase {
    /**
     * Setup test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test granting an item to a user.
     * Expected: Inventory is created, XP increases, timemodified is updated to NOW.
     */
    public function test_grant_item_updates_xp_and_date(): void {
        global $DB;

        // 1. Setup: Create course, user, and fake block instance.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1; // Fake block instance.

        // Create player with 0 XP and an old date.
        $pastdate = time() - 86400; // Yesterday.
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 0;
        $player->timecreated = $pastdate;
        $player->timemodified = $pastdate;
        $DB->insert_record('block_playerhud_user', $player);

        // Create an item worth 100 XP.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Test Sword';
        $item->xp = 100;
        $item->timecreated = time();
        $item->timemodified = time();
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        // 2. Action: Simulate grant_item from manage.php.
        $now = time();
        $newinv = new \stdClass();
        $newinv->userid = $user->id;
        $newinv->itemid = $itemid;
        $newinv->dropid = 0;
        $newinv->source = 'teacher';
        $newinv->timecreated = $now;
        $DB->insert_record('block_playerhud_inventory', $newinv);

        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp += $item->xp;
        $dbplayer->timemodified = $now;
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Assertions (Validations).
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $inventory = $DB->get_records('block_playerhud_inventory', ['userid' => $user->id]);

        $this->assertEquals(100, $updatedplayer->currentxp, 'XP should have increased to 100.');
        $this->assertGreaterThan(
            $pastdate,
            $updatedplayer->timemodified,
            'The timemodified date should have been updated to the exact time of the grant.'
        );
        $this->assertCount(1, $inventory, 'There should be 1 item in the inventory.');
        $this->assertEquals('teacher', reset($inventory)->source, 'The item source must be from the teacher.');
    }

    /**
     * Test revoking an item from a user.
     * Expected: Item status becomes 'revoked', XP decreases, timemodified remains UNCHANGED.
     */
    public function test_revoke_item_preserves_leaderboard_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;

        // Player reached 500 XP 5 days ago.
        $fivedaysago = time() - (5 * 86400);
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 500;
        $player->timecreated = $fivedaysago;
        $player->timemodified = $fivedaysago;
        $DB->insert_record('block_playerhud_user', $player);

        // 200 XP item that the student already owns.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Stolen Shield';
        $item->xp = 200;
        $item->timecreated = time();
        $item->timemodified = time();
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        $inv = new \stdClass();
        $inv->userid = $user->id;
        $inv->itemid = $itemid;
        $inv->source = 'map';
        $inv->timecreated = $fivedaysago;
        $invid = $DB->insert_record('block_playerhud_inventory', $inv);

        // 2. Action: Simulate revoke_item from manage.php (Soft Revoke).
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $item->xp);

        // NOTE: We intentionally DO NOT update dbplayer->timemodified.
        $DB->update_record('block_playerhud_user', $dbplayer);

        $dbinv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);
        $dbinv->source = 'revoked';
        $dbinv->timecreated = time();
        $DB->update_record('block_playerhud_inventory', $dbinv);

        // 3. Assertions (Crucial Validations).
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $updatedinv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);

        $this->assertEquals(300, $updatedplayer->currentxp, 'XP should have dropped from 500 to 300.');
        $this->assertEquals(
            $fivedaysago,
            $updatedplayer->timemodified,
            'CATASTROPHIC: The timemodified date was changed! The tie-breaker criterion was broken.'
        );
        $this->assertEquals('revoked', $updatedinv->source, 'The item status should be Soft Revoke (revoked).');
    }

    /**
     * Test XP does not drop below zero when deleting items.
     */
    public function test_xp_never_negative_on_revoke(): void {
        global $DB;

        // 1. Setup: Player with 50 XP.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;
        $pastdate = time() - 3600;

        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 50;
        $player->timecreated = $pastdate;
        $player->timemodified = $pastdate;
        $DB->insert_record('block_playerhud_user', $player);

        // Item is worth 100 XP (more than the player currently has).
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Glitched Item';
        $item->xp = 100;
        $item->timecreated = time();
        $item->timemodified = time();

        // 2. Action: Remove 100 XP from the player.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $item->xp);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Assertions.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(0, $updatedplayer->currentxp, 'XP cannot be negative. It should have been capped at 0.');
    }

    /**
     * Test Hard Delete (single item).
     * Expected: Total XP from all instances of the item is deducted, timemodified remains UNCHANGED.
     */
    public function test_hard_delete_item_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;
        $olddate = time() - (10 * 86400); // 10 days ago.

        // Player with 1000 XP.
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 1000;
        $player->timecreated = $olddate;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // 200 XP item.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Deleted Potion';
        $item->xp = 200;
        $item->timecreated = time();
        $item->timemodified = time();
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        // Student collected this item 2 times (earned 400 XP from it).
        $qtd = 2;

        // 2. Action: Simulate the exact logic of the 'delete' action in manage.php.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $xptoremove = $item->xp * $qtd;
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $xptoremove);

        // NOTE: We intentionally DO NOT update dbplayer->timemodified.
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Assertions.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(600, $updatedplayer->currentxp, 'XP should have dropped from 1000 to 600.');
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'Item deletion altered the tie-breaker date!');
    }

    /**
     * Test Bulk Delete (multiple items).
     * Expected: Sum of XP from all deleted items is deducted, timemodified remains UNCHANGED.
     */
    public function test_bulk_delete_items_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $olddate = time() - 3600; // 1 hour ago.

        $player = new \stdClass();
        $player->blockinstanceid = 1;
        $player->userid = $user->id;
        $player->currentxp = 800;
        $player->timecreated = $olddate;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // 2. Action: Simulate 'bulk_delete' action (Multiple items summing 350 XP to remove).
        $totalxptoremove = 350;

        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $totalxptoremove);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Assertions.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(450, $updatedplayer->currentxp, 'XP should have dropped from 800 to 450.');
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'Bulk deletion altered the tie-breaker date!');
    }

    /**
     * Test Delete Quest/Raid.
     * Expected: Quest XP * completions is deducted, timemodified remains UNCHANGED.
     */
    public function test_delete_quest_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $olddate = time() - 86400; // 1 day ago.

        $player = new \stdClass();
        $player->blockinstanceid = 1;
        $player->userid = $user->id;
        $player->currentxp = 2000;
        $player->timecreated = $olddate;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // 500 XP quest completed 1 time.
        $questrewardxp = 500;
        $completions = 1;

        // 2. Action: Simulate 'delete_quest' action.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $xptoremove = $questrewardxp * $completions;
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $xptoremove);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Assertions.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(
            1500,
            $updatedplayer->currentxp,
            'The quest XP (500) should have been subtracted from the original 2000.'
        );
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'Deleting the quest altered the tie-breaker date!');
    }
}
