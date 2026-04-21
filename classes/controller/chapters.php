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
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
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
            $record                  = new \stdClass();
            $record->blockinstanceid = $instanceid;
            $record->title           = $data->title;
            $record->intro_text      = $data->intro_text ?? '';
            $record->unlock_date     = $data->unlock_date ?? 0;
            $record->required_level  = $data->required_level ?? 0;
            $record->sortorder       = $data->sortorder ?? 1;

            if ($data->chapterid) {
                $record->id = $data->chapterid;
                $DB->update_record('block_playerhud_chapters', $record);
            } else {
                $DB->insert_record('block_playerhud_chapters', $record);
            }

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
}
