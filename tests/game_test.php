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
 * @coversDefaultClass \block_playerhud\game
 * @covers \block_playerhud\event\xp_changed
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
     * get_game_stats()'s total_game_xp must always match economy_health()'s total_items_xp
     * for the same instance, since both are now backed by the same shared calculation
     * (analytics::game_xp_totals()) instead of two independent implementations.
     *
     * @covers ::get_game_stats
     */
    public function test_get_game_stats_matches_economy_health_total(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels   = 10;

        // Finite drop (counts), infinite drop (marked but adds 0), drop-less item (adds 0).
        $finite = $this->create_dummy_item('Gem', 50);
        $this->create_dummy_drop($finite->id, 3);
        $infinite = $this->create_dummy_item('Spring', 40);
        $this->create_dummy_drop($infinite->id, 0);
        $this->create_dummy_item('Loose', 999);

        $DB->insert_record('block_playerhud_quests', (object)[
            'blockinstanceid'  => $this->instanceid,
            'name'             => 'Bonus',
            'description'      => '',
            'type'             => 1,
            'requirement'      => '1',
            'req_itemid'       => 0,
            'reward_xp'        => 25,
            'reward_itemid'    => 0,
            'required_class_id' => '0',
            'image_todo'       => '📋',
            'image_done'       => '🏅',
            'enabled'          => 1,
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $stats = game::get_game_stats($config, $this->instanceid, 0);
        $health = \block_playerhud\local\analytics::economy_health($this->instanceid, 100, 10);

        $this->assertEquals($health->total_items_xp, $stats['total_game_xp']);
        $this->assertEquals(175, $stats['total_game_xp']);
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

    /**
     * get_avatar_item returns the record when the item is enabled and belongs to the instance.
     *
     * @covers ::get_avatar_item
     */
    public function test_get_avatar_item_returns_record_for_enabled_item(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $item = $this->create_dummy_item('Vampire', 0);

        $result = game::get_avatar_item($this->instanceid, $item->id);

        $this->assertNotNull($result);
        $this->assertEquals($item->id, (int) $result->id);
        $this->assertEquals('Vampire', $result->name);
    }

    /**
     * get_avatar_item returns null when the item is disabled (enabled = 0).
     *
     * @covers ::get_avatar_item
     */
    public function test_get_avatar_item_returns_null_for_disabled_item(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $item = $this->create_dummy_item('Disabled Avatar', 0);
        $DB->set_field('block_playerhud_items', 'enabled', 0, ['id' => $item->id]);

        $result = game::get_avatar_item($this->instanceid, $item->id);

        $this->assertNull($result);
    }

    /**
     * get_avatar_item returns null when the item belongs to a different block instance.
     *
     * @covers ::get_avatar_item
     */
    public function test_get_avatar_item_returns_null_for_foreign_instance(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $course = $this->getDataGenerator()->create_course();
        $instanceb = $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);

        $foreignitemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceb,
            'name'            => 'Foreign Avatar',
            'image'           => '🧛',
            'description'     => '',
            'xp'              => 0,
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $result = game::get_avatar_item($this->instanceid, $foreignitemid);

        $this->assertNull($result);
    }

    /**
     * get_avatar_item returns null when no item with the given ID exists.
     *
     * @covers ::get_avatar_item
     */
    public function test_get_avatar_item_returns_null_for_nonexistent_id(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $result = game::get_avatar_item($this->instanceid, 999999);

        $this->assertNull($result);
    }

    /**
     * Test that collecting a finite drop with XP > 0 awards the correct XP to the player.
     *
     * @covers ::process_collection
     */
    public function test_process_collection_awards_xp_on_finite_drop(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Golden Shield', 150);
        $drop = $this->create_dummy_drop($item->id, 2);

        $result = game::process_collection($this->instanceid, $drop->id, $user->id);

        $this->assertTrue($result['success']);
        $this->assertTrue(game::has_item($user->id, $item->id));

        $player = game::get_player($this->instanceid, $user->id);
        $this->assertEquals(150, $player->currentxp, 'Collecting a finite drop must award the full item XP.');

        global $DB;
        $this->assertSame(150, (int) $DB->get_field('block_playerhud_inventory', 'xpawarded', ['userid' => $user->id]));
    }

    /**
     * change_xp awards a positive delta and fires xp_changed with that delta.
     *
     * @covers ::change_xp
     */
    public function test_change_xp_awards_and_fires_event(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $player = game::get_player($this->instanceid, $user->id);

        $sink = $this->redirectEvents();
        $applied = game::change_xp($player, 120, $this->instanceid);

        $this->assertSame(120, $applied);
        $this->assertSame(120, (int) game::get_player($this->instanceid, $user->id)->currentxp);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\block_playerhud\event\xp_changed::class, $events[0]);
        $this->assertSame(120, $events[0]->other['delta']);
        $this->assertSame((int) $user->id, $events[0]->relateduserid);
    }

    /**
     * change_xp deducts a negative delta and floors the total at zero; the event
     * carries the delta actually applied (clamped), not the requested one.
     *
     * @covers ::change_xp
     */
    public function test_change_xp_deducts_and_floors_at_zero(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $player = game::get_player($this->instanceid, $user->id);
        game::change_xp($player, 50, $this->instanceid);

        $sink = $this->redirectEvents();
        // Deduct more than the current total: floored to 0, applied delta is -50.
        $applied = game::change_xp($player, -200, $this->instanceid);

        $this->assertSame(-50, $applied);
        $this->assertSame(0, (int) game::get_player($this->instanceid, $user->id)->currentxp);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertSame(-50, $events[0]->other['delta']);
    }

    /**
     * change_xp is a no-op (no write, no event) when the applied delta is zero.
     *
     * @covers ::change_xp
     */
    public function test_change_xp_noop_fires_nothing(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $player = game::get_player($this->instanceid, $user->id);

        $sink = $this->redirectEvents();
        // Already at 0; a deduction cannot go below 0, so nothing changes.
        $applied = game::change_xp($player, -10, $this->instanceid);

        $this->assertSame(0, $applied);
        $this->assertCount(0, $sink->get_events());
    }

    /**
     * Test that get_leaderboard excludes users with the block/playerhud:manage capability.
     *
     * @covers ::get_leaderboard
     */
    public function test_get_leaderboard_excludes_managers(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = new \stdClass();
        $bi->blockname = 'playerhud';
        $bi->parentcontextid = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern = 'course-view-*';
        $bi->subpagepattern = null;
        $bi->defaultregion = 'side-pre';
        $bi->defaultweight = 0;
        $bi->configdata = base64_encode(serialize(new \stdClass()));
        $bi->timecreated = time();
        $bi->timemodified = time();
        $instanceid = $DB->insert_record('block_instances', $bi);

        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher  = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $now = time();
        foreach ([$student1->id, $student2->id, $teacher->id] as $uid) {
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid'    => $instanceid,
                'userid'             => $uid,
                'currentxp'          => 100,
                'ranking_visibility' => 1,
                'enable_gamification' => 1,
                'timecreated'        => $now,
                'timemodified'       => $now,
            ]);
        }

        $result = game::get_leaderboard($instanceid, $course->id, $student1->id, false);
        $rankeduserids = array_map('intval', array_column($result['individual'], 'userid'));

        $this->assertContains((int) $student1->id, $rankeduserids, 'Student 1 must appear in the ranking.');
        $this->assertContains((int) $student2->id, $rankeduserids, 'Student 2 must appear in the ranking.');
        $this->assertNotContains((int) $teacher->id, $rankeduserids, 'Teacher with manage capability must be excluded.');
    }

    /**
     * xp_to_level maps XP to the configured level, clamped to the cap.
     *
     * @covers ::xp_to_level
     */
    public function test_xp_to_level(): void {
        $this->assertEquals(1, game::xp_to_level(0, 100, 10));
        $this->assertEquals(1, game::xp_to_level(99, 100, 10));
        $this->assertEquals(2, game::xp_to_level(100, 100, 10));
        $this->assertEquals(3, game::xp_to_level(250, 100, 10));
        $this->assertEquals(10, game::xp_to_level(99999, 100, 10), 'Level must be capped at max_levels.');
        $this->assertEquals(1, game::xp_to_level(500, 0, 10), 'A zero xp-per-level must not divide by zero.');
    }

    /**
     * process_collection flags leveled_up only on the collection that crosses a level.
     *
     * @covers ::process_collection
     */
    public function test_process_collection_leveled_up(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        // Default config: 100 XP per level. A 50 XP item needs two collections to level up.
        $item = $this->create_dummy_item('Herb', 50);
        $drop = $this->create_dummy_drop($item->id, 5);

        $first = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertFalse($first['game_data']['leveled_up'], 'First 50 XP collection stays on level 1.');

        $second = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertTrue($second['game_data']['leveled_up'], 'Reaching 100 XP must flag a level-up.');
    }

    /**
     * process_collection flags won only on the collection that reaches 100% of the game XP.
     *
     * @covers ::process_collection
     */
    public function test_process_collection_won_transition(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        // Total game XP = 100 * 2 = 200. The second collection reaches 100%.
        $item = $this->create_dummy_item('Relic', 100);
        $drop = $this->create_dummy_drop($item->id, 2);

        $first = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertFalse($first['game_data']['won'], 'Halfway through the game is not a win.');

        $second = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertTrue($second['game_data']['won'], 'Reaching 100% of the game XP must flag a win.');
    }

    /**
     * The first PlayerCoin collection signals the coin milestone exactly once.
     *
     * @covers ::process_collection
     */
    public function test_process_collection_first_coin_milestone(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('PlayerCoin', 0);
        $DB->set_field('block_playerhud_items', 'action_type', 'playercoin', ['id' => $item->id]);
        $drop = $this->create_dummy_drop($item->id, 3);

        $first = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertEquals('coin', $first['milestone'], 'First PlayerCoin must signal the coin milestone.');

        $player = game::get_player($this->instanceid, $user->id);
        $this->assertEquals(
            game::MILESTONE_COIN,
            (int) $player->milestones & game::MILESTONE_COIN,
            'The coin milestone bit must be persisted.'
        );

        $second = game::process_collection($this->instanceid, $drop->id, $user->id);
        $this->assertEquals('', $second['milestone'], 'A subsequent PlayerCoin must not signal again.');
    }

    /**
     * Create an item carrying a power type and emoji image.
     *
     * @param string $name Item name.
     * @param string $actiontype The action_type value (e.g. playercoin, avatar_profile).
     * @param string $image Emoji image.
     * @return \stdClass The created item.
     */
    protected function create_power_item(string $name, string $actiontype, string $image = ''): \stdClass {
        global $DB;
        $item = $this->create_dummy_item($name, 0);
        $DB->set_field('block_playerhud_items', 'action_type', $actiontype, ['id' => $item->id]);
        $DB->set_field('block_playerhud_items', 'image', $image, ['id' => $item->id]);
        $item->action_type = $actiontype;
        $item->image = $image;
        return $item;
    }

    /**
     * get_player auto-creates a default profile when none exists and reuses it afterwards.
     *
     * @covers ::get_player
     */
    public function test_get_player_creates_default_record(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();

        $this->assertEquals(0, $DB->count_records('block_playerhud_user', ['userid' => $user->id]));

        $player = game::get_player($this->instanceid, $user->id);
        $this->assertEquals(0, (int) $player->currentxp);
        $this->assertEquals(1, (int) $player->enable_gamification);
        $this->assertEquals(1, (int) $player->ranking_visibility);
        $this->assertNotEmpty($player->id);
        $this->assertEquals(1, $DB->count_records('block_playerhud_user', ['userid' => $user->id]));

        // A second call reuses the same record instead of inserting another.
        $again = game::get_player($this->instanceid, $user->id);
        $this->assertEquals($player->id, $again->id);
        $this->assertEquals(1, $DB->count_records('block_playerhud_user', ['userid' => $user->id]));
    }

    /**
     * toggle_gamification flips the enable_gamification flag both ways.
     *
     * @covers ::toggle_gamification
     */
    public function test_toggle_gamification(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();

        game::toggle_gamification($this->instanceid, $user->id, false);
        $this->assertEquals(0, (int) game::get_player($this->instanceid, $user->id)->enable_gamification);

        game::toggle_gamification($this->instanceid, $user->id, true);
        $this->assertEquals(1, (int) game::get_player($this->instanceid, $user->id)->enable_gamification);
    }

    /**
     * toggle_ranking_visibility flips the ranking_visibility flag both ways.
     *
     * @covers ::toggle_ranking_visibility
     */
    public function test_toggle_ranking_visibility(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();

        game::toggle_ranking_visibility($this->instanceid, $user->id, false);
        $this->assertEquals(0, (int) game::get_player($this->instanceid, $user->id)->ranking_visibility);

        game::toggle_ranking_visibility($this->instanceid, $user->id, true);
        $this->assertEquals(1, (int) game::get_player($this->instanceid, $user->id)->ranking_visibility);
    }

    /**
     * get_inventory returns owned items and hides revoked or consumed entries.
     *
     * @covers ::get_inventory
     */
    public function test_get_inventory_excludes_revoked_and_consumed(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();

        $kept = $this->create_dummy_item('Kept', 10);
        $revoked = $this->create_dummy_item('Revoked', 10);
        $consumed = $this->create_dummy_item('Consumed', 10);

        $now = time();
        $DB->insert_records('block_playerhud_inventory', [
            (object) ['userid' => $user->id, 'itemid' => $kept->id, 'dropid' => 0, 'source' => 'map', 'timecreated' => $now],
            (object) ['userid' => $user->id, 'itemid' => $revoked->id,
                'dropid' => 0, 'source' => 'revoked', 'timecreated' => $now],
            (object) ['userid' => $user->id, 'itemid' => $consumed->id,
                'dropid' => 0, 'source' => 'consumed', 'timecreated' => $now],
        ]);

        $inventory = game::get_inventory($user->id, $this->instanceid);
        $itemids = array_map(static fn($row) => (int) $row->id, $inventory);

        $this->assertContains((int) $kept->id, $itemids);
        $this->assertNotContains((int) $revoked->id, $itemids);
        $this->assertNotContains((int) $consumed->id, $itemids);
    }

    /**
     * has_item reflects whether the user owns a given item.
     *
     * @covers ::has_item
     */
    public function test_has_item(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $user = $this->getDataGenerator()->create_user();
        $item = $this->create_dummy_item('Owned', 10);

        $this->assertFalse(game::has_item($user->id, $item->id));

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $user->id, 'itemid' => $item->id, 'dropid' => 0, 'source' => 'map', 'timecreated' => time(),
        ]);

        $this->assertTrue(game::has_item($user->id, $item->id));
    }

    /**
     * get_user_rank orders by XP, breaks ties by earlier arrival and excludes managers.
     *
     * @covers ::get_user_rank
     */
    public function test_get_user_rank_tiebreak_and_manager_exclusion(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $bi = (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $instanceid = $DB->insert_record('block_instances', $bi);

        $leader = $this->getDataGenerator()->create_user();
        $earlytie = $this->getDataGenerator()->create_user();
        $latetie = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $now = time();
        // Leader leads on XP; the two tied players share XP but arrived at different times.
        $rows = [
            [$leader->id, 300, $now],
            [$earlytie->id, 200, $now - 100],
            [$latetie->id, 200, $now],
            [$teacher->id, 999, $now - 500],
        ];
        foreach ($rows as [$uid, $xp, $tm]) {
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid' => $instanceid, 'userid' => $uid, 'currentxp' => $xp,
                'enable_gamification' => 1, 'ranking_visibility' => 1, 'timecreated' => $tm, 'timemodified' => $tm,
            ]);
        }

        // Manager is excluded despite the highest XP, so the leader is rank 1.
        $this->assertEquals(1, game::get_user_rank($instanceid, $leader->id, 300));
        // Earlier arrival wins the tie.
        $this->assertEquals(2, game::get_user_rank($instanceid, $earlytie->id, 200));
        $this->assertEquals(3, game::get_user_rank($instanceid, $latetie->id, 200));
    }

    /**
     * get_user_rank only counts users still enrolled in the course.
     *
     * @covers ::get_user_rank
     */
    public function test_get_user_rank_respects_enrolment(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $bi = (object) [
            'blockname' => 'playerhud', 'parentcontextid' => $coursecontext->id, 'showinsubcontexts' => 0,
            'pagetypepattern' => 'course-view-*', 'subpagepattern' => null, 'defaultregion' => 'side-pre',
            'defaultweight' => 0, 'configdata' => base64_encode(serialize(new \stdClass())),
            'timecreated' => time(), 'timemodified' => time(),
        ];
        $instanceid = $DB->insert_record('block_instances', $bi);

        $enrolled = $this->getDataGenerator()->create_user();
        $ghost = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($enrolled->id, $course->id, 'student');

        $now = time();
        foreach ([[$enrolled->id, 100], [$ghost->id, 500]] as [$uid, $xp]) {
            $DB->insert_record('block_playerhud_user', (object) [
                'blockinstanceid' => $instanceid, 'userid' => $uid, 'currentxp' => $xp,
                'enable_gamification' => 1, 'ranking_visibility' => 1, 'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        // The higher-XP ghost is not enrolled, so the enrolled student still ranks 1.
        $this->assertEquals(1, game::get_user_rank($instanceid, $enrolled->id, 100, $course->id));
    }

    /**
     * get_full_trades populates each trade with its requirement and reward items.
     *
     * @covers ::get_full_trades
     */
    public function test_get_full_trades_populates_requirements_and_rewards(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $cost = $this->create_dummy_item('Coin', 0);
        $prize = $this->create_dummy_item('Trophy', 0);
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Trophy Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 0, 'timecreated' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) ['tradeid' => $tradeid, 'itemid' => $cost->id, 'qty' => 3]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $prize->id, 'qty' => 1]);

        $trades = game::get_full_trades($this->instanceid);
        $this->assertArrayHasKey($tradeid, $trades);
        $trade = $trades[$tradeid];
        $this->assertCount(1, $trade->requirements);
        $this->assertCount(1, $trade->rewards);
        $this->assertEquals($cost->id, reset($trade->requirements)->itemid);
        $this->assertEquals(3, (int) reset($trade->requirements)->qty);
        $this->assertEquals($prize->id, reset($trade->rewards)->itemid);
    }

    /**
     * get_full_trades returns an empty array when the instance has no trades.
     *
     * @covers ::get_full_trades
     */
    public function test_get_full_trades_empty(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();
        $this->assertSame([], game::get_full_trades($this->instanceid));
    }

    /**
     * A trade with all enabled requirement/reward items is not flagged unavailable.
     *
     * @covers ::get_full_trades
     */
    public function test_get_full_trades_available_when_all_items_enabled(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $cost = $this->create_dummy_item('Coin', 0);
        $prize = $this->create_dummy_item('Trophy', 0);
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Trophy Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 0, 'timecreated' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) ['tradeid' => $tradeid, 'itemid' => $cost->id, 'qty' => 3]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $prize->id, 'qty' => 1]);

        $trades = game::get_full_trades($this->instanceid);

        $this->assertFalse($trades[$tradeid]->unavailable);
    }

    /**
     * A trade with a disabled requirement item is flagged unavailable, even though the reward
     * item is enabled.
     *
     * @covers ::get_full_trades
     */
    public function test_get_full_trades_unavailable_when_requirement_item_disabled(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $cost = $this->create_dummy_item('Coin', 0);
        $prize = $this->create_dummy_item('Trophy', 0);
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Trophy Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 0, 'timecreated' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) ['tradeid' => $tradeid, 'itemid' => $cost->id, 'qty' => 3]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $prize->id, 'qty' => 1]);
        $DB->set_field('block_playerhud_items', 'enabled', 0, ['id' => $cost->id]);

        $trades = game::get_full_trades($this->instanceid);

        $this->assertTrue($trades[$tradeid]->unavailable);
    }

    /**
     * A trade with a disabled reward item is flagged unavailable too, not just requirements.
     *
     * @covers ::get_full_trades
     */
    public function test_get_full_trades_unavailable_when_reward_item_disabled(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $cost = $this->create_dummy_item('Coin', 0);
        $prize = $this->create_dummy_item('Trophy', 0);
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Trophy Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 0, 'timecreated' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) ['tradeid' => $tradeid, 'itemid' => $cost->id, 'qty' => 3]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $prize->id, 'qty' => 1]);
        $DB->set_field('block_playerhud_items', 'enabled', 0, ['id' => $prize->id]);

        $trades = game::get_full_trades($this->instanceid);

        $this->assertTrue($trades[$tradeid]->unavailable);
    }

    /**
     * build_trade_suggestions yields one suggestion per uncovered avatar plus a bundle,
     * with discounted cost for robot/alien avatars.
     *
     * @covers ::build_trade_suggestions
     */
    public function test_build_trade_suggestions(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $coin = $this->create_power_item('PlayerCoin', 'playercoin', '🪙');
        $robot = $this->create_power_item('Robot', 'avatar_profile', '🤖');
        $fox = $this->create_power_item('Fox', 'avatar_profile', '🦊');

        $suggestions = game::build_trade_suggestions($this->instanceid);

        $byuid = [];
        foreach ($suggestions as $sug) {
            $byuid[$sug['uid']] = $sug;
        }

        $this->assertArrayHasKey('ind_' . $robot->id, $byuid);
        $this->assertArrayHasKey('ind_' . $fox->id, $byuid);
        $this->assertArrayHasKey('bundle_all', $byuid);

        // Robot/alien avatars are discounted to 1 coin; ordinary avatars cost 5.
        $this->assertEquals(1, $byuid['ind_' . $robot->id]['cost_qty']);
        $this->assertEquals(5, $byuid['ind_' . $fox->id]['cost_qty']);
        $this->assertEquals(50, $byuid['bundle_all']['cost_qty']);
        $this->assertEquals($coin->id, $byuid['ind_' . $robot->id]['cost_itemid']);
        $this->assertCount(2, $byuid['bundle_all']['rewards']);
    }

    /**
     * build_trade_suggestions skips an avatar that is already the sole reward of a trade.
     *
     * @covers ::build_trade_suggestions
     */
    public function test_build_trade_suggestions_skips_covered_avatar(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $this->create_power_item('PlayerCoin', 'playercoin', '🪙');
        $covered = $this->create_power_item('Fox', 'avatar_profile', '🦊');

        // An existing trade already grants the Fox as its sole reward.
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid, 'name' => 'Fox Trade',
            'groupid' => 0, 'centralized' => 1, 'onetime' => 1, 'timecreated' => time(),
        ]);
        $DB->insert_record('block_playerhud_trade_rewards', (object) ['tradeid' => $tradeid, 'itemid' => $covered->id, 'qty' => 1]);

        $uids = array_column(game::build_trade_suggestions($this->instanceid), 'uid');
        $this->assertNotContains('ind_' . $covered->id, $uids, 'Already-covered avatar must not be suggested individually.');
    }

    /**
     * build_trade_suggestions returns nothing without a PlayerCoin or avatars.
     *
     * @covers ::build_trade_suggestions
     */
    public function test_build_trade_suggestions_requires_prerequisites(): void {
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        // No PlayerCoin yet.
        $this->create_power_item('Fox', 'avatar_profile', '🦊');
        $this->assertSame([], game::build_trade_suggestions($this->instanceid));
    }

    /**
     * create_trade_from_suggestion persists a one-time trade with its cost and rewards.
     *
     * @covers ::create_trade_from_suggestion
     */
    public function test_create_trade_from_suggestion(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->setup_block_instance();

        $coin = $this->create_power_item('PlayerCoin', 'playercoin', '🪙');
        $avatar = $this->create_power_item('Fox', 'avatar_profile', '🦊');

        $sug = [
            'name' => 'Fox Avatar', 'cost_itemid' => $coin->id, 'cost_qty' => 5,
            'rewards' => [['id' => $avatar->id, 'qty' => 1]],
        ];
        $result = game::create_trade_from_suggestion($this->instanceid, $sug);
        $tradeid = $result['tradeid'];

        $trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid], '*', MUST_EXIST);
        $this->assertEquals('Fox Avatar', $trade->name);
        $this->assertEquals(1, (int) $trade->onetime);
        $this->assertEquals(1, (int) $trade->centralized);

        $req = $DB->get_record('block_playerhud_trade_reqs', ['id' => $result['reqid']], '*', MUST_EXIST);
        $this->assertEquals($tradeid, (int) $req->tradeid);
        $this->assertEquals($coin->id, (int) $req->itemid);
        $this->assertEquals(5, (int) $req->qty);

        $this->assertCount(1, $result['rewardids']);
        $reward = $DB->get_record('block_playerhud_trade_rewards', ['id' => $result['rewardids'][0]], '*', MUST_EXIST);
        $this->assertEquals($tradeid, (int) $reward->tradeid);
        $this->assertEquals($avatar->id, (int) $reward->itemid);
    }
}
