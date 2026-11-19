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
 * Tests for how scene images travel through backup and restore.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * Scene images must reach the restored course under the restored scene's own id.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class backup_restore_scene_images_test extends advanced_testcase {
    /**
     * An image stored in a scene is backed up with the course and restored once, bound to the new
     * scene id, so the restored scene text still finds it.
     */
    public function test_scene_image_is_restored_under_the_new_scene_id(): void {
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
        $chapterid = (int) $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => 'The Gate',
            'sortorder'       => 1,
        ]);
        $nodeid = (int) $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid,
            'content'   => '<p><img src="@@PLUGINFILE@@/gate.png" alt="Gate"></p>',
            'is_start'  => 1,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => \context_block::instance($instanceid)->id,
            'component' => 'block_playerhud',
            'filearea'  => 'scene_content',
            'itemid'    => $nodeid,
            'filepath'  => '/',
            'filename'  => 'gate.png',
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
        $restoredchapterid = (int) $DB->get_field('block_playerhud_chapters', 'id', [
            'blockinstanceid' => $restoredblock->id,
        ], MUST_EXIST);
        $restorednode = $DB->get_record('block_playerhud_story_nodes', ['chapterid' => $restoredchapterid], '*', MUST_EXIST);

        $files = get_file_storage()->get_area_files(
            \context_block::instance($restoredblock->id)->id,
            'block_playerhud',
            'scene_content',
            false,
            'id',
            false
        );

        $this->assertCount(1, $files, 'The image must be restored once.');
        $file = reset($files);
        $this->assertSame((int) $restorednode->id, (int) $file->get_itemid());
        $this->assertSame('gate.png', $file->get_filename());
        $this->assertStringContainsString('@@PLUGINFILE@@/gate.png', $restorednode->content);
    }
}
