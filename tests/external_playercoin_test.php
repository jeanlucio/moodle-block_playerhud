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
use block_playerhud\external;

/**
 * Tests for the PlayerCoin, Avatar Pack and Drop Setup web service methods.
 *
 * Covers three web services added in v1.5:
 *   - create_playercoin  — idempotent quick-create of the PlayerCoin item
 *   - create_avatar_pack — batch-create of 17 pre-defined avatar items
 *   - setup_playercoin_drop — attach an infinite drop to the course news forum
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external
 */
final class external_playercoin_test extends advanced_testcase {
    /** @var \stdClass Shared course for all tests. */
    protected $course;

    /** @var int Primary block instance ID. */
    protected int $instanceid;

    /**
     * Create a fresh course and block instance for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->course    = $this->getDataGenerator()->create_course();
        $this->instanceid = $this->create_block_instance();
    }

    /**
     * First call creates the PlayerCoin item and returns created=true.
     */
    public function test_create_playercoin_creates_new_item(): void {
        global $DB;

        $result = external::create_playercoin($this->instanceid, $this->course->id);

        $this->assertTrue($result['created']);
        $this->assertGreaterThan(0, $result['itemid']);
        $this->assertStringContainsString((string) $result['itemid'], $result['edit_url']);

        $item = $DB->get_record('block_playerhud_items', ['id' => $result['itemid']], '*', MUST_EXIST);
        $this->assertEquals('PlayerCoin', $item->name);
        $this->assertEquals('🪙', $item->image);
        $this->assertEquals($this->instanceid, (int) $item->blockinstanceid);
        $this->assertEquals('playercoin', $item->action_type, 'PlayerCoin must be tagged with action_type=playercoin.');
    }

