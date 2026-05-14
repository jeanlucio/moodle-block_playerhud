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
 * Basic persistence (create / update / delete) tests for items, chapters and trades.
 *
 * These tests verify that the core content types used by the block are stored,
 * retrieved and modified correctly at the database level, and that records are
 * properly scoped to their block instance.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class content_crud_test extends advanced_testcase {
    /** @var \stdClass Course used by all tests. */
    protected $course;

    /** @var int Block instance ID. */
    protected $instanceid;

    /**
     * Set up a course and a block instance before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        global $DB;

        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();

        $coursecontext = \context_course::instance($this->course->id);
        $bi = new \stdClass();
        $bi->blockname        = 'playerhud';
        $bi->parentcontextid  = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern  = 'course-view-*';
        $bi->defaultregion    = 'side-pre';
        $bi->defaultweight    = 0;
        $bi->configdata       = base64_encode(serialize(new \stdClass()));
        $bi->timecreated      = time();
        $bi->timemodified     = time();
        $this->instanceid     = $DB->insert_record('block_instances', $bi);
    }

    /**
     * Creating an item persists every field correctly.
     */
    public function test_item_create_persists_all_fields(): void {
        global $DB;

        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name            = 'Health Potion';
        $item->xp              = 25;
        $item->image           = '🧪';
        $item->description     = 'Restores health.';
        $item->enabled         = 1;
        $item->secret          = 0;
        $item->tradable        = 1;
        $item->timecreated     = time();
        $item->timemodified    = time();
        $item->id              = $DB->insert_record('block_playerhud_items', $item);

        $saved = $DB->get_record('block_playerhud_items', ['id' => $item->id], '*', MUST_EXIST);

        $this->assertEquals($this->instanceid, $saved->blockinstanceid);
        $this->assertEquals('Health Potion', $saved->name);
        $this->assertEquals(25, $saved->xp);
        $this->assertEquals('🧪', $saved->image);
        $this->assertEquals('Restores health.', $saved->description);
        $this->assertEquals(1, $saved->enabled);
        $this->assertEquals(0, $saved->secret);
        $this->assertEquals(1, $saved->tradable);
    }

    /**
     * Updating an item changes only the modified fields.
     */
    public function test_item_update_changes_fields(): void {
        global $DB;

        $item = $this->create_item('Old Name', 10);

        $item->name         = 'New Name';
        $item->xp           = 50;
        $item->enabled      = 0;
        $item->timemodified = time();
        $DB->update_record('block_playerhud_items', $item);

        $updated = $DB->get_record('block_playerhud_items', ['id' => $item->id], '*', MUST_EXIST);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals(50, $updated->xp);
        $this->assertEquals(0, $updated->enabled);
        $this->assertEquals($this->instanceid, $updated->blockinstanceid);
    }

    /**
     * Deleting an item removes it from the database.
     */
    public function test_item_delete_removes_record(): void {
        global $DB;

        $item = $this->create_item('Temporary Item', 5);
        $this->assertTrue($DB->record_exists('block_playerhud_items', ['id' => $item->id]));

        $DB->delete_records('block_playerhud_items', ['id' => $item->id]);

        $this->assertFalse($DB->record_exists('block_playerhud_items', ['id' => $item->id]));
    }

    /**
     * Items are scoped to their instance and do not appear for other instances.
     */
    public function test_item_listing_scoped_to_instance(): void {
        global $DB;

        $otherid = $this->create_second_instance();

        $this->create_item('Item A', 10);
        $this->create_item('Item B', 20);

        $ownitem = new \stdClass();
        $ownitem->blockinstanceid = $otherid;
        $ownitem->name            = 'Foreign Item';
        $ownitem->xp              = 99;
        $ownitem->image           = '';
        $ownitem->description     = '';
        $ownitem->enabled         = 1;
        $ownitem->secret          = 0;
        $ownitem->tradable        = 1;
        $ownitem->maxusage        = 1;
        $ownitem->respawntime     = 0;
        $ownitem->timecreated     = time();
        $ownitem->timemodified    = time();
        $DB->insert_record('block_playerhud_items', $ownitem);

        $myitems = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);

        $this->assertCount(2, $myitems);
        foreach ($myitems as $i) {
            $this->assertEquals($this->instanceid, $i->blockinstanceid);
        }
    }

    /**
     * A secret item exists in the database with the secret flag set.
     */
    public function test_item_secret_flag_stored(): void {
        global $DB;

        $item = $this->create_item('Hidden Relic', 100, true);

        $saved = $DB->get_record('block_playerhud_items', ['id' => $item->id], '*', MUST_EXIST);
        $this->assertEquals(1, $saved->secret);
    }

    /**
     * Creating a chapter persists every field correctly.
     */
    public function test_chapter_create_persists_all_fields(): void {
        global $DB;

        $chapter = new \stdClass();
        $chapter->blockinstanceid = $this->instanceid;
        $chapter->title           = 'The Lost City';
        $chapter->intro_text      = 'A dark prologue.';
        $chapter->unlock_date     = 1800000000;
        $chapter->required_level  = 5;
        $chapter->sortorder       = 2;
        $chapter->id              = $DB->insert_record('block_playerhud_chapters', $chapter);

        $saved = $DB->get_record('block_playerhud_chapters', ['id' => $chapter->id], '*', MUST_EXIST);

        $this->assertEquals($this->instanceid, $saved->blockinstanceid);
        $this->assertEquals('The Lost City', $saved->title);
        $this->assertEquals('A dark prologue.', $saved->intro_text);
        $this->assertEquals(1800000000, $saved->unlock_date);
        $this->assertEquals(5, $saved->required_level);
        $this->assertEquals(2, $saved->sortorder);
    }

    /**
     * Updating a chapter changes only the modified fields.
     */
    public function test_chapter_update_changes_fields(): void {
        global $DB;

        $chapter = $this->create_chapter('Old Title', 0);

        $chapter->title          = 'New Title';
        $chapter->required_level = 3;
        $DB->update_record('block_playerhud_chapters', $chapter);

        $updated = $DB->get_record('block_playerhud_chapters', ['id' => $chapter->id], '*', MUST_EXIST);

        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals(3, $updated->required_level);
        $this->assertEquals($this->instanceid, $updated->blockinstanceid);
    }

    /**
     * Deleting a chapter removes it from the database.
     */
    public function test_chapter_delete_removes_record(): void {
        global $DB;

        $chapter = $this->create_chapter('Temporary Chapter', 0);
        $this->assertTrue($DB->record_exists('block_playerhud_chapters', ['id' => $chapter->id]));

        $DB->delete_records('block_playerhud_chapters', ['id' => $chapter->id]);

        $this->assertFalse($DB->record_exists('block_playerhud_chapters', ['id' => $chapter->id]));
    }

    /**
     * Chapters are scoped to their instance and do not appear for other instances.
     */
    public function test_chapter_listing_scoped_to_instance(): void {
        global $DB;

        $otherid = $this->create_second_instance();

        $this->create_chapter('Chapter 1', 0);
        $this->create_chapter('Chapter 2', 1);

        $foreign = new \stdClass();
        $foreign->blockinstanceid = $otherid;
        $foreign->title           = 'Foreign Chapter';
        $foreign->intro_text      = '';
        $foreign->unlock_date     = 0;
        $foreign->required_level  = 0;
        $foreign->sortorder       = 1;
        $DB->insert_record('block_playerhud_chapters', $foreign);

        $mychapters = $DB->get_records(
            'block_playerhud_chapters',
            ['blockinstanceid' => $this->instanceid]
        );

        $this->assertCount(2, $mychapters);
        foreach ($mychapters as $c) {
            $this->assertEquals($this->instanceid, $c->blockinstanceid);
        }
    }

    /**
     * Creating a trade persists every field correctly.
     */
    public function test_trade_create_persists_all_fields(): void {
        global $DB;

        $trade = new \stdClass();
        $trade->blockinstanceid = $this->instanceid;
        $trade->name            = 'Blacksmith Deal';
        $trade->centralized     = 0;
        $trade->onetime         = 1;
        $trade->groupid         = 7;
        $trade->timecreated     = time();
        $trade->id              = $DB->insert_record('block_playerhud_trades', $trade);

        $saved = $DB->get_record('block_playerhud_trades', ['id' => $trade->id], '*', MUST_EXIST);

        $this->assertEquals($this->instanceid, $saved->blockinstanceid);
        $this->assertEquals('Blacksmith Deal', $saved->name);
        $this->assertEquals(0, $saved->centralized);
        $this->assertEquals(1, $saved->onetime);
        $this->assertEquals(7, $saved->groupid);
    }

    /**
     * Updating a trade changes only the modified fields.
     */
    public function test_trade_update_changes_fields(): void {
        global $DB;

        $trade = $this->create_trade('Old Trade');

        $trade->name       = 'Updated Trade';
        $trade->centralized = 1;
        $trade->onetime    = 0;
        $DB->update_record('block_playerhud_trades', $trade);

        $updated = $DB->get_record('block_playerhud_trades', ['id' => $trade->id], '*', MUST_EXIST);

        $this->assertEquals('Updated Trade', $updated->name);
        $this->assertEquals(1, $updated->centralized);
        $this->assertEquals(0, $updated->onetime);
        $this->assertEquals($this->instanceid, $updated->blockinstanceid);
    }

    /**
     * Deleting a trade removes it from the database.
     */
    public function test_trade_delete_removes_record(): void {
        global $DB;

        $trade = $this->create_trade('Temporary Trade');
        $this->assertTrue($DB->record_exists('block_playerhud_trades', ['id' => $trade->id]));

        $DB->delete_records('block_playerhud_trades', ['id' => $trade->id]);

        $this->assertFalse($DB->record_exists('block_playerhud_trades', ['id' => $trade->id]));
    }

    /**
     * Trades are scoped to their instance and do not appear for other instances.
     */
    public function test_trade_listing_scoped_to_instance(): void {
        global $DB;

        $otherid = $this->create_second_instance();

        $this->create_trade('Trade A');
        $this->create_trade('Trade B');

        $foreign = new \stdClass();
        $foreign->blockinstanceid = $otherid;
        $foreign->name            = 'Foreign Trade';
        $foreign->centralized     = 1;
        $foreign->onetime         = 0;
        $foreign->groupid         = 0;
        $foreign->timecreated     = time();
        $DB->insert_record('block_playerhud_trades', $foreign);

        $mytrades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $this->instanceid]);

        $this->assertCount(2, $mytrades);
        foreach ($mytrades as $t) {
            $this->assertEquals($this->instanceid, $t->blockinstanceid);
        }
    }

    /**
     * Create a second block instance in the same course.
     *
     * @return int The new instance ID.
     */
    private function create_second_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        $bi = new \stdClass();
        $bi->blockname        = 'playerhud';
        $bi->parentcontextid  = $coursecontext->id;
        $bi->showinsubcontexts = 0;
        $bi->pagetypepattern  = 'course-view-*';
        $bi->defaultregion    = 'side-post';
        $bi->defaultweight    = 1;
        $bi->configdata       = base64_encode(serialize(new \stdClass()));
        $bi->timecreated      = time();
        $bi->timemodified     = time();
        return $DB->insert_record('block_instances', $bi);
    }

    /**
     * Create a minimal item in the test instance.
     *
     * @param string $name Item name.
     * @param int $xp XP value.
     * @param bool $secret Whether the item is secret.
     * @return \stdClass The created record with id set.
     */
    private function create_item(string $name, int $xp, bool $secret = false): \stdClass {
        global $DB;
        $item = new \stdClass();
        $item->blockinstanceid = $this->instanceid;
        $item->name            = $name;
        $item->xp              = $xp;
        $item->image           = '';
        $item->description     = '';
        $item->enabled         = 1;
        $item->secret          = $secret ? 1 : 0;
        $item->tradable        = 1;
        $item->timecreated     = time();
        $item->timemodified    = time();
        $item->id              = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Create a minimal chapter in the test instance.
     *
     * @param string $title Chapter title.
     * @param int $requiredlevel Minimum player level to unlock.
     * @return \stdClass The created record with id set.
     */
    private function create_chapter(string $title, int $requiredlevel): \stdClass {
        global $DB;
        $chapter = new \stdClass();
        $chapter->blockinstanceid = $this->instanceid;
        $chapter->title           = $title;
        $chapter->intro_text      = '';
        $chapter->unlock_date     = 0;
        $chapter->required_level  = $requiredlevel;
        $chapter->sortorder       = 1;
        $chapter->id              = $DB->insert_record('block_playerhud_chapters', $chapter);
        return $chapter;
    }

    /**
     * Create a minimal trade in the test instance.
     *
     * @param string $name Trade name.
     * @return \stdClass The created record with id set.
     */
    private function create_trade(string $name): \stdClass {
        global $DB;
        $trade = new \stdClass();
        $trade->blockinstanceid = $this->instanceid;
        $trade->name            = $name;
        $trade->centralized     = 1;
        $trade->onetime         = 0;
        $trade->groupid         = 0;
        $trade->timecreated     = time();
        $trade->id              = $DB->insert_record('block_playerhud_trades', $trade);
        return $trade;
    }
}
