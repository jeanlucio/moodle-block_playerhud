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

/**
 * Tests for the setup_playercoin_drop web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the setup_playercoin_drop web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\setup_playercoin_drop
 */
final class setup_playercoin_drop_test extends external_base_testcase {
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

        $result = setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);

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

        $result = setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);

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
        setup_playercoin_drop::execute($this->instanceid, $this->course->id, $foreignitem->id);

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
            'No drop must be created after a rejected cross-instance item.'
        );
    }

    /**
     * A courseid that does not own the block instance must be rejected, even for an
     * otherwise-valid item — the news forum lookup must never run against a foreign course.
     */
    public function test_setup_playercoin_drop_rejects_course_not_owning_the_instance(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course();
        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        $this->expectException(\moodle_exception::class);
        setup_playercoin_drop::execute($this->instanceid, $othercourse->id, $item->id);

        $this->assertEquals(
            0,
            $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
            'No drop must be created when courseid does not own the block instance.'
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

        setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);

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
        setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);
    }
}