    /**
     * Second call returns created=false and the same itemid — no duplicate created.
     */
    public function test_create_playercoin_idempotent_returns_existing(): void {
        global $DB;

        $first  = external::create_playercoin($this->instanceid, $this->course->id);
        $second = external::create_playercoin($this->instanceid, $this->course->id);

        $this->assertFalse($second['created']);
        $this->assertEquals($first['itemid'], $second['itemid']);

        $count = $DB->count_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid,
            'action_type'     => 'playercoin',
        ]);
        $this->assertEquals(1, $count, 'Only one PlayerCoin must exist after two calls.');
    }

    /**
     * A student without block/playerhud:manage must not be able to create PlayerCoin.
     */
    public function test_create_playercoin_requires_manage_capability(): void {
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        external::create_playercoin($this->instanceid, $this->course->id);
    }

    /**
     * A fresh instance receives all 17 pre-defined avatar items.
     */
    public function test_create_avatar_pack_creates_17_items(): void {
        global $DB;

        $result = external::create_avatar_pack($this->instanceid, $this->course->id);

        $this->assertEquals(17, $result['created']);
        $total = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(17, $total);
    }

    /**
     * Every item created by the pack must have action_type = 'avatar_profile'.
     */
    public function test_create_avatar_pack_all_items_have_avatar_action_type(): void {
        global $DB;

        external::create_avatar_pack($this->instanceid, $this->course->id);

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        foreach ($items as $item) {
            $this->assertEquals(
                'avatar_profile',
                $item->action_type,
                "Item '{$item->name}' must have action_type=avatar_profile."
            );
        }
    }

    /**
     * An item already in the instance with the same emoji image is skipped,
     * so the pack creates 16 instead of 17.
     */
    public function test_create_avatar_pack_skips_existing_emoji(): void {
        global $DB;

        // Pre-create one item using the first avatar's emoji (🧛🏻‍♂️).
        $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid'  => $this->instanceid,
            'name'             => 'Pre-existing vampire',
            'image'            => '🧛🏻‍♂️',
            'description'      => '',
            'xp'               => 0,
            'enabled'          => 1,
            'tradable'         => 0,
            'secret'           => 0,
            'required_class_id' => '0',
            'action_type'      => 'avatar_profile',
            'action_value'     => '',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);

        $result = external::create_avatar_pack($this->instanceid, $this->course->id);

        $this->assertEquals(16, $result['created'], 'One avatar with matching emoji must be skipped.');
        $total = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $this->assertEquals(17, $total, '16 new + 1 pre-existing = 17 total.');
    }

    /**
     * Calling create_avatar_pack twice returns created=0 on the second call.
     */
    public function test_create_avatar_pack_idempotent_second_call_creates_zero(): void {
        external::create_avatar_pack($this->instanceid, $this->course->id);

        $second = external::create_avatar_pack($this->instanceid, $this->course->id);

        $this->assertEquals(0, $second['created'], 'No new items must be created when all emojis already exist.');
    }

    /**
     * Success path: drop is created in DB and shortcode is prepended to the
     * news forum intro.
     */
    public function test_setup_playercoin_drop_success(): void {
        global $DB;

        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => '',
        ]);
        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        $result = external::setup_playercoin_drop($this->instanceid, $this->course->id, $item->id);

        $this->assertTrue($result['success']);

        $drop = $DB->get_record(
            'block_playerhud_drops',
            ['blockinstanceid' => $this->instanceid, 'itemid' => $item->id],
            '*',
            MUST_EXIST
        );
        $this->assertEquals(0, (int) $drop->maxusage, 'Drop must be infinite (maxusage=0).');
        $this->assertEquals(3600, (int) $drop->respawntime);
        $this->assertNotEmpty($drop->code);

        $forum = $DB->get_record_sql(
            "SELECT f.intro FROM {forum} f WHERE f.course = :cid AND f.type = 'news'",
            ['cid' => $this->course->id]
        );
        $this->assertStringStartsWith('[PLAYERHUD_DROP code=', $forum->intro);
        $this->assertStringContainsString($drop->code, $forum->intro);
    }

    /**
     * When the course has no news forum the WS returns success=false and
     * creates no drop record.
     */
    public function test_setup_playercoin_drop_no_forum_returns_failure(): void {
        global $DB;

        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        $result = external::setup_playercoin_drop($this->instanceid, $this->course->id, $item->id);

        $this->assertFalse($result['success']);
        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
            'No drop must be created when the news forum does not exist.'
        );
    }

    /**
     * Passing an itemid that belongs to a different block instance must be
     * rejected — no drop is created and a DB exception is thrown.
     */
    public function test_setup_playercoin_drop_rejects_item_from_other_instance(): void {
        global $DB;

        $instanceb   = $this->create_block_instance();
        $foreignitem = $this->create_item($instanceb, 'PlayerCoin');

        $this->expectException(\dml_missing_record_exception::class);
        external::setup_playercoin_drop($this->instanceid, $this->course->id, $foreignitem->id);

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
            'No drop must be created after a rejected cross-instance item.'
        );
    }

    /**
     * When the news forum already has an intro the shortcode is prepended and
     * the original intro is preserved after a <br>.
     */
    public function test_setup_playercoin_drop_prepends_to_existing_intro(): void {
        global $DB;

        $existingintro = '<p>Welcome to the course!</p>';
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => $existingintro,
        ]);
        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        external::setup_playercoin_drop($this->instanceid, $this->course->id, $item->id);

        $forum = $DB->get_record_sql(
            "SELECT f.intro FROM {forum} f WHERE f.course = :cid AND f.type = 'news'",
            ['cid' => $this->course->id]
        );
        $this->assertStringStartsWith('[PLAYERHUD_DROP code=', $forum->intro);
        $this->assertStringContainsString('<br>' . $existingintro, $forum->intro);
    }

    /**
     * A student without block/playerhud:manage must not be able to setup a drop.
     */
    public function test_setup_playercoin_drop_requires_manage_capability(): void {
        $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
        ]);
        $item    = $this->create_item($this->instanceid, 'PlayerCoin');
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        external::setup_playercoin_drop($this->instanceid, $this->course->id, $item->id);
    }

    /**
     * Insert a minimal block_instances row and return its ID.
     *
     * @return int The new instance ID.
     */
    private function create_block_instance(): int {
        global $DB;
        $coursecontext = \context_course::instance($this->course->id);
        return $DB->insert_record('block_instances', (object) [
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
     * Insert a minimal item record and return it with id set.
     *
     * @param int $instanceid Target block instance ID.
     * @param string $name Item name.
     * @return \stdClass The inserted record.
     */
    private function create_item(int $instanceid, string $name): \stdClass {
        global $DB;
        $item = (object) [
            'blockinstanceid'  => $instanceid,
            'name'             => $name,
            'image'            => '🪙',
            'description'      => '',
            'xp'               => 0,
            'enabled'          => 1,
            'tradable'         => 1,
            'secret'           => 0,
            'required_class_id' => '0',
            'action_type'      => '',
            'action_value'     => '',
            'timecreated'      => time(),
            'timemodified'     => time(),
        ];
        $item->id = $DB->insert_record('block_playerhud_items', $item);
        return $item;
    }
}
