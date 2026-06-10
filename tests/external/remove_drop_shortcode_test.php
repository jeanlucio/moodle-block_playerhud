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
 * Tests for the remove_drop_shortcode web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the remove_drop_shortcode web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\remove_drop_shortcode
 */
final class remove_drop_shortcode_test extends external_base_testcase {
    /**
     * Insert a drop for an item and return [dropid, code].
     *
     * @param int $itemid Target item ID.
     * @return array{0:int,1:string} Drop ID and its code.
     */
    private function create_drop(int $itemid): array {
        global $DB;
        $code = substr(md5(uniqid('', true)), 0, 12);
        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $this->instanceid,
            'itemid'          => $itemid,
            'name'            => 'Test drop',
            'maxusage'        => 5,
            'respawntime'     => 0,
            'code'            => $code,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        return [$dropid, $code];
    }

    /**
     * Success path: an existing shortcode is stripped from the content field.
     */
    public function test_remove_strips_existing_shortcode(): void {
        global $DB;

        $item        = $this->create_item($this->instanceid, 'Gem');
        [$dropid, $code] = $this->create_drop($item->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '[PLAYERHUD_DROP code=' . $code . ']' . "\n" . 'Keep this body',
        ]);

        $result = remove_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content'
        );

        $this->assertTrue($result['success']);
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringNotContainsString('[PLAYERHUD_DROP code=' . $code . ']', $content);
        $this->assertStringContainsString('Keep this body', $content);
    }

    /**
     * When the shortcode is absent the call still succeeds (idempotent no-op).
     */
    public function test_remove_absent_shortcode_is_noop_success(): void {
        $item        = $this->create_item($this->instanceid, 'Gem');
        [$dropid]    = $this->create_drop($item->id);

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => 'Nothing here',
        ]);

        $result = remove_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content'
        );

        $this->assertTrue($result['success']);
    }

    /**
     * A student without manage capability is rejected.
     */
    public function test_remove_requires_manage_capability(): void {
        $item     = $this->create_item($this->instanceid, 'Gem');
        [$dropid] = $this->create_drop($item->id);
        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '',
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        remove_drop_shortcode::execute($this->instanceid, $this->course->id, $dropid, $page->cmid, 'content');
    }
}
