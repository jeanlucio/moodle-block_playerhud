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
     * Regression test for the N+1 performance finding: execute_batch() must insert
     * shortcodes for several drops in one call, each into its own target activity, with the
     * same per-item success/failure semantics as calling execute() once per drop.
     */
    public function test_execute_batch_inserts_shortcodes_for_several_drops(): void {
        global $DB;

        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid1, $code1] = $this->create_drop($item->id);
        [$dropid2, $code2] = $this->create_drop($item->id);

        $results = insert_drop_shortcode::execute_batch($this->instanceid, $this->course->id, [
            ['dropid' => $dropid1, 'cmid' => $page1->cmid, 'field' => 'content', 'position' => 'top'],
            ['dropid' => $dropid2, 'cmid' => $page2->cmid, 'field' => 'content', 'position' => 'top'],
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);

        $content1 = $DB->get_field('page', 'content', ['id' => $page1->id]);
        $content2 = $DB->get_field('page', 'content', ['id' => $page2->id]);
        $this->assertStringContainsString('code=' . $code1, $content1);
        $this->assertStringContainsString('code=' . $code2, $content2);

        // Same rename-to-activity-name side effect as execute().
        $drop1name = $DB->get_field('block_playerhud_drops', 'name', ['id' => $dropid1]);
        $this->assertEquals($page1->name, $drop1name);
    }

    /**
     * Two drops landing on the same activity and field in one batch must both survive: the
     * preloaded field-value cache is read once at the start of the batch, so without in-place
     * invalidation the second item would overwrite the first item's shortcode instead of
     * appending after it.
     */
    public function test_execute_batch_two_drops_into_the_same_activity_and_field_both_land(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid1, $code1] = $this->create_drop($item->id);
        [$dropid2, $code2] = $this->create_drop($item->id);

        $results = insert_drop_shortcode::execute_batch($this->instanceid, $this->course->id, [
            ['dropid' => $dropid1, 'cmid' => $page->cmid, 'field' => 'content', 'position' => 'top'],
            ['dropid' => $dropid2, 'cmid' => $page->cmid, 'field' => 'content', 'position' => 'bottom'],
        ]);

        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);

        $content = (string) $DB->get_field('page', 'content', ['id' => $page->id]);
        $this->assertStringContainsString('code=' . $code1, $content);
        $this->assertStringContainsString('code=' . $code2, $content);
    }

    /**
     * The preloaded drop and field-value caches keep the number of reads roughly constant as
     * the batch grows, instead of scaling linearly with the number of items — the actual
     * performance property the preload methods exist for.
     */
    public function test_execute_batch_read_count_does_not_scale_with_batch_size(): void {
        global $DB;

        $item = $this->create_item($this->instanceid, 'Gem');

        $smallitems = [];
        for ($i = 0; $i < 2; $i++) {
            $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
            [$dropid] = $this->create_drop($item->id);
            $smallitems[] = ['dropid' => $dropid, 'cmid' => $page->cmid, 'field' => 'content', 'position' => 'top'];
        }

        $bigitems = [];
        for ($i = 0; $i < 8; $i++) {
            $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
            [$dropid] = $this->create_drop($item->id);
            $bigitems[] = ['dropid' => $dropid, 'cmid' => $page->cmid, 'field' => 'content', 'position' => 'top'];
        }

        $readsbefore = $DB->perf_get_reads();
        insert_drop_shortcode::execute_batch($this->instanceid, $this->course->id, $smallitems);
        $smallreads = $DB->perf_get_reads() - $readsbefore;

        $readsbefore = $DB->perf_get_reads();
        insert_drop_shortcode::execute_batch($this->instanceid, $this->course->id, $bigitems);
        $bigreads = $DB->perf_get_reads() - $readsbefore;

        // A 4x larger batch (2 -> 8 items) must not come close to a 4x read count: with the old
        // one-query-per-item design bigreads would be ~4x smallreads; with preloading it stays
        // within a couple of extra reads regardless of batch size.
        $this->assertLessThan($smallreads + 3, $bigreads);
    }

    /**
     * A batch entry targeting an invalid field fails on its own without aborting the rest
     * of the batch.
     */
    public function test_execute_batch_one_failure_does_not_block_the_rest(): void {
        global $DB;

        $page1 = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
        $page2 = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id, 'content' => '']);
        $item = $this->create_item($this->instanceid, 'Gem');
        [$dropid1] = $this->create_drop($item->id);
        [$dropid2, $code2] = $this->create_drop($item->id);

        $results = insert_drop_shortcode::execute_batch($this->instanceid, $this->course->id, [
            ['dropid' => $dropid1, 'cmid' => $page1->cmid, 'field' => 'not_a_real_field', 'position' => 'top'],
            ['dropid' => $dropid2, 'cmid' => $page2->cmid, 'field' => 'content', 'position' => 'top'],
        ]);

        $this->assertFalse($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $content2 = $DB->get_field('page', 'content', ['id' => $page2->id]);
        $this->assertStringContainsString('code=' . $code2, $content2);
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
     * makes the drops management table's "Location / Name" column useful for finding a drop
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
