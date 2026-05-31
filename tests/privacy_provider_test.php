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
use core_privacy\local\request\writer;
use block_playerhud\privacy\provider;

/**
 * Privacy API tests for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\privacy\provider
 */
final class privacy_provider_test extends advanced_testcase {
    /** @var int Dummy block instance ID. */
    protected $instanceid;

    /** @var \context_block Block context. */
    protected $context;

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
     * Test deletion of user data.
     * Ensures GDPR compliance by removing profile, inventory, and AI logs.
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
     * @covers \block_playerhud\privacy\provider::delete_user_preferences
     */
    public function test_delete_user_preferences(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $avatarkey = 'block_playerhud_avatar_' . $this->instanceid;

        // Simulate a teacher saving API keys and a student equipping an avatar.
        set_user_preference('block_playerhud_gemini_key', 'AIza_test_key', $user->id);
        set_user_preference('block_playerhud_groq_key', 'gsk_test_key', $user->id);
        set_user_preference($avatarkey, '777', $user->id);

        // Verify preferences are stored before deletion.
        $this->assertEquals('AIza_test_key', get_user_preferences('block_playerhud_gemini_key', null, $user->id));
        $this->assertEquals('gsk_test_key', get_user_preferences('block_playerhud_groq_key', null, $user->id));
        $this->assertEquals('777', get_user_preferences($avatarkey, null, $user->id));

        // Execute the privacy provider deletion method.
        provider::delete_user_preferences($user->id);

        // Assert that every preference is now null (removed from DB).
        $this->assertNull(get_user_preferences('block_playerhud_gemini_key', null, $user->id));
        $this->assertNull(get_user_preferences('block_playerhud_groq_key', null, $user->id));
        $this->assertNull(get_user_preferences($avatarkey, null, $user->id));
    }

    /**
     * Test that user preferences (API keys and equipped avatar) are exported correctly.
     *
     * @covers \block_playerhud\privacy\provider::export_user_preferences
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $avatarkey = 'block_playerhud_avatar_' . $this->instanceid;

        set_user_preference('block_playerhud_gemini_key', 'AIza_test_key', $user->id);
        set_user_preference($avatarkey, '777', $user->id);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $prefs = $writer->get_user_preferences('block_playerhud');
        $this->assertEquals('AIza_test_key', $prefs->block_playerhud_gemini_key->value);
        $this->assertEquals('777', $prefs->{$avatarkey}->value);
    }

    /**
     * Test that the metadata declares every stored item, including the avatar preference.
     *
     * @covers \block_playerhud\privacy\provider::get_metadata
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
}
