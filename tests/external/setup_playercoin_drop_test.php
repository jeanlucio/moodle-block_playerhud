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
 * @covers     \block_playerhud\utils
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
     * The news forum description is shown on the course page from the course cache, so the cache
     * must be rebuilt: the shortcode used to appear only after some other action rebuilt it. The
     * change is also announced to Moodle, and the forum records when it was modified.
     */
    public function test_setup_playercoin_drop_refreshes_the_course_page_and_announces_the_change(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id, 'type' => 'news', 'intro' => 'Welcome', 'showdescription' => 1,
        ]);
        $DB->set_field('forum', 'timemodified', 1000, ['id' => $forum->id]);
        // Build the course cache with the old description, as a visit to the course page does.
        rebuild_course_cache($this->course->id, true);
        $this->assertStringNotContainsString(
            'PLAYERHUD_DROP',
            get_fast_modinfo($this->course->id)->get_cm($forum->cmid)->content
        );
        $item = $this->create_item($this->instanceid, 'PlayerCoin');
        $sink = $this->redirectEvents();

        setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);

        $this->assertStringContainsString(
            'PLAYERHUD_DROP',
            get_fast_modinfo($this->course->id)->get_cm($forum->cmid)->content
        );
        $events = array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof \core\event\course_module_updated
        );
        $this->assertCount(1, $events);
        $this->assertGreaterThan(1000, (int) $DB->get_field('forum', 'timemodified', ['id' => $forum->id]));
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

        // A try/catch rather than expectException(): the exception ends the test there, and the
        // check that nothing was written has to run after it.
        try {
            setup_playercoin_drop::execute($this->instanceid, $this->course->id, $foreignitem->id);
            $this->fail('Expected a dml_missing_record_exception for an item of another instance.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertEquals(
                0,
                $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
                'No drop must be created after a rejected cross-instance item.'
            );
        }
    }

    /**
     * A courseid that does not own the block instance must be rejected, even for an
     * otherwise-valid item — the news forum lookup must never run against a foreign course.
     */
    public function test_setup_playercoin_drop_rejects_course_not_owning_the_instance(): void {
        global $DB;

        $othercourse = $this->getDataGenerator()->create_course();
        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        try {
            setup_playercoin_drop::execute($this->instanceid, $othercourse->id, $item->id);
            $this->fail('Expected an accessdenied exception for a course that does not own the instance.');
        } catch (\moodle_exception $e) {
            $this->assertSame('accessdenied', $e->errorcode);
            $this->assertEquals(
                0,
                $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]),
                'No drop must be created when courseid does not own the block instance.'
            );
        }
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
     * A teacher forbidden to edit the news forum by a role override on it cannot put the drop in its
     * description, and no drop is created.
     */
    public function test_setup_playercoin_drop_refuses_a_forum_the_user_may_not_edit(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => 'Welcome',
        ]);
        $item = $this->create_item($this->instanceid, 'PlayerCoin');
        $this->setUser($this->create_teacher_forbidden_to_edit($forum->cmid));

        $result = setup_playercoin_drop::execute($this->instanceid, $this->course->id, $item->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Welcome', $DB->get_field('forum', 'intro', ['id' => $forum->id]));
        $this->assertSame(0, $DB->count_records('block_playerhud_drops', ['blockinstanceid' => $this->instanceid]));
    }

    /**
     * create_drop() makes the PlayerCoin's infinite drop and returns its code, without touching
     * the news forum: the wizard uses it to leave the drop waiting in "Distribute drops".
     */
    public function test_create_drop_makes_an_unplaced_infinite_drop(): void {
        global $DB;

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type'   => 'news',
            'intro'  => 'Welcome',
        ]);
        $item = $this->create_item($this->instanceid, 'PlayerCoin');

        $created = setup_playercoin_drop::create_drop($this->instanceid, $item->id);

        $drop = $DB->get_record('block_playerhud_drops', ['id' => $created['dropid']], '*', MUST_EXIST);
        $this->assertSame($created['code'], $drop->code);
        $this->assertSame(0, (int) $drop->maxusage);
        $this->assertSame(3600, (int) $drop->respawntime);
        $this->assertSame((int) $item->id, (int) $drop->itemid);
        $this->assertSame('Welcome', $DB->get_field('forum', 'intro', ['id' => $forum->id]));
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
