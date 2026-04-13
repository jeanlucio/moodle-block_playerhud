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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Create or edit a story scene (node) with its choices.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$chapterid  = required_param('chapterid', PARAM_INT);
$nodeid     = optional_param('nodeid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

$chapter = $DB->get_record(
    'block_playerhud_chapters',
    ['id' => $chapterid, 'blockinstanceid' => $instanceid],
    '*',
    MUST_EXIST
);

require_login($course);

$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

$scenesurl = new moodle_url('/blocks/playerhud/manage_scenes.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'chapterid'  => $chapterid,
]);

$PAGE->set_url('/blocks/playerhud/edit_scene.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'chapterid'  => $chapterid,
]);
$PAGE->set_title(get_string('scene_editor', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// Load existing choices for this node.
$existingchoices = [];
if ($nodeid) {
    $existingchoices = array_values(
        $DB->get_records('block_playerhud_choices', ['nodeid' => $nodeid], 'id ASC')
    );
}

// Build current_data for the form.
$currentdata = [];
foreach ($existingchoices as $idx => $ch) {
    $currentdata["choice_text_$idx"]      = $ch->text;
    $currentdata["choice_next_$idx"]      = $ch->next_nodeid;
    $currentdata["choice_req_class_$idx"] = $ch->req_class_id ?? 0;
    $currentdata["choice_req_karma_$idx"] = $ch->req_karma_min ?? 0;
    $currentdata["choice_karma_$idx"]     = $ch->karma_delta;
    $currentdata["choice_set_class_$idx"] = $ch->set_class_id;
    $currentdata["choice_cost_$idx"]      = $ch->cost_itemid;
    $currentdata["choice_cost_qty_$idx"]  = max(1, (int) $ch->cost_item_qty);
}

// If "Add choice" was clicked, absorb current posted values first.
if (optional_param('add_choice_btn', false, PARAM_BOOL)) {
    $postedrepeats = optional_param('repeats', 3, PARAM_INT);
    for ($k = 0; $k < $postedrepeats + 1; $k++) {
        $currentdata["choice_text_$k"]      = optional_param("choice_text_$k", '', PARAM_TEXT);
        $currentdata["choice_next_$k"]      = optional_param("choice_next_$k", 0, PARAM_INT);
        $currentdata["choice_req_class_$k"] = optional_param("choice_req_class_$k", 0, PARAM_INT);
        $currentdata["choice_req_karma_$k"] = optional_param("choice_req_karma_$k", 0, PARAM_INT);
        $currentdata["choice_karma_$k"]     = optional_param("choice_karma_$k", 0, PARAM_INT);
        $currentdata["choice_set_class_$k"] = optional_param("choice_set_class_$k", 0, PARAM_INT);
        $currentdata["choice_cost_$k"]      = optional_param("choice_cost_$k", 0, PARAM_INT);
        $currentdata["choice_cost_qty_$k"]  = optional_param("choice_cost_qty_$k", 1, PARAM_INT);
    }
}

$mform = new \block_playerhud\form\edit_scene_form(null, [
    'chapterid'       => $chapterid,
    'instanceid'      => $instanceid,
    'nodeid'          => $nodeid,
    'db_choices_count' => count($existingchoices) + 1,
    'current_data'    => $currentdata,
]);

if ($nodeid && !$mform->is_submitted()) {
    $node = $DB->get_record('block_playerhud_story_nodes', ['id' => $nodeid], '*', MUST_EXIST);
    $formdata = array_merge($currentdata, [
        'courseid'   => $courseid,
        'instanceid' => $instanceid,
        'chapterid'  => $chapterid,
        'nodeid'     => $nodeid,
        'content'    => ['text' => $node->content, 'format' => FORMAT_HTML],
        'is_start'   => (int) $node->is_start,
    ]);
    $mform->set_data($formdata);
} else if (!$mform->is_submitted()) {
    $mform->set_data([
        'courseid'   => $courseid,
        'instanceid' => $instanceid,
        'chapterid'  => $chapterid,
        'nodeid'     => 0,
    ]);
}

if ($mform->is_cancelled()) {
    redirect($scenesurl);
} else if ($data = $mform->get_data()) {
    $record          = new stdClass();
    $record->chapterid = $chapterid;
    $record->content   = $data->content['text'];
    $record->is_start  = (int) $data->is_start;

    if ($data->nodeid) {
        $record->id = $data->nodeid;
        $DB->update_record('block_playerhud_story_nodes', $record);
        $currentnodeid = $data->nodeid;
        $DB->delete_records('block_playerhud_choices', ['nodeid' => $currentnodeid]);
    } else {
        $currentnodeid = $DB->insert_record('block_playerhud_story_nodes', $record);
    }

    $previousdestid = 0;
    $repeatscount   = optional_param('repeats', 0, PARAM_INT);

    for ($i = 0; $i < $repeatscount; $i++) {
        $textval = optional_param("choice_text_$i", '', PARAM_TEXT);

        if (!empty($textval)) {
            $ch          = new stdClass();
            $ch->nodeid  = $currentnodeid;
            $ch->text    = $textval;

            $rawnextid = optional_param("choice_next_$i", 0, PARAM_INT);

            if ($rawnextid === -1) {
                $newnode           = new stdClass();
                $newnode->chapterid = $chapterid;
                $newnode->content   = '<p><em>(New scene from: "' . s($textval) . '")</em></p>';
                $newnode->is_start  = 0;
                $createdid          = $DB->insert_record('block_playerhud_story_nodes', $newnode);
                $ch->next_nodeid    = $createdid;
                $previousdestid     = $createdid;
            } else if ($rawnextid === -2) {
                if ($previousdestid > 0) {
                    $ch->next_nodeid = $previousdestid;
                } else {
                    $newnode           = new stdClass();
                    $newnode->chapterid = $chapterid;
                    $newnode->content   = '<p><em>(Fallback scene)</em></p>';
                    $newnode->is_start  = 0;
                    $createdid          = $DB->insert_record('block_playerhud_story_nodes', $newnode);
                    $ch->next_nodeid    = $createdid;
                    $previousdestid     = $createdid;
                }
            } else {
                $ch->next_nodeid = $rawnextid;
                $previousdestid  = $rawnextid;
            }

            $ch->req_class_id  = optional_param("choice_req_class_$i", 0, PARAM_INT);
            $ch->req_karma_min = optional_param("choice_req_karma_$i", 0, PARAM_INT);
            $ch->karma_delta   = optional_param("choice_karma_$i", 0, PARAM_INT);
            $ch->set_class_id  = optional_param("choice_set_class_$i", 0, PARAM_INT);
            $ch->cost_itemid   = optional_param("choice_cost_$i", 0, PARAM_INT);
            $qty               = optional_param("choice_cost_qty_$i", 1, PARAM_INT);
            $ch->cost_item_qty = max(1, $qty);

            $DB->insert_record('block_playerhud_choices', $ch);
        }
    }

    if (isset($data->add_choice_btn)) {
        redirect(new moodle_url('/blocks/playerhud/edit_scene.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
            'chapterid'  => $chapterid,
            'nodeid'     => $currentnodeid,
        ]));
    }

    redirect(
        $scenesurl,
        get_string('scene_saved', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('scene_editor', 'block_playerhud'));
$mform->display();
echo $OUTPUT->footer();
