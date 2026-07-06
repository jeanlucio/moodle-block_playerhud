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
 * Tests for the insert_drop_shortcode web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the insert_drop_shortcode web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\insert_drop_shortcode
 */
final class insert_drop_shortcode_test extends external_base_testcase {
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
     * Success path: the shortcode is prepended to the page content field.
     */
    public function test_insert_prepends_shortcode_to_content(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => 'Original body',
        ]);
        $item        = $this->create_item($this->instanceid, 'Gem');
        [$dropid, $code] = $this->create_drop($item->id);

        $result = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top'
        );

        $this->assertTrue($result['success']);
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringStartsWith('[PLAYERHUD_DROP code=' . $code . ']', $content);
        $this->assertStringContainsString('Original body', $content);
    }

    /**
     * Inserting the same drop twice is rejected the second time.
     */
    public function test_insert_duplicate_is_rejected(): void {
        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '',
        ]);
        $item       = $this->create_item($this->instanceid, 'Gem');
        [$dropid]   = $this->create_drop($item->id);

        insert_drop_shortcode::execute($this->instanceid, $this->course->id, $dropid, $page->cmid, 'content', 'top');
        $second = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top'
        );

        $this->assertFalse($second['success']);
    }

    /**
     * A drop from another block instance cannot be inserted (not found).
     */
    public function test_insert_rejects_drop_from_other_instance(): void {
        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '',
        ]);
        $instanceb   = $this->create_block_instance();
        $foreignitem = $this->create_item($instanceb, 'Gem');
        [$dropid]    = $this->create_drop($foreignitem->id);

        // The drop belongs to $foreignitem but we query under $this->instanceid.
        $result = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top'
        );

        $this->assertFalse($result['success']);
    }

    /**
     * A successful insert renames the drop to the activity it just landed in — this is what
     * makes the drops management table's "Localização/Nome" column useful for finding a drop
     * later, instead of just repeating the item's own name. Shared by both callers (the wizard's
     * auto-distribute step and this manual screen), so it only needs proving once, here.
     */
    public function test_insert_renames_drop_to_the_activity(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'name'    => 'Reactor Control Room',
            'content' => 'Original body',
        ]);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid] = $this->create_drop($item->id);

        $result = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top'
        );

        $this->assertTrue($result['success']);
        $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid], '*', MUST_EXIST);
        $this->assertSame('Reactor Control Room', $drop->name);
        $this->assertNotSame('Gem', $drop->name);
    }

    /**
     * mode=text with a custom label builds a shortcode carrying both attributes, so the filter
     * renders the drop as a plain text link with that exact label instead of the default card.
     */
    public function test_insert_text_mode_with_custom_label(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => 'Original body',
        ]);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid, $code] = $this->create_drop($item->id);

        $result = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top',
            'text',
            '💻'
        );

        $this->assertTrue($result['success']);
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringContainsString('[PLAYERHUD_DROP code=' . $code . ' mode=text text="💻"]', $content);
    }

    /**
     * An unrecognised mode value falls back to the default card form, ignoring any custom text.
     */
    public function test_insert_falls_back_to_card_mode_for_unknown_mode(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '',
        ]);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid, $code] = $this->create_drop($item->id);

        $result = insert_drop_shortcode::execute(
            $this->instanceid,
            $this->course->id,
            $dropid,
            $page->cmid,
            'content',
            'top',
            'bogus',
            'ignored'
        );

        $this->assertTrue($result['success']);
        $content = $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringContainsString('[PLAYERHUD_DROP code=' . $code . ']', $content);
    }

    /**
     * A student without manage capability is rejected.
     */
    public function test_insert_requires_manage_capability(): void {
        $page = $this->getDataGenerator()->create_module('page', [
            'course'  => $this->course->id,
            'content' => '',
        ]);
        $item     = $this->create_item($this->instanceid, 'Gem');
        [$dropid] = $this->create_drop($item->id);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        insert_drop_shortcode::execute($this->instanceid, $this->course->id, $dropid, $page->cmid, 'content', 'top');
    }
}
