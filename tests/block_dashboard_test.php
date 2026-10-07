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
 * Tests for how the block class treats the Dashboard.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * The block is not offered on the Dashboard, but an instance left there by an earlier release
 * must still be removable by its owner.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud
 */
final class block_dashboard_test extends advanced_testcase {
    /**
     * Builds the block object the way core does for the permission checks.
     *
     * @return \block_playerhud
     */
    private function make_block(): \block_playerhud {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/playerhud/block_playerhud.php');

        return new \block_playerhud();
    }

    /**
     * Makes a page of the given type in the given context.
     *
     * @param string $pagetype Page type, e.g. my-index.
     * @param \context $context Page context.
     * @return \moodle_page
     */
    private function make_page(string $pagetype, \context $context): \moodle_page {
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_pagetype($pagetype);
        $page->set_url('/my/');

        return $page;
    }

    /**
     * Core only shows "Delete block" when user_can_addto() is true. With the Dashboard no longer
     * an applicable format that answer became false, so an instance left on a Dashboard could
     * not be removed from the interface any more.
     */
    public function test_an_instance_left_on_the_dashboard_stays_deletable(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $page = $this->make_page('my-index', \context_user::instance($user->id));

        $this->assertTrue($this->make_block()->user_can_addto($page));
    }

    /**
     * That relaxation does not make the block addable: core also requires the page format to be
     * applicable, and the Dashboard is not.
     */
    public function test_the_dashboard_is_still_not_an_applicable_format(): void {
        global $CFG;
        require_once($CFG->libdir . '/blocklib.php');

        $this->assertFalse(blocks_name_allowed_in_format('playerhud', 'my-index'));
        $this->assertTrue(blocks_name_allowed_in_format('playerhud', 'course-view-topics'));
    }

    /**
     * Other pages keep the default rule: a user without the add capability cannot add it.
     */
    public function test_course_pages_keep_the_default_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $page = $this->make_page('course-view-topics', \context_course::instance($course->id));

        $this->assertFalse($this->make_block()->user_can_addto($page));

        $this->setAdminUser();
        $this->assertTrue($this->make_block()->user_can_addto($page));
    }
}
