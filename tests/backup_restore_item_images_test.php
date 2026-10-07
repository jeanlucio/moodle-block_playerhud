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
 * Tests for how item images travel through backup and restore.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * Item images must be restored exactly once, under the restored item's own id.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class backup_restore_item_images_test extends advanced_testcase {
    /**
     * Each uploaded item image is restored once, bound to the new item id. The block task used to
     * declare item_image in get_fileareas() as well, which restores the same file a second time
     * under the OLD item id (that area keys files by item id, so the generic step cannot remap
     * it) — an orphan copy that, across sites, can even land on an unrelated new item.
     */
    public function test_item_image_is_restored_once_under_the_new_item_id(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $admin = get_admin();

        $course = $this->getDataGenerator()->create_course();
        $instanceid = (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => \context_course::instance($course->id)->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        $blockcontext = \context_block::instance($instanceid);

        $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Sword',
            'image'           => 'sword.png',
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $blockcontext->id,
            'component' => 'block_playerhud',
            'filearea'  => 'item_image',
            'itemid'    => $itemid,
            'filepath'  => '/',
            'filename'  => 'sword.png',
        ], 'fake image bytes');

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($tempdir)
        );
        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $restoredblock = $DB->get_record('block_instances', [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($newcourse->id)->id,
        ], '*', MUST_EXIST);
        $restoreditemid = (int) $DB->get_field('block_playerhud_items', 'id', [
            'blockinstanceid' => $restoredblock->id, 'name' => 'Sword',
        ], MUST_EXIST);

        $files = get_file_storage()->get_area_files(
            \context_block::instance($restoredblock->id)->id,
            'block_playerhud',
            'item_image',
            false,
            'id',
            false
        );

        $this->assertCount(1, $files, 'The image must be restored once, not once per restore path.');
        $this->assertSame($restoreditemid, (int) reset($files)->get_itemid());
    }
}
