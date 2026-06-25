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
 * Tests for the drops controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use stdClass;

/**
 * Tests for the drops controller persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\drops
 */
final class drops_test extends advanced_testcase {
    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    protected function make_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Creates an item for the block instance.
     *
     * @param int $instanceid Owning block instance ID.
     * @return int The new item ID.
     */
    protected function make_item(int $instanceid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Item',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Inserts a drop directly for the item.
     *
     * @param int $instanceid Owning block instance ID.
     * @param int $itemid Item the drop belongs to.
     * @return int The new drop ID.
     */
    protected function seed_drop(int $instanceid, int $itemid): int {
        global $DB;

        return (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid'          => $itemid,
            'name'            => 'Old spot',
            'maxusage'        => 1,
            'respawntime'     => 0,
            'code'            => 'OLDXYZ',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Builds submitted drop form data with the common defaults.
     *
     * @param int $instanceid Block instance ID.
     * @param int $itemid Item ID.
     * @param array $overrides Field overrides merged over the defaults.
     * @return stdClass
     */
    protected function drop_data(int $instanceid, int $itemid, array $overrides = []): stdClass {
        return (object) array_merge([
            'instanceid'  => $instanceid,
            'itemid'      => $itemid,
            'name'        => 'Forest spot',
            'respawntime' => 0,
            'maxusage'    => 1,
            'unlimited'   => 0,
            'id'          => 0,
        ], $overrides);
    }

    /**
     * A new drop is inserted with a generated code and the submitted limits.
     *
     * @covers ::save_drop
     */
    public function test_save_drop_inserts_with_code(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);

        $data = $this->drop_data($instanceid, $itemid, [
            'name'        => 'Hidden cave',
            'respawntime' => 60,
            'maxusage'    => 5,
        ]);

        $dropid = (new drops())->save_drop($data);

        $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid], '*', MUST_EXIST);
        $this->assertSame($instanceid, (int) $drop->blockinstanceid);
        $this->assertSame($itemid, (int) $drop->itemid);
        $this->assertSame('Hidden cave', $drop->name);
        $this->assertSame(60, (int) $drop->respawntime);
        $this->assertSame(5, (int) $drop->maxusage);
        $this->assertNotEmpty($drop->code);
    }

    /**
     * The unlimited flag forces a max usage of zero (infinite).
     *
     * @covers ::save_drop
     */
    public function test_save_drop_unlimited_sets_maxusage_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);

        $data = $this->drop_data($instanceid, $itemid, ['unlimited' => 1, 'maxusage' => 5]);

        $dropid = (new drops())->save_drop($data);

        $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $drop->maxusage);
    }

    /**
     * On update the ownership fields come from the DB, not the submitted form.
     *
     * @covers ::save_drop
     */
    public function test_save_drop_update_preserves_ownership(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $otheritem = $this->make_item($instanceid);
        $dropid = $this->seed_drop($instanceid, $itemid);

        // Submit a tampered itemid; it must be ignored in favour of the stored one.
        $data = $this->drop_data($instanceid, $otheritem, ['id' => $dropid, 'name' => 'Renamed']);

        $returned = (new drops())->save_drop($data);

        $this->assertSame($dropid, $returned);
        $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid], '*', MUST_EXIST);
        $this->assertSame('Renamed', $drop->name);
        $this->assertSame($itemid, (int) $drop->itemid);
    }

    /**
     * Updating a drop under a different instance is rejected.
     *
     * @covers ::save_drop
     */
    public function test_save_drop_rejects_foreign_instance_on_update(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $dropid = $this->seed_drop($instanceid, $itemid);

        $instanceb = $this->make_instance();
        $data = $this->drop_data($instanceb, $itemid, ['id' => $dropid, 'name' => 'Hijack']);

        $this->expectException(\moodle_exception::class);
        (new drops())->save_drop($data);
    }

    /**
     * Creating a drop for an item from another instance is rejected.
     *
     * @covers ::save_drop
     */
    public function test_save_drop_rejects_foreign_item_on_insert(): void {
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $foreignitem = $this->make_item($this->make_instance());

        $data = $this->drop_data($instanceid, $foreignitem);

        $this->expectException(\dml_missing_record_exception::class);
        (new drops())->save_drop($data);
    }

    /**
     * Deleting a drop removes it from its block instance.
     *
     * @covers ::delete_drop
     */
    public function test_delete_drop_removes_drop(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $dropid = $this->seed_drop($instanceid, $itemid);

        (new drops())->delete_drop($dropid, $instanceid);

        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
    }

    /**
     * A drop owned by another instance is not deleted.
     *
     * @covers ::delete_drop
     */
    public function test_delete_drop_foreign_instance_is_noop(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itemid = $this->make_item($instancea);
        $dropid = $this->seed_drop($instancea, $itemid);

        (new drops())->delete_drop($dropid, $instanceb);

        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
    }

    /**
     * Bulk delete removes only the drops owned by the instance and counts them.
     *
     * @covers ::bulk_delete_drops
     */
    public function test_bulk_delete_drops_removes_only_owned(): void {
        global $DB;
        $this->resetAfterTest();
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $itema = $this->make_item($instancea);
        $itemb = $this->make_item($instanceb);
        $dropa1 = $this->seed_drop($instancea, $itema);
        $dropa2 = $this->seed_drop($instancea, $itema);
        $dropb = $this->seed_drop($instanceb, $itemb);

        $count = (new drops())->bulk_delete_drops([$dropa1, $dropa2, $dropb], $instancea);

        $this->assertSame(2, $count);
        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $dropa1]));
        $this->assertFalse($DB->record_exists('block_playerhud_drops', ['id' => $dropa2]));
        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $dropb]));
    }

    /**
     * Bulk delete with no ids deletes nothing and returns zero.
     *
     * @covers ::bulk_delete_drops
     */
    public function test_bulk_delete_drops_empty_returns_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $instanceid = $this->make_instance();
        $itemid = $this->make_item($instanceid);
        $dropid = $this->seed_drop($instanceid, $itemid);

        $count = (new drops())->bulk_delete_drops([], $instanceid);

        $this->assertSame(0, $count);
        $this->assertTrue($DB->record_exists('block_playerhud_drops', ['id' => $dropid]));
    }
}
