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
 * Controller for RPG class creation and editing.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classes {
    /**
     * Handles the create/edit form for an RPG class.
     *
     * @return string HTML output.
     */
    public function handle_edit_form(): string {
        global $DB, $PAGE, $OUTPUT;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $classid    = optional_param('classid', 0, PARAM_INT);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

        require_login($course);
        $context = context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);

        $pageurl = new moodle_url('/blocks/playerhud/edit_class.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
        ]);
        if ($classid) {
            $pageurl->param('classid', $classid);
        }

        $returnurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'classes',
        ]);

        $PAGE->set_url($pageurl);
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('pluginname', 'block_playerhud'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->set_pagelayout('standard');

        $record = null;
        if ($classid) {
            $record = $DB->get_record(
                'block_playerhud_classes',
                ['id' => $classid, 'blockinstanceid' => $instanceid],
                '*',
                MUST_EXIST
            );
        }

        $mform = new \block_playerhud\form\edit_class_form($pageurl, [
            'context' => $context,
            'classid' => $classid,
        ]);

        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            $classid = $this->save_class($data, $context, $record);
            redirect(
                $returnurl,
                get_string('class_saved', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($record) {
            $formdata = clone $record;

            for ($tier = 1; $tier <= 5; $tier++) {
                $field   = 'image_tier' . $tier;
                $draftid = file_get_submitted_draft_itemid($field);
                file_prepare_draft_area(
                    $draftid,
                    $context->id,
                    'block_playerhud',
                    'class_image_' . $tier,
                    $record->id,
                    ['maxfiles' => 1]
                );
                $formdata->$field = $draftid;
            }

            $formdata->courseid   = $courseid;
            $formdata->instanceid = $instanceid;
            $formdata->classid    = $classid;

            $mform->set_data($formdata);
        } else {
            $mform->set_data([
                'courseid'   => $courseid,
                'instanceid' => $instanceid,
                'classid'    => 0,
            ]);
        }

        $output  = $OUTPUT->header();
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Persists an RPG class record and its portrait files.
     *
     * @param \stdClass     $data    Form data.
     * @param context_block $context Block context (needed for file API).
     * @param \stdClass|null $record Existing record when editing, null when creating.
     * @return int The class ID (new or existing).
     */
    private function save_class(\stdClass $data, context_block $context, ?\stdClass $record): int {
        global $DB;

        $now = time();

        if ($data->classid && $record) {
            $record->name         = $data->name;
            $record->description  = $data->description;
            $record->base_hp      = (int) $data->base_hp;
            $record->timemodified = $now;
            $DB->update_record('block_playerhud_classes', $record);
            $classid = (int) $record->id;
        } else {
            $newrecord                  = new \stdClass();
            $newrecord->blockinstanceid = $data->instanceid;
            $newrecord->name            = $data->name;
            $newrecord->description     = $data->description;
            $newrecord->base_hp         = (int) $data->base_hp;
            $newrecord->timecreated     = $now;
            $newrecord->timemodified    = $now;
            $classid = $DB->insert_record('block_playerhud_classes', $newrecord);
        }

        for ($tier = 1; $tier <= 5; $tier++) {
            $field = 'image_tier' . $tier;
            file_save_draft_area_files(
                $data->$field,
                $context->id,
                'block_playerhud',
                'class_image_' . $tier,
                $classid,
                ['maxfiles' => 1]
            );
        }

        return $classid;
    }
}
