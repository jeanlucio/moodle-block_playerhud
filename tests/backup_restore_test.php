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

/**
 * Smoke test: backup a course with PlayerHUD RPG data and restore it,
 * verifying that classes, chapters and story nodes are preserved.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_playerhud_stepslib
 * @covers     \restore_playerhud_stepslib
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * Verify that the backup stepslib includes every RPG table as a source.
     *
     * This is a static code check — it does not require a database or file
     * system. It guards against accidental removal of RPG elements from the
     * backup step definition.
     *
     * @coversNothing
     */
    public function test_backup_stepslib_covers_rpg_tables(): void {
        global $CFG;

        $stepslibpath = $CFG->dirroot . '/blocks/playerhud/backup/moodle2/backup_playerhud_stepslib.php';
        $this->assertFileExists($stepslibpath);

        $source = file_get_contents($stepslibpath);

        $expectedtables = [
            'block_playerhud_classes',
            'block_playerhud_chapters',
            'block_playerhud_story_nodes',
            'block_playerhud_choices',
            'block_playerhud_rpg_progress',
        ];

        foreach ($expectedtables as $table) {
            $this->assertStringContainsString(
                $table,
                $source,
                "backup stepslib must reference table '{$table}'"
            );
        }
    }

    /**
     * Verify that the restore stepslib includes every RPG table as a restore step.
     *
     * @coversNothing
     */
    public function test_restore_stepslib_covers_rpg_tables(): void {
        global $CFG;

        $stepslibpath = $CFG->dirroot . '/blocks/playerhud/backup/moodle2/restore_playerhud_stepslib.php';
        $this->assertFileExists($stepslibpath);

        $source = file_get_contents($stepslibpath);

        $expectedtables = [
            'block_playerhud_classes',
            'block_playerhud_chapters',
            'block_playerhud_story_nodes',
            'block_playerhud_choices',
            'block_playerhud_rpg_progress',
        ];

        foreach ($expectedtables as $table) {
            $this->assertStringContainsString(
                $table,
                $source,
                "restore stepslib must reference table '{$table}'"
            );
        }
    }

    /**
     * Full backup-then-restore smoke test.
     *
     * Creates a course with a PlayerHUD block and RPG data (class, chapter,
     * node), backs up the course, restores into a new course, then asserts
     * that each RPG record is present under the restored block instance.
     */
    public function test_backup_and_restore_preserves_rpg_data(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Load backup/restore libraries.
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();

        // 1. Create source course and block instance.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $bi = (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ];
        $instanceid = $DB->insert_record('block_instances', $bi);

        // 2. Seed RPG data.
        $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => 'Wizard',
            'description'     => '',
            'base_hp'         => 80,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $chapterid = $DB->insert_record('block_playerhud_chapters', (object) [
            'blockinstanceid' => $instanceid,
            'title'           => 'The Beginning',
            'intro_text'      => '',
            'unlock_date'     => 0,
            'required_level'  => 0,
            'sortorder'       => 1,
        ]);

        $DB->insert_record('block_playerhud_story_nodes', (object) [
            'chapterid' => $chapterid,
            'content'   => 'It was a dark and stormy night.',
            'is_start'  => 1,
        ]);

        // 3. Backup.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->execute_plan();
        $results = $bc->get_results();
        $backupfile = $results['backup_destination'];
        $bc->destroy();

        // 4. Extract backup to a temp directory for restore.
        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($fp, make_backup_temp_directory($tempdir));

        // 5. Restore into the new course.
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

        // 6. Locate the restored block instance.
        $newcoursecontext = \context_course::instance($newcourse->id);
        $restoredblock = $DB->get_record(
            'block_instances',
            ['blockname' => 'playerhud', 'parentcontextid' => $newcoursecontext->id]
        );
        $this->assertNotFalse($restoredblock, 'Restored block instance must exist.');

        // 7. Assert RPG content was carried over.
        $this->assertTrue(
            $DB->record_exists(
                'block_playerhud_classes',
                ['blockinstanceid' => $restoredblock->id, 'name' => 'Wizard']
            ),
            'RPG class "Wizard" must exist in the restored block.'
        );

        $restoredchapter = $DB->get_record(
            'block_playerhud_chapters',
            ['blockinstanceid' => $restoredblock->id, 'title' => 'The Beginning']
        );
        $this->assertNotFalse($restoredchapter, 'Chapter "The Beginning" must be restored.');

        $this->assertTrue(
            $DB->record_exists(
                'block_playerhud_story_nodes',
                ['chapterid' => $restoredchapter->id, 'is_start' => 1]
            ),
            'Start story node must be restored under the correct chapter.'
        );
    }
}
