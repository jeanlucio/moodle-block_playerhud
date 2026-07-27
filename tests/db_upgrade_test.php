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

/**
 * Unit tests for the block_playerhud upgrade steps that carry real data-migration logic,
 * as opposed to plain schema DDL (which install.xml already exercises on every test run).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_block_playerhud_upgrade
 */
final class db_upgrade_test extends advanced_testcase {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Resets the test environment and pulls in the upgrade function for direct calls.
     */
    protected function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        // The savepoint helper lives in upgradelib.php, only autoloaded by the real upgrade
        // runner — calling the upgrade function directly in a test needs it pulled in by hand.
        require_once($CFG->libdir . '/upgradelib.php');
        require_once(__DIR__ . '/../db/upgrade.php');

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->instanceid = (int) $DB->insert_record('block_instances', (object) [
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
     * Inserts a minimal item record and returns its ID.
     *
     * @param string $name Item name.
     * @param array $overrides Field overrides merged on top of sane defaults.
     * @return int The new item ID.
     */
    private function create_item(string $name, array $overrides = []): int {
        global $DB;
        return (int) $DB->insert_record('block_playerhud_items', (object) array_merge([
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'tradable'        => 1,
            'action_type'     => '',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ], $overrides));
    }

    /**
     * Lowers the plugin's own recorded version, exactly as it would be on a real site
     * partway through an upgrade, so the call below is a genuine step forward instead of a
     * no-op "downgrade" — upgrade_plugin_savepoint() refuses anything else.
     *
     * @param int $version Version to record as the plugin's current one.
     */
    private function set_current_version(int $version): void {
        set_config('version', $version, 'block_playerhud');
    }

    /**
     * A pre-existing item literally named 'PlayerCoin' with no action_type yet is tagged
     * 'playercoin', so it can be identified by field value rather than by its mutable name.
     */
    public function test_upgrade_tags_existing_playercoin_items(): void {
        global $DB;

        $itemid = $this->create_item('PlayerCoin');

        $this->set_current_version(2026052802);
        xmldb_block_playerhud_upgrade(2026052802);

        $this->assertSame('playercoin', $DB->get_field('block_playerhud_items', 'action_type', ['id' => $itemid]));
    }

    /**
     * An item that merely happens to share the name but already has some other action_type
     * (or any other item name entirely) is left untouched by the PlayerCoin backfill.
     */
    public function test_upgrade_does_not_tag_unrelated_items(): void {
        global $DB;

        $regularitem = $this->create_item('Wooden Sword');
        $alreadytagged = $this->create_item('PlayerCoin', ['action_type' => 'avatar_profile']);

        $this->set_current_version(2026052802);
        xmldb_block_playerhud_upgrade(2026052802);

        $this->assertSame('', $DB->get_field('block_playerhud_items', 'action_type', ['id' => $regularitem]));
        $this->assertSame(
            'avatar_profile',
            $DB->get_field('block_playerhud_items', 'action_type', ['id' => $alreadytagged])
        );
    }

    /**
     * A pre-existing inventory row from a source that pays real XP (map/teacher/revoked),
     * for an item worth XP and with no drop attached, is backfilled with the item's current
     * XP — the same formula the audit log used to recompute on the fly.
     */
    public function test_upgrade_backfills_inventory_xpawarded_for_a_paid_source(): void {
        global $DB;

        $itemid = $this->create_item('Rare Sword', ['xp' => 50]);
        $inventoryid = $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => 2, 'itemid' => $itemid, 'dropid' => 0,
            'source' => 'map', 'timecreated' => time(),
        ]);

        $this->set_current_version(2026070802);
        xmldb_block_playerhud_upgrade(2026070802);

        $this->assertEquals(50, $DB->get_field('block_playerhud_inventory', 'xpawarded', ['id' => $inventoryid]));
    }

    /**
     * A row from the 'drop' source (a normal collectible pickup, where xpawarded is already
     * written correctly at grant time) is never touched by this backfill — only the sources
     * that predate xpawarded's own introduction are.
     */
    public function test_upgrade_does_not_backfill_a_drop_sourced_row(): void {
        global $DB;

        $itemid = $this->create_item('Common Gem', ['xp' => 20]);
        $inventoryid = $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => 2, 'itemid' => $itemid, 'dropid' => 0,
            'source' => 'drop', 'timecreated' => time(),
        ]);

        $this->set_current_version(2026070802);
        xmldb_block_playerhud_upgrade(2026070802);

        $this->assertEquals(0, $DB->get_field('block_playerhud_inventory', 'xpawarded', ['id' => $inventoryid]));
    }

    /**
     * A row collected from an infinite drop (maxusage = 0) legitimately paid zero XP at grant
     * time (the game's anti-farm rule), so the backfill must not overwrite it with the item's
     * current XP value.
     */
    public function test_upgrade_does_not_backfill_a_row_from_an_infinite_drop(): void {
        global $DB;

        $itemid = $this->create_item('Daily Potion', ['xp' => 10]);
        $dropid = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid, 'itemid' => $itemid, 'name' => 'Infinite Drop',
            'maxusage' => 0, 'respawntime' => 0, 'code' => 'ABCDEF',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $inventoryid = $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => 2, 'itemid' => $itemid, 'dropid' => $dropid,
            'source' => 'map', 'timecreated' => time(),
        ]);

        $this->set_current_version(2026070802);
        xmldb_block_playerhud_upgrade(2026070802);

        $this->assertEquals(0, $DB->get_field('block_playerhud_inventory', 'xpawarded', ['id' => $inventoryid]));
    }

    /**
     * A pre-existing quest_log claim is backfilled with the quest's reward_xp at the moment
     * this step runs — the only value available for a claim that predates xpawarded.
     */
    public function test_upgrade_backfills_quest_log_xpawarded(): void {
        global $DB;

        $questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Quest', 'description' => '',
            'type' => 1, 'requirement' => '1', 'req_itemid' => 0, 'reward_xp' => 25,
            'reward_itemid' => 0, 'required_class_id' => '0', 'image_todo' => '📋',
            'image_done' => '🏅', 'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $logid = $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $questid, 'userid' => 2, 'timecreated' => time(),
        ]);

        $this->set_current_version(2026070902);
        xmldb_block_playerhud_upgrade(2026070902);

        $this->assertEquals(25, $DB->get_field('block_playerhud_quest_log', 'xpawarded', ['id' => $logid]));
    }
}
