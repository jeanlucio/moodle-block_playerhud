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
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use block_playerhud\privacy\provider;

/**
 * Privacy API tests for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /** @var int Dummy block instance ID. */
    protected $instanceid;

    /** @var \context_block Block context. */
    protected $context;

    /** @var int Shared item ID for the block instance. */
    protected $itemid;

    /** @var int Shared quest ID for the block instance. */
    protected $questid;

    /** @var int Shared trade ID for the block instance. */
    protected $tradeid;

    /**
     * Set up the environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        global $DB;
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
        $this->context = \context_block::instance($this->instanceid);
    }

    /**
     * Create the shared content (item, quest, trade) for the block instance once.
     */
    protected function ensure_content(): void {
        global $DB;

        if (!empty($this->itemid)) {
            return;
        }

        $this->itemid = $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Sword of Privacy',
            'xp' => 100,
            'enabled' => 1,
            'secret' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->questid = $DB->insert_record('block_playerhud_quests', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Privacy Quest',
            'description' => '',
            'type' => 1,
            'requirement' => '1',
            'req_itemid' => 0,
            'reward_xp' => 10,
            'reward_itemid' => 0,
            'required_class_id' => '0',
            'image_todo' => '📋',
            'image_done' => '🏅',
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $this->instanceid,
            'name' => 'Privacy Trade',
            'groupid' => 0,
            'centralized' => 1,
            'onetime' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Seed a complete set of personal data for a user across every stored table.
     *
     * @param int $userid The user ID to seed data for.
     */
    protected function seed_user(int $userid): void {
        global $DB;

        $this->ensure_content();

        $DB->insert_record('block_playerhud_user', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $userid,
            'currentxp' => 5000,
            'enable_gamification' => 1,
            'ranking_visibility' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('block_playerhud_rpg_progress', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $userid,
            'classid' => 7,
            'karma' => 42,
            'current_nodes' => '{"ch1":"nodeA"}',
            'completed_chapters' => '["ch1"]',
        ]);

        $DB->insert_record('block_playerhud_inventory', (object) [
            'userid' => $userid,
            'itemid' => $this->itemid,
            'dropid' => 0,
            'source' => 'test',
            'timecreated' => time(),
        ]);

        $DB->insert_record('block_playerhud_ai_logs', (object) [
            'blockinstanceid' => $this->instanceid,
            'userid' => $userid,
            'action_type' => 'generate_item',
            'object_name' => 'Sword',
            'ai_provider' => 'gemini',
            'timecreated' => time(),
        ]);

        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $this->questid,
            'userid' => $userid,
            'timecreated' => time(),
        ]);

        $DB->insert_record('block_playerhud_trade_log', (object) [
            'tradeid' => $this->tradeid,
            'userid' => $userid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Count personal rows owned by a user across every stored table.
     *
     * @param int $userid The user ID.
     * @return int Total number of rows belonging to the user.
     */
    protected function total_user_rows(int $userid): int {
        global $DB;

        return $DB->count_records('block_playerhud_user', ['userid' => $userid])
            + $DB->count_records('block_playerhud_rpg_progress', ['userid' => $userid])
            + $DB->count_records('block_playerhud_inventory', ['userid' => $userid])
            + $DB->count_records('block_playerhud_ai_logs', ['userid' => $userid])
            + $DB->count_records('block_playerhud_quest_log', ['userid' => $userid])
            + $DB->count_records('block_playerhud_trade_log', ['userid' => $userid]);
    }

    /**
     * Test deletion of user data.
     * Ensures GDPR compliance by removing profile, inventory, and AI logs.
     *
     * @covers ::delete_data_for_user
     * @covers ::delete_data_for_user_list_in_context
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();

        // 1. Give the user a Player Profile with XP.
        $player = new \stdClass();
        $player->blockinstanceid = $this->instanceid;
        $player->userid = $user->id;
        $player->currentxp = 5000;
        $player->enable_gamification = 1;
        $player->ranking_visibility = 1;
        $player->timecreated = time();
        $player->timemodified = time();
        $DB->insert_record('block_playerhud_user', $player);

        // 2. Create a dummy item.
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name = 'Sword of Privacy';
        $item->xp = 100;
        $item->enabled = 1;
        $item->secret = 0;
        $item->timecreated = time();
        $item->timemodified = time();
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        // 3. Give the user an item in their inventory.
        $inv = new \stdClass();
        $inv->userid = $user->id;
        $inv->itemid = $itemid;
        $inv->dropid = 0;
        $inv->source = 'test';
        $inv->timecreated = time();
        $DB->insert_record('block_playerhud_inventory', $inv);

        // 4. Create a fake AI log for this user.
        $ailog = new \stdClass();
        $ailog->blockinstanceid = $this->instanceid;
        $ailog->userid = $user->id;
        $ailog->action_type = 'generate_item';
        $ailog->ai_provider = 'gemini';
        $ailog->timecreated = time();
        $DB->insert_record('block_playerhud_ai_logs', $ailog);

        // 5. Create a quest and a quest_log entry for this user.
        $questid = $DB->insert_record('block_playerhud_quests', (object)[
            'blockinstanceid'  => $this->instanceid,
            'name'             => 'Privacy Quest',
            'description'      => '',
            'type'             => 1,
            'requirement'      => '1',
            'req_itemid'       => 0,
            'reward_xp'        => 10,
            'reward_itemid'    => 0,
            'required_class_id' => '0',
            'image_todo'       => '📋',
            'image_done'       => '🏅',
            'enabled'          => 1,
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);
        $DB->insert_record('block_playerhud_quest_log', (object)[
            'questid'     => $questid,
            'userid'      => $user->id,
            'timecreated' => time(),
        ]);

        // Verify all data exists before deleting.
        $this->assertEquals(1, $DB->count_records('block_playerhud_user', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('block_playerhud_ai_logs', ['userid' => $user->id]));
        $this->assertEquals(
            1,
            $DB->count_records('block_playerhud_quest_log', ['userid' => $user->id]),
            'Quest log entry should exist before deletion.'
        );

        // 6. Build the approved context list to request deletion.
        $approvedcontextlist = new approved_contextlist($user, 'block_playerhud', [$this->context->id]);

        // 7. Execute the Privacy API deletion.
        provider::delete_data_for_user($approvedcontextlist);

        // 8. Verify all data is permanently gone from the database.
        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_user', ['userid' => $user->id]),
            'User profile should be deleted.'
        );

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_inventory', ['userid' => $user->id]),
            'Inventory should be deleted.'
        );

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_ai_logs', ['userid' => $user->id]),
            'AI logs should be deleted.'
        );

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_quest_log', ['userid' => $user->id]),
            'Quest log entries should be deleted (GDPR compliance).'
        );
    }

    /**
     * Test that user preferences (API keys and equipped avatar) are deleted correctly.
     * Ensure GDPR compliance by removing preference traces upon user deletion.
     *
     * @covers ::delete_user_preferences
     */
    public function test_delete_user_preferences(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $avatarkey = 'block_playerhud_avatar_' . $this->instanceid;

        // Simulate a teacher saving every API key plus a student equipping an avatar.
        set_user_preference('block_playerhud_gemini_key', 'AIza_test_key', $user->id);
        set_user_preference('block_playerhud_groq_key', 'gsk_test_key', $user->id);
        set_user_preference('block_playerhud_openai_key', 'sk_test_key', $user->id);
        set_user_preference('block_playerhud_openai_url', 'https://api.example.com', $user->id);
        set_user_preference('block_playerhud_openai_model', 'gpt-test', $user->id);
        set_user_preference($avatarkey, '777', $user->id);

        // Verify preferences are stored before deletion.
        $this->assertEquals('AIza_test_key', get_user_preferences('block_playerhud_gemini_key', null, $user->id));
        $this->assertEquals('sk_test_key', get_user_preferences('block_playerhud_openai_key', null, $user->id));
        $this->assertEquals('777', get_user_preferences($avatarkey, null, $user->id));

        // Execute the privacy provider deletion method.
        provider::delete_user_preferences($user->id);

        // Assert that every preference is now null (removed from DB).
        $this->assertNull(get_user_preferences('block_playerhud_gemini_key', null, $user->id));
        $this->assertNull(get_user_preferences('block_playerhud_groq_key', null, $user->id));
        $this->assertNull(get_user_preferences('block_playerhud_openai_key', null, $user->id));
        $this->assertNull(get_user_preferences('block_playerhud_openai_url', null, $user->id));
        $this->assertNull(get_user_preferences('block_playerhud_openai_model', null, $user->id));
        $this->assertNull(get_user_preferences($avatarkey, null, $user->id));
    }

    /**
     * Test that user preferences (API keys and equipped avatar) are exported correctly.
     *
     * @covers ::export_user_preferences
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $avatarkey = 'block_playerhud_avatar_' . $this->instanceid;

        set_user_preference('block_playerhud_gemini_key', 'AIza_test_key', $user->id);
        set_user_preference('block_playerhud_openai_url', 'https://api.example.com', $user->id);
        set_user_preference('block_playerhud_openai_model', 'gpt-test', $user->id);
        set_user_preference($avatarkey, '777', $user->id);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $prefs = $writer->get_user_preferences('block_playerhud');
        $this->assertEquals('AIza_test_key', $prefs->block_playerhud_gemini_key->value);
        $this->assertEquals('https://api.example.com', $prefs->block_playerhud_openai_url->value);
        $this->assertEquals('gpt-test', $prefs->block_playerhud_openai_model->value);
        $this->assertEquals('777', $prefs->{$avatarkey}->value);
    }

    /**
     * Test that the metadata declares every stored item, including the avatar preference.
     *
     * @covers ::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('block_playerhud'));
        $items = $collection->get_collection();
        $this->assertNotEmpty($items);

        $names = [];
        foreach ($items as $item) {
            $names[] = $item->get_name();
        }

        // User preferences (including the equipped avatar).
        $this->assertContains('block_playerhud_avatar', $names);
        $this->assertContains('block_playerhud_gemini_key', $names);

        // Stored database tables.
        $this->assertContains('block_playerhud_user', $names);
        $this->assertContains('block_playerhud_inventory', $names);

        // External API destinations.
        $this->assertContains('openai_compatible', $names);
    }

    /**
     * Test that the context where a user stored data is discovered.
     *
     * @covers ::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed_user($user->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids = $contextlist->get_contextids();
        $this->assertCount(1, $contextids);
        $this->assertContains((int) $this->context->id, array_map('intval', $contextids));

        // A user without any data discovers no contexts.
        $empty = provider::get_contexts_for_userid($other->id);
        $this->assertCount(0, $empty->get_contextids());
    }

    /**
     * Test that every user with data in the block context is discovered.
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        global $DB;

        $withprofile = $this->getDataGenerator()->create_user();
        $withquestonly = $this->getDataGenerator()->create_user();
        $this->seed_user($withprofile->id);

        // Second user only owns a quest log entry (exercises the JOIN branch).
        $this->ensure_content();
        $DB->insert_record('block_playerhud_quest_log', (object) [
            'questid' => $this->questid,
            'userid' => $withquestonly->id,
            'timecreated' => time(),
        ]);

        $userlist = new userlist($this->context, 'block_playerhud');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int) $withprofile->id, array_map('intval', $userids));
        $this->assertContains((int) $withquestonly->id, array_map('intval', $userids));

        // A non-block context yields no users.
        $coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $emptylist = new userlist($coursecontext, 'block_playerhud');
        provider::get_users_in_context($emptylist);
        $this->assertCount(0, $emptylist->get_userids());
    }

    /**
     * Test that all stored personal data is exported under the correct subtrees.
     *
     * @covers ::export_user_data
     */
    public function test_export_user_data(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->seed_user($user->id);

        $approved = new approved_contextlist($user, 'block_playerhud', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $pluginname = get_string('pluginname', 'block_playerhud');

        // A. General profile.
        $profile = $writer->get_data([$pluginname, get_string('profile')]);
        $this->assertEquals(5000, $profile->currentxp);

        // B. RPG progress.
        $rpg = $writer->get_data([$pluginname, get_string('privacy_export_rpg', 'block_playerhud')]);
        $this->assertEquals(7, $rpg->class_id);
        $this->assertEquals(42, $rpg->karma);

        // C. Inventory.
        $inventory = $writer->get_data([$pluginname, get_string('tab_collection', 'block_playerhud')]);
        $this->assertCount(1, $inventory->items);
        $this->assertEquals($this->itemid, $inventory->items[0]['item_id']);

        // D. Quest logs.
        $quests = $writer->get_data([$pluginname, get_string('privacy_export_quest_log', 'block_playerhud')]);
        $this->assertCount(1, $quests->quests);
        $this->assertEquals('Privacy Quest', $quests->quests[0]['quest_name']);

        // E. Trade logs.
        $trades = $writer->get_data([$pluginname, get_string('tab_shop', 'block_playerhud')]);
        $this->assertCount(1, $trades->transactions);
        $this->assertEquals('Privacy Trade', $trades->transactions[0]['trade_name']);

        // F. AI Oracle logs.
        $ailogs = $writer->get_data([$pluginname, get_string('privacy_export_ai_logs', 'block_playerhud')]);
        $this->assertCount(1, $ailogs->logs);
        $this->assertEquals('generate_item', $ailogs->logs[0]['action']);
    }

    /**
     * Test that deleting a whole context removes every user's data within it.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $this->seed_user($usera->id);
        $this->seed_user($userb->id);

        $this->assertEquals(6, $this->total_user_rows($usera->id));
        $this->assertEquals(6, $this->total_user_rows($userb->id));

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $this->total_user_rows($usera->id));
        $this->assertEquals(0, $this->total_user_rows($userb->id));
    }

    /**
     * Test that deleting a subset of users leaves other users in the context untouched.
     *
     * @covers ::delete_data_for_users
     * @covers ::delete_data_for_user_list_in_context
     */
    public function test_delete_data_for_users(): void {
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $survivor = $this->getDataGenerator()->create_user();
        $this->seed_user($usera->id);
        $this->seed_user($userb->id);
        $this->seed_user($survivor->id);

        $approved = new approved_userlist($this->context, 'block_playerhud', [$usera->id, $userb->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $this->total_user_rows($usera->id));
        $this->assertEquals(0, $this->total_user_rows($userb->id));
        $this->assertEquals(6, $this->total_user_rows($survivor->id), 'Untargeted user must keep all data.');
    }

    /**
     * Test that every entry point safely ignores non-block contexts without touching data.
     *
     * @covers ::export_user_data
     * @covers ::delete_data_for_all_users_in_context
     * @covers ::delete_data_for_user
     * @covers ::delete_data_for_users
     */
    public function test_guards_ignore_non_block_contexts(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->seed_user($user->id);

        $coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);

        // Export against a course context hits the early return and writes nothing.
        $exportlist = new approved_contextlist($user, 'block_playerhud', [$coursecontext->id]);
        provider::export_user_data($exportlist);

        // Context-wide and per-user deletions are no-ops on a non-block context.
        provider::delete_data_for_all_users_in_context($coursecontext);
        provider::delete_data_for_user($exportlist);
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'block_playerhud', [$user->id]));

        $this->assertEquals(6, $this->total_user_rows($user->id), 'Non-block contexts must never delete data.');
    }
}
