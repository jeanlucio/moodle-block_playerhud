<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Create or edit an RPG class for PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$classid    = optional_param('classid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

$baseurl = new moodle_url('/blocks/playerhud/edit_class.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
]);
if ($classid) {
    $baseurl->param('classid', $classid);
}

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');

$returnurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id'         => $courseid,
    'instanceid' => $instanceid,
    'tab'        => 'classes',
]);

// Load existing record if editing.
$record = null;
if ($classid) {
    $record = $DB->get_record(
        'block_playerhud_classes',
        ['id' => $classid, 'blockinstanceid' => $instanceid],
        '*',
        MUST_EXIST
    );
}

$mform = new \block_playerhud\form\edit_class_form($baseurl, [
    'context' => $context,
    'classid' => $classid,
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    if ($classid && $record) {
        $record->name        = $data->name;
        $record->description = $data->description;
        $record->base_hp     = (int)$data->base_hp;
        $record->timemodified = $now;
        $DB->update_record('block_playerhud_classes', $record);
    } else {
        $record = new stdClass();
        $record->blockinstanceid = $instanceid;
        $record->name            = $data->name;
        $record->description     = $data->description;
        $record->base_hp         = (int)$data->base_hp;
        $record->timecreated     = $now;
        $record->timemodified    = $now;
        $classid = $DB->insert_record('block_playerhud_classes', $record);
    }

    // Save portrait files for each of the 5 evolution tiers.
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

    redirect($returnurl, get_string('class_saved', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
}

// Populate form with existing data when editing.
if ($record) {
    $formdata = clone $record;

    // Prepare a draft file area for each tier image.
    for ($tier = 1; $tier <= 5; $tier++) {
        $field = 'image_tier' . $tier;
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

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
