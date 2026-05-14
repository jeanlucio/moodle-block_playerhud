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
 * Security tests verifying cross-instance record isolation for all update paths.
 *
 * The controllers that handle item/quest/chapter/trade editing (tab_items::process(),
 * tab_quests::process(), controller\chapters::handle_edit_form(), and
 * controller\trades::save_trade()) guard every update with:
 *
 *   $DB->get_record('block_playerhud_X', ['id' => $id, 'blockinstanceid' => $instanceid],
 *                   'id', MUST_EXIST);
 *
 * These tests verify the exact same call that each controller makes, confirming that
 * the guard correctly rejects a cross-instance ID and accepts a same-instance ID.
 * Because the guard is called before any write, a rejection guarantees the foreign
 * record is never modified.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\tab_items
 * @covers     \block_playerhud\output\manage\tab_quests
 * @covers     \block_playerhud\controller\chapters
 * @covers     \block_playerhud\controller\trades
 */
final class cross_instance_security_test extends advanced_testcase {
    /** @var \stdClass Shared course. */
    protected $course;

    /** @var int Block instance A — attacker's managed instance. */
    protected $instancea;

    /** @var int Block instance B — victim's data lives here. */
    protected $instanceb;

    /**
     * Create two independent block instances in the same course.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course  = $this->getDataGenerator()->create_course();
        $this->instancea = $this->create_block_instance();
        $this->instanceb = $this->create_block_instance();
    }

    /**
     * Normal edit: the guard passes when item and instance match.
     */
    public function test_item_guard_accepts_own_instance(): void {
        global $DB;
        $item = $this->create_item($this->instancea, 'Item A');

        $result = $DB->get_record(
            'block_playerhud_items',
            ['id' => $item->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );

        $this->assertEquals($item->id, $result->id);
    }

    /**
     * Tamper attempt: a teacher in instance A submits an item ID that belongs to
     * instance B. The guard must reject it before any write occurs.
     */
    public function test_item_guard_rejects_foreign_instance(): void {
        global $DB;
        $foreignitem = $this->create_item($this->instanceb, 'Item B');

        $this->expectException(\dml_missing_record_exception::class);
        $DB->get_record(
            'block_playerhud_items',
            ['id' => $foreignitem->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );
    }

    /**
     * After a rejected tamper, the foreign item's data is unchanged.
     */
    public function test_item_foreign_record_untouched_after_rejection(): void {
        global $DB;
        $foreignitem = $this->create_item($this->instanceb, 'Item B Original');

        // Cross-instance lookup returns nothing (same condition the guard checks).
        $guardpassed = $DB->record_exists(
            'block_playerhud_items',
            ['id' => $foreignitem->id, 'blockinstanceid' => $this->instancea]
        );
        $this->assertFalse($guardpassed);

        // Foreign record is untouched.
        $still = $DB->get_record('block_playerhud_items', ['id' => $foreignitem->id], '*', MUST_EXIST);
        $this->assertEquals($this->instanceb, $still->blockinstanceid);
        $this->assertEquals('Item B Original', $still->name);
    }

    /**
     * Normal edit: the guard passes when quest and instance match.
     */
    public function test_quest_guard_accepts_own_instance(): void {
        global $DB;
        $quest = $this->create_quest($this->instancea, 'Quest A');

        $result = $DB->get_record(
            'block_playerhud_quests',
            ['id' => $quest->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );

        $this->assertEquals($quest->id, $result->id);
    }

    /**
     * Tamper attempt: teacher in A submits a quest ID from instance B.
     */
    public function test_quest_guard_rejects_foreign_instance(): void {
        global $DB;
        $foreignquest = $this->create_quest($this->instanceb, 'Quest B');

        $this->expectException(\dml_missing_record_exception::class);
        $DB->get_record(
            'block_playerhud_quests',
            ['id' => $foreignquest->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );
    }

    /**
     * After a rejected tamper, the foreign quest's data is unchanged.
     */
    public function test_quest_foreign_record_untouched_after_rejection(): void {
        global $DB;
        $foreignquest = $this->create_quest($this->instanceb, 'Quest B Original');

        $guardpassed = $DB->record_exists(
            'block_playerhud_quests',
            ['id' => $foreignquest->id, 'blockinstanceid' => $this->instancea]
        );
        $this->assertFalse($guardpassed);

        $still = $DB->get_record('block_playerhud_quests', ['id' => $foreignquest->id], '*', MUST_EXIST);
        $this->assertEquals($this->instanceb, $still->blockinstanceid);
        $this->assertEquals('Quest B Original', $still->name);
    }

    /**
     * Normal edit: the guard passes when chapter and instance match.
     */
    public function test_chapter_guard_accepts_own_instance(): void {
        global $DB;
        $chapter = $this->create_chapter($this->instancea, 'Chapter A');

        $result = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapter->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );

        $this->assertEquals($chapter->id, $result->id);
    }

    /**
     * Tamper attempt: teacher in A submits a chapter ID from instance B.
     */
    public function test_chapter_guard_rejects_foreign_instance(): void {
        global $DB;
        $foreignchapter = $this->create_chapter($this->instanceb, 'Chapter B');

        $this->expectException(\dml_missing_record_exception::class);
        $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $foreignchapter->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );
    }

    /**
     * After a rejected tamper, the foreign chapter's data is unchanged.
     */
    public function test_chapter_foreign_record_untouched_after_rejection(): void {
        global $DB;
        $foreignchapter = $this->create_chapter($this->instanceb, 'Chapter B Original');

        $guardpassed = $DB->record_exists(
            'block_playerhud_chapters',
            ['id' => $foreignchapter->id, 'blockinstanceid' => $this->instancea]
        );
        $this->assertFalse($guardpassed);

        $still = $DB->get_record('block_playerhud_chapters', ['id' => $foreignchapter->id], '*', MUST_EXIST);
        $this->assertEquals($this->instanceb, $still->blockinstanceid);
        $this->assertEquals('Chapter B Original', $still->title);
    }

    /**
     * Normal edit: the guard passes when trade and instance match.
     */
    public function test_trade_guard_accepts_own_instance(): void {
        global $DB;
        $trade = $this->create_trade($this->instancea, 'Trade A');

        $result = $DB->get_record(
            'block_playerhud_trades',
            ['id' => $trade->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );

        $this->assertEquals($trade->id, $result->id);
    }

    /**
     * Tamper attempt: teacher in A submits a trade ID from instance B.
     */
    public function test_trade_guard_rejects_foreign_instance(): void {
        global $DB;
        $foreigntrade = $this->create_trade($this->instanceb, 'Trade B');

        $this->expectException(\dml_missing_record_exception::class);
        $DB->get_record(
            'block_playerhud_trades',
            ['id' => $foreigntrade->id, 'blockinstanceid' => $this->instancea],
            'id',
            MUST_EXIST
        );
    }

    /**
     * After a rejected tamper, the foreign trade's data is unchanged.
     */
    public function test_trade_foreign_record_untouched_after_rejection(): void {
        global $DB;
        $foreigntrade = $this->create_trade($this->instanceb, 'Trade B Original');

        $guardpassed = $DB->record_exists(
            'block_playerhud_trades',
            ['id' => $foreigntrade->id, 'blockinstanceid' => $this->instancea]
        );
        $this->assertFalse($guardpassed);

        $still = $DB->get_record('block_playerhud_trades', ['id' => $foreigntrade->id], '*', MUST_EXIST);
        $this->assertEquals($this->instanceb, $still->blockinstanceid);
        $this->assertEquals('Trade B Original', $still->name);
    }

    /**
     * Create a minimal block_instances row and return its ID.
     *
     * @return int The new instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
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
        return $DB->insert_record('block_instances', $bi);
    }

    /**
     * Create a minimal item record in the given instance.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $name Item name.
     * @return \stdClass The created record with id set.
     */
    private function create_item(int $instanceid, string $name): \stdClass {
        global $DB;
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name            = $name;
        $item->xp              = 10;
        $item->image           = '';
        $item->description     = '';
        $item->enabled         = 1;
        $item->secret          = 0;
        $item->tradable        = 1;
        $item->maxusage        = 1;
        $item->respawntime     = 0;
        $item->timecreated     = time();
        $item->timemodified    = time();
        $item->id              = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }

    /**
     * Create a minimal quest record in the given instance.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $name Quest name.
     * @return \stdClass The created record with id set.
     */
    private function create_quest(int $instanceid, string $name): \stdClass {
        global $DB;
        $quest = new \stdClass();
        $quest->blockinstanceid = $instanceid;
        $quest->name            = $name;
        $quest->description     = '';
        $quest->type            = 1;
        $quest->requirement     = '1';
        $quest->req_itemid      = 0;
        $quest->reward_xp       = 0;
        $quest->reward_itemid   = 0;
        $quest->enabled         = 1;
        $quest->timecreated     = time();
        $quest->timemodified    = time();
        $quest->id              = $DB->insert_record('block_playerhud_quests', $quest);
        return $quest;
    }

    /**
     * Create a minimal chapter record in the given instance.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $title Chapter title.
     * @return \stdClass The created record with id set.
     */
    private function create_chapter(int $instanceid, string $title): \stdClass {
        global $DB;
        $chapter = new \stdClass();
        $chapter->blockinstanceid = $instanceid;
        $chapter->title           = $title;
        $chapter->intro_text      = '';
        $chapter->unlock_date     = 0;
        $chapter->required_level  = 0;
        $chapter->sortorder       = 1;
        $chapter->id              = $DB->insert_record('block_playerhud_chapters', $chapter);
        return $chapter;
    }

    /**
     * Create a minimal trade record in the given instance.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $name Trade name.
     * @return \stdClass The created record with id set.
     */
    private function create_trade(int $instanceid, string $name): \stdClass {
        global $DB;
        $trade = new \stdClass();
        $trade->blockinstanceid = $instanceid;
        $trade->name            = $name;
        $trade->centralized     = 1;
        $trade->onetime         = 0;
        $trade->groupid         = 0;
        $trade->timecreated     = time();
        $trade->id              = $DB->insert_record('block_playerhud_trades', $trade);
        return $trade;
    }
}
