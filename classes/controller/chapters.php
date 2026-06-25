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

namespace block_playerhud\controller;

use context_block;
use moodle_url;

/**
 * Controller for chapter creation and editing.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chapters {
    /**
     * Handles the create/edit form for a story chapter.
     *
     * @return string HTML output.
     */
    public function handle_edit_form(): string {
        global $DB, $PAGE, $OUTPUT;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $chapterid  = optional_param('chapterid', 0, PARAM_INT);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

        require_login($course);
        $context = context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);

        $returnurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'chapters',
        ]);

        $pageurl = new moodle_url('/blocks/playerhud/edit_chapter.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
        ]);
        if ($chapterid) {
            $pageurl->param('chapterid', $chapterid);
        }

        $PAGE->set_url($pageurl);
        $PAGE->set_title(get_string('chapter_edit', 'block_playerhud'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('incourse');

        $mform = new \block_playerhud\form\edit_chapter_form(null, ['chapterid' => $chapterid]);

        if ($chapterid && !$mform->is_submitted()) {
            $chap = $DB->get_record(
                'block_playerhud_chapters',
                ['id' => $chapterid, 'blockinstanceid' => $instanceid],
                '*',
                MUST_EXIST
            );
            $formdata               = (array) $chap;
            $formdata['courseid']   = $courseid;
            $formdata['instanceid'] = $instanceid;
            $formdata['chapterid']  = $chapterid;
            $mform->set_data($formdata);
        } else if (!$mform->is_submitted()) {
            $mform->set_data(['courseid' => $courseid, 'instanceid' => $instanceid]);
        }

        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            $this->save_chapter($data, $instanceid);

            redirect(
                $returnurl,
                get_string('chapter_saved', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $output  = $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('chapter_edit', 'block_playerhud'));
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Persists a chapter from submitted form data.
     *
     * On update the chapter must belong to the given block instance, preventing
     * edits to another instance's chapter.
     *
     * @param \stdClass $data Submitted data (title, intro_text, unlock_date,
     *                        required_level, sortorder and optional chapterid).
     * @param int $instanceid The owning block instance ID.
     * @return int The created or updated chapter ID.
     */
    public function save_chapter(\stdClass $data, int $instanceid): int {
        global $DB;

        $record = new \stdClass();
        $record->blockinstanceid = $instanceid;
        $record->title           = $data->title;
        $record->intro_text      = $data->intro_text ?? '';
        $record->unlock_date     = $data->unlock_date ?? 0;
        $record->required_level  = $data->required_level ?? 0;
        $record->sortorder       = $data->sortorder ?? 1;

        if (!empty($data->chapterid)) {
            $DB->get_record(
                'block_playerhud_chapters',
                ['id' => $data->chapterid, 'blockinstanceid' => $instanceid],
                'id',
                MUST_EXIST
            );
            $record->id = $data->chapterid;
            $DB->update_record('block_playerhud_chapters', $record);
            return (int) $data->chapterid;
        }

        return (int) $DB->insert_record('block_playerhud_chapters', $record);
    }

    /**
     * Deletes a chapter together with its scenes and their choices.
     *
     * The chapter must belong to the given block instance.
     *
     * @param int $chapterid The chapter to delete.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    public function delete_chapter(int $chapterid, int $instanceid): void {
        global $DB;

        $chapter = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        $sceneids = $DB->get_fieldset_select(
            'block_playerhud_story_nodes',
            'id',
            'chapterid = ?',
            [$chapter->id]
        );
        if ($sceneids) {
            [$insql, $inparams] = $DB->get_in_or_equal($sceneids);
            $DB->delete_records_select('block_playerhud_choices', "nodeid $insql", $inparams);
        }
        $DB->delete_records('block_playerhud_story_nodes', ['chapterid' => $chapter->id]);
        $DB->delete_records('block_playerhud_chapters', ['id' => $chapter->id, 'blockinstanceid' => $instanceid]);
    }

    /**
     * Swaps a chapter's sort order with its neighbour in the given direction.
     *
     * The chapter must belong to the given block instance. Moving past the first
     * or last chapter is a no-op.
     *
     * @param int $chapterid The chapter to move.
     * @param int $instanceid The owning block instance ID.
     * @param string $direction Either 'up' or 'down'.
     * @return void
     */
    public function move_chapter(int $chapterid, int $instanceid, string $direction): void {
        global $DB;

        $chapter = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        if ($direction === 'up') {
            $sql = "SELECT * FROM {block_playerhud_chapters}
                     WHERE blockinstanceid = ? AND sortorder < ?
                  ORDER BY sortorder DESC";
        } else {
            $sql = "SELECT * FROM {block_playerhud_chapters}
                     WHERE blockinstanceid = ? AND sortorder > ?
                  ORDER BY sortorder ASC";
        }

        $siblings = $DB->get_records_sql($sql, [$instanceid, $chapter->sortorder], 0, 1);
        $sibling = reset($siblings);
        if (!$sibling) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $temporder          = $chapter->sortorder;
            $chapter->sortorder = $sibling->sortorder;
            $sibling->sortorder = $temporder;
            $DB->update_record('block_playerhud_chapters', $chapter);
            $DB->update_record('block_playerhud_chapters', $sibling);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }
}
