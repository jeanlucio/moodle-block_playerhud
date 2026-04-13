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
 * List and manage scenes (nodes) for a story chapter.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$chapterid  = required_param('chapterid', PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);
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

$baseurl = new moodle_url('/blocks/playerhud/manage_scenes.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'chapterid'  => $chapterid,
]);

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($chapter->title));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// Action: Delete Scene.
if ($action === 'delete_scene' && $nodeid && confirm_sesskey()) {
    $node = $DB->get_record('block_playerhud_story_nodes', ['id' => $nodeid, 'chapterid' => $chapterid]);
    if ($node) {
        $DB->delete_records('block_playerhud_choices', ['nodeid' => $nodeid]);
        $DB->delete_records('block_playerhud_story_nodes', ['id' => $nodeid]);
    }
    redirect(
        $baseurl,
        get_string('scene_deleted', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

$chaptersurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id'         => $courseid,
    'instanceid' => $instanceid,
    'tab'        => 'chapters',
]);

$newsceneurl = new moodle_url('/blocks/playerhud/edit_scene.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'chapterid'  => $chapterid,
]);

$scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapterid], 'id ASC');

// Bulk-fetch choice counts per node.
$choicecounts = [];
if ($scenes) {
    $nodeids = array_keys($scenes);
    [$insql, $inparams] = $DB->get_in_or_equal($nodeids);
    $sql = "SELECT nodeid, COUNT(id) AS cnt
              FROM {block_playerhud_choices}
             WHERE nodeid $insql
          GROUP BY nodeid";
    foreach ($DB->get_records_sql($sql, $inparams) as $row) {
        $choicecounts[(int) $row->nodeid] = (int) $row->cnt;
    }
}

$scenesdata = [];
foreach ($scenes as $scene) {
    $choicecount = $choicecounts[$scene->id] ?? 0;
    $editurl     = new moodle_url('/blocks/playerhud/edit_scene.php', [
        'courseid'   => $courseid,
        'instanceid' => $instanceid,
        'chapterid'  => $chapterid,
        'nodeid'     => $scene->id,
    ]);
    $deleteurl   = new moodle_url($baseurl, [
        'action'  => 'delete_scene',
        'nodeid'  => $scene->id,
        'sesskey' => sesskey(),
    ]);

    $scenesdata[] = [
        'id_label'    => get_string('scene_number', 'block_playerhud', (int) $scene->id),
        'is_start'    => (bool) $scene->is_start,
        'str_start_badge' => get_string('scene_start_badge', 'block_playerhud'),
        'snippet'     => s(substr(strip_tags($scene->content), 0, 100)),
        'choice_count' => $choicecount,
        'str_choices' => get_string('choices_hdr', 'block_playerhud'),
        'url_edit'    => $editurl->out(false),
        'str_edit'    => get_string('edit'),
        'url_delete'  => $deleteurl->out(false),
        'str_delete'  => get_string('delete'),
        'confirm_msg' => s(get_string('scene_delete_confirm', 'block_playerhud')),
    ];
}

$templatedata = [
    'back_url'               => $chaptersurl->out(false),
    'str_back'               => get_string('back_to_chapters', 'block_playerhud'),
    'str_scene_editor'       => get_string('scene_editor', 'block_playerhud'),
    'chapter_title'          => format_string($chapter->title),
    'new_scene_url'          => $newsceneurl->out(false),
    'str_new'                => get_string('scene_new', 'block_playerhud'),
    'has_scenes'             => !empty($scenesdata),
    'scenes'                 => $scenesdata,
    'str_empty'              => get_string('scenes_empty', 'block_playerhud'),
    'str_confirm_delete_title' => get_string('confirm_delete', 'block_playerhud'),
    'str_cancel'             => get_string('cancel', 'block_playerhud'),
    'str_close'              => get_string('close', 'block_playerhud'),
    'str_delete'             => get_string('delete'),
];

$PAGE->requires->js_call_amd('block_playerhud/manage_story', 'initSceneDelete');

echo $OUTPUT->render_from_template('block_playerhud/manage_scenes', $templatedata);

echo $OUTPUT->footer();
