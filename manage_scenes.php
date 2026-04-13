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
 * List and manage scenes (nodes) for a story chapter.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

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

$backlink = \html_writer::link(
    $chaptersurl,
    '&larr; ' . get_string('back_to_chapters', 'block_playerhud'),
    ['class' => 'btn btn-outline-secondary btn-sm mb-3']
);
echo \html_writer::tag('div', $backlink, []);

echo $OUTPUT->heading(
    get_string('scene_editor', 'block_playerhud') . ': ' . format_string($chapter->title),
    3
);

$scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapterid], 'id ASC');

// Bulk-fetch choice counts per node.
$choicecounts = [];
if ($scenes) {
    $nodeids = array_keys($scenes);
    [$insql, $inparams] = $DB->get_in_or_equal($nodeids);
    $sql = "SELECT nodeid, COUNT(id) AS cnt FROM {block_playerhud_choices} WHERE nodeid $insql GROUP BY nodeid";
    foreach ($DB->get_records_sql($sql, $inparams) as $row) {
        $choicecounts[(int) $row->nodeid] = (int) $row->cnt;
    }
}

$newsceneurl = new moodle_url('/blocks/playerhud/edit_scene.php', [
    'courseid'   => $courseid,
    'instanceid' => $instanceid,
    'chapterid'  => $chapterid,
]);

echo \html_writer::link(
    $newsceneurl,
    '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' . get_string('scene_new', 'block_playerhud'),
    ['class' => 'btn btn-primary mb-3']
);

if ($scenes) {
    echo '<div class="list-group mt-3">';
    foreach ($scenes as $scene) {
        $choicecount = $choicecounts[$scene->id] ?? 0;
        $snippet     = substr(strip_tags($scene->content), 0, 100);
        $isstartbadge = $scene->is_start
            ? '<span class="badge bg-success ms-2">' . get_string('scene_start_badge', 'block_playerhud') . '</span>'
            : '';

        $editurl   = new moodle_url('/blocks/playerhud/edit_scene.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
            'chapterid'  => $chapterid,
            'nodeid'     => $scene->id,
        ]);
        $deleteurl = new moodle_url($baseurl, [
            'action'  => 'delete_scene',
            'nodeid'  => $scene->id,
            'sesskey' => sesskey(),
        ]);

        $safemsg = s(get_string('scene_delete_confirm', 'block_playerhud'));

        echo '
        <div class="list-group-item p-3 mb-2 border rounded shadow-sm">
            <div class="d-flex w-100 justify-content-between align-items-start">
                <div>
                    <strong>Scene #' . (int) $scene->id . '</strong>' . $isstartbadge . '
                    <p class="mb-1 text-muted small mt-1">' . s($snippet) . '</p>
                    <small class="text-muted">' . $choicecount . ' '
                        . get_string('choices_hdr', 'block_playerhud') . '</small>
                </div>
                <div class="d-flex gap-2 ms-3">
                    <a href="' . $editurl->out() . '" class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-pencil" aria-hidden="true"></i> ' . get_string('edit') . '
                    </a>
                    <button class="btn btn-sm btn-outline-danger"
                            data-action="delete-scene"
                            data-confirm-msg="' . $safemsg . '"
                            data-delete-url="' . $deleteurl->out() . '"
                            data-bs-toggle="modal"
                            data-bs-target="#ph-confirm-delete-scene">
                        <i class="fa fa-trash" aria-hidden="true"></i> ' . get_string('delete') . '
                    </button>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
} else {
    echo $OUTPUT->notification(get_string('scenes_empty', 'block_playerhud'), 'info');
}

// Delete confirmation modal.
echo '
<div class="modal fade" id="ph-confirm-delete-scene" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">' . get_string('confirm_delete', 'block_playerhud') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="' . get_string('close', 'block_playerhud') . '"></button>
            </div>
            <div class="modal-body">
                <p id="ph-delete-scene-msg"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'
                    . get_string('cancel', 'block_playerhud') . '</button>
                <a id="ph-delete-scene-url" href="#" class="btn btn-danger">'
                    . get_string('delete') . '</a>
            </div>
        </div>
    </div>
</div>';

$PAGE->requires->js_call_amd('block_playerhud/manage_story', 'initSceneDelete');

echo $OUTPUT->footer();
