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
use block_playerhud\game;

/**
 * Tests for the game logic class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game
 */
final class game_test extends advanced_testcase {
    /** @var int Dummy block instance ID for testing. */
    protected $instanceid;

    /**
     * Creates a real block instance in the database to satisfy context_block.
     */
    protected function setup_block_instance(): void {
        global $DB;

        // Create a real course and get its context.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        // Insert a real block instance.
        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->subpagepattern = null;
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;

        // Provide empty config to avoid base64/unserialize warnings.
        $config = new \stdClass();
        $bi->configdata = base64_encode(serialize($config));

        $bi->timecreated = time();
        $bi->timemodified = time();

        $this->instanceid = $DB->insert_record('block_instances', $bi);
    }

    /**
     * Helper to create a dummy item for tests.
     *
     * @param string $name Item name.
     * @param int $xp XP value.
     * @return \stdClass The created item.
     */
    protected function create_dummy_item(string $name, int $xp): \stdClass {
        global $DB;
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = $name;
        $item->xp = $xp;
        $item->image = '';
        $item->description = '';
        $item->enabled = 1;
        $item->secret = 0;
        $item->timecreated = time();
        $item->timemodified = time();
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Helper to create a dummy drop for tests.
     *
     * @param int $itemid The item ID.
     * @param int $maxusage Maximum collections allowed (0 for infinite).
     * @param int $respawntime Cooldown in seconds.
     * @return \stdClass The created drop.
     */
    protected function create_dummy_drop(int $itemid, int $maxusage, int $respawntime = 0): \stdClass {
        global $DB;
        $drop = new \stdClass();
        $drop->blockinstanceid = $this->instanceid;
        $drop->itemid = $itemid;
        $drop->name = 'Test Location';
        $drop->maxusage = $maxusage;
        $drop->respawntime = $respawntime;
        $drop->code = 'TEST' . rand(100, 999);
        $drop->timecreated = time();
        $drop->timemodified = time();
        $drop->id = $DB->insert_record('block_playerhud_drops', $drop);
        return $drop;
    }

    /**
     * Test game statistics math (Levels, Max Levels, and Total XP).
     *
     * @covers ::get_game_stats
     */
    public function test_get_game_stats(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        // 1. Setup config.
        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels = 10;

        // 2. Setup economy (Total Game XP).
        $item = $this->create_dummy_item('Test Item', 50);
        // A drop that can be collected 3 times. Total game XP should be 150.
        $this->create_dummy_drop($item->id, 3);

        // 3. Test Level 1 (50 XP).
        $stats = game::get_game_stats($config, $this->instanceid, 50);
        $this->assertEquals(1, $stats['level']);
        $this->assertEquals(150, $stats['total_game_xp']);
        $this->assertEquals(50, $stats['xp_next']); // Needs 50 more to reach 100.
        $this->assertFalse($stats['is_max']);

        // 4. Test Level 2 (150 XP).
        $stats = game::get_game_stats($config, $this->instanceid, 150);
        $this->assertEquals(2, $stats['level']);

        // 5. Test Max Level Cap (2000 XP - should cap at level 10).
        $stats = game::get_game_stats($config, $this->instanceid, 2000);
        $this->assertEquals(10, $stats['level']);
        $this->assertTrue($stats['is_max']);
    }

    /**
     * Test that enabled quest reward_xp is included in total_game_xp.
     *
     * @covers ::get_game_stats
     */
    public function test_get_game_stats_includes_quest_xp(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels   = 10;

        // 150 XP from a finite drop (item 50 XP × 3 collections).
        $item = $this->create_dummy_item('Iron Ore', 50);
        $this->create_dummy_drop($item->id, 3);

        // 50 XP available from an enabled quest reward.
        $DB->insert_record('block_playerhud_quests', (object)[
            'blockinstanceid'  => $this->instanceid,
            'name'             => 'Test Quest',
            'description'      => '',
            'type'             => 1,
            'requirement'      => '1',
            'req_itemid'       => 0,
            'reward_xp'        => 50,
            'reward_itemid'    => 0,
            'required_class_id' => '0',
            'image_todo'       => '📋',
            'image_done'       => '🏅',
            'enabled'          => 1,
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $stats = game::get_game_stats($config, $this->instanceid, 0);

        $this->assertEquals(200, $stats['total_game_xp'], 'Quest reward_xp must be added to drop XP total.');
    }

    /**
     * Test that disabled quests do not contribute to total_game_xp.
     *
     * @covers ::get_game_stats
     */
    public function test_get_game_stats_disabled_quest_excluded(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels   = 10;

        $item = $this->create_dummy_item('Herb', 50);
        $this->create_dummy_drop($item->id, 3);

        // Disabled quest must NOT add to the total.
        $DB->insert_record('block_playerhud_quests', (object)[
            'blockinstanceid'  => $this->instanceid,
            'name'             => 'Disabled Quest',
            'description'      => '',
            'type'             => 1,
            'requirement'      => '1',
            'req_itemid'       => 0,
            'reward_xp'        => 500,
            'reward_itemid'    => 0,
            'required_class_id' => '0',
            'image_todo'       => '📋',
            'image_done'       => '🏅',
            'enabled'          => 0,
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $stats = game::get_game_stats($config, $this->instanceid, 0);

        $this->assertEquals(150, $stats['total_game_xp'], 'Disabled quest must not count toward total_game_xp.');
    }

    /**
     * Test the Anti-Farm rule: Infinite drops (maxusage = 0) must yield 0 XP.
     *
     * @covers ::process_collection
     */
    public function test_process_collection_infinite_drop_anti_farm(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        // Create an item worth 100 XP, but on an infinite drop.
        $item = $this->create_dummy_item('Infinite Berry', 100);
        $drop = $this->create_dummy_drop($item->id, 0);

        // Process collection.
        $result = game::process_collection($this->instanceid, $drop->id, $user->id);

        $this->assertTrue($result['success']);

        // Assert the user got the item in inventory...
        $this->assertTrue(game::has_item($user->id, $item->id));

        // ...but assert the Anti-Farm rule worked: XP must be 0!
        $player = game::get_player($this->instanceid, $user->id);
        $this->assertEquals(0, $player->currentxp);
    }

    /**
     * Test strict limit enforcement (maxusage).
     *
     * @covers ::process_collection
     */
    public function test_process_collection_maxusage_limit(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        // Create a rare item that can only be collected ONCE.
        $item = $this->create_dummy_item('Rare Sword', 200);
        $drop = $this->create_dummy_drop($item->id, 1);

        // First collection should succeed.
        $result = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertTrue($result['success']);

        // The system MUST throw an exception on the second attempt.
        try {
            game::process_collection($this->instanceid, $drop->id, $user->id);
            $this->fail('Expected moodle_exception with errorcode limitreached');
        } catch (\moodle_exception $e) {
            $this->assertEquals('limitreached', $e->errorcode);
        }
    }

    /**
     * Test cooldown enforcement (respawntime).
     *
     * @covers ::process_collection
     */
    public function test_process_collection_cooldown(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();

        // Create an item that can be collected 5 times, but requires waiting 1 hour.
        $item = $this->create_dummy_item('Daily Potion', 50);
        $drop = $this->create_dummy_drop($item->id, 5, 3600);

        // First collection should succeed.
        game::process_collection($this->instanceid, $drop->id, $user->id);

        // Trying again immediately MUST trigger the waitmore exception.
        try {
            game::process_collection($this->instanceid, $drop->id, $user->id);
            $this->fail('Expected moodle_exception with errorcode waitmore');
        } catch (\moodle_exception $e) {
            $this->assertEquals('waitmore', $e->errorcode);
        }
    }
}
