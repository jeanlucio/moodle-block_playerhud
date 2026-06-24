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
 * Controller for scene listing, creation, and editing.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scenes {
    /**
     * Renders the scene management list for a chapter.
     *
     * @return string HTML output.
     */
    public function view_manage_page(): string {
        global $DB, $PAGE, $OUTPUT;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $chapterid  = required_param('chapterid', PARAM_INT);
        $action     = optional_param('action', '', PARAM_ALPHANUMEXT);
        $nodeid     = optional_param('nodeid', 0, PARAM_INT);

        $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
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

        if ($action === 'delete_scene' && $nodeid && confirm_sesskey()) {
            $node = $DB->get_record(
                'block_playerhud_story_nodes',
                ['id' => $nodeid, 'chapterid' => $chapterid]
            );
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
                'id_label'              => get_string('scene_number', 'block_playerhud', (int) $scene->id),
                'is_start'              => (bool) $scene->is_start,
                'str_start_badge'       => get_string('scene_start_badge', 'block_playerhud'),
                'snippet'               => s(substr(strip_tags($scene->content), 0, 100)),
                'choice_count'          => $choicecount,
                'str_choices'           => get_string('choices_hdr', 'block_playerhud'),
                'url_edit'              => $editurl->out(false),
                'str_edit'              => get_string('edit'),
                'url_delete'            => $deleteurl->out(false),
                'str_delete'            => get_string('delete'),
                'confirm_msg'           => s(get_string('scene_delete_confirm', 'block_playerhud')),
            ];
        }

        $templatedata = [
            'back_url'                 => $chaptersurl->out(false),
            'str_back'                 => get_string('back_to_chapters', 'block_playerhud'),
            'str_scene_editor'         => get_string('scene_editor', 'block_playerhud'),
            'chapter_title'            => format_string($chapter->title),
            'new_scene_url'            => $newsceneurl->out(false),
            'str_new'                  => get_string('scene_new', 'block_playerhud'),
            'has_scenes'               => !empty($scenesdata),
            'scenes'                   => $scenesdata,
            'str_empty'                => get_string('scenes_empty', 'block_playerhud'),
            'str_confirm_delete_title' => get_string('confirm_delete', 'block_playerhud'),
            'str_cancel'               => get_string('cancel', 'block_playerhud'),
            'str_close'                => get_string('close', 'block_playerhud'),
            'str_delete'               => get_string('delete'),
        ];

        $PAGE->requires->js_call_amd('block_playerhud/manage_story', 'initSceneDelete');

        $output  = $OUTPUT->header();
        $output .= $OUTPUT->render_from_template('block_playerhud/manage_scenes', $templatedata);
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Handles the create/edit form for a story scene (node) with its choices.
     *
     * @return string HTML output.
     */
    public function handle_edit_form(): string {
        global $DB, $PAGE, $OUTPUT;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $chapterid  = required_param('chapterid', PARAM_INT);
        $nodeid     = optional_param('nodeid', 0, PARAM_INT);

        $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
        $DB->get_record(
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

        $pageurl = new moodle_url('/blocks/playerhud/edit_scene.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
            'chapterid'  => $chapterid,
        ]);
        if ($nodeid) {
            $pageurl->param('nodeid', $nodeid);
        }

        $PAGE->set_url($pageurl);
        $PAGE->set_title(get_string('scene_editor', 'block_playerhud'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('incourse');

        $existingchoices = [];
        if ($nodeid) {
            // Only load choices if the node actually belongs to the validated chapter.
            $nodebelongs = $DB->record_exists(
                'block_playerhud_story_nodes',
                ['id' => $nodeid, 'chapterid' => $chapterid]
            );
            if ($nodebelongs) {
                $existingchoices = array_values(
                    $DB->get_records('block_playerhud_choices', ['nodeid' => $nodeid], 'id ASC')
                );
            }
        }

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
            'chapterid'        => $chapterid,
            'instanceid'       => $instanceid,
            'nodeid'           => $nodeid,
            'db_choices_count' => count($existingchoices) + 1,
            'current_data'     => $currentdata,
        ]);

        if ($nodeid && !$mform->is_submitted()) {
            $node = $DB->get_record(
                'block_playerhud_story_nodes',
                ['id' => $nodeid, 'chapterid' => $chapterid],
                '*',
                MUST_EXIST
            );
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
            $record            = new \stdClass();
            $record->chapterid = $chapterid;
            $record->content   = $data->content['text'];
            $record->is_start  = (int) $data->is_start;

            if ($data->nodeid) {
                // Verify the node belongs to the validated chapter before updating.
                $DB->get_record(
                    'block_playerhud_story_nodes',
                    ['id' => $data->nodeid, 'chapterid' => $chapterid],
                    'id',
                    MUST_EXIST
                );
                $record->id    = $data->nodeid;
                $DB->update_record('block_playerhud_story_nodes', $record);
                $currentnodeid = $data->nodeid;
                $DB->delete_records('block_playerhud_choices', ['nodeid' => $currentnodeid]);
            } else {
                $currentnodeid = $DB->insert_record('block_playerhud_story_nodes', $record);
            }

            $repeatscount = optional_param('repeats', 0, PARAM_INT);

            $submittedchoices = [];
            for ($i = 0; $i < $repeatscount; $i++) {
                $submittedchoices[] = [
                    'text'          => optional_param("choice_text_$i", '', PARAM_TEXT),
                    'next_nodeid'   => optional_param("choice_next_$i", 0, PARAM_INT),
                    'req_class_id'  => optional_param("choice_req_class_$i", 0, PARAM_INT),
                    'req_karma_min' => optional_param("choice_req_karma_$i", 0, PARAM_INT),
                    'karma_delta'   => optional_param("choice_karma_$i", 0, PARAM_INT),
                    'set_class_id'  => optional_param("choice_set_class_$i", 0, PARAM_INT),
                    'cost_itemid'   => optional_param("choice_cost_$i", 0, PARAM_INT),
                    'cost_item_qty' => optional_param("choice_cost_qty_$i", 1, PARAM_INT),
                ];
            }

            $this->save_choices($currentnodeid, $chapterid, $instanceid, $submittedchoices);

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

        $output  = $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('scene_editor', 'block_playerhud'));
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Persists the submitted choices for a story node.
     *
     * Each target id (next node, required/granted class, cost item) is validated
     * against the ids belonging to the chapter/instance, so an unknown id falls
     * back to zero. A next-node value of -1 creates a fresh follow-up node and -2
     * reuses the previously created one (or a fallback).
     *
     * @param int $nodeid The node the choices belong to.
     * @param int $chapterid The owning chapter, used to scope and create nodes.
     * @param int $instanceid The block instance, used to scope classes and items.
     * @param array $submittedchoices List of choice rows keyed by field name.
     * @return void
     */
    public function save_choices(int $nodeid, int $chapterid, int $instanceid, array $submittedchoices): void {
        global $DB;

        $validnodeids = array_map('intval', $DB->get_fieldset_select(
            'block_playerhud_story_nodes',
            'id',
            'chapterid = ?',
            [$chapterid]
        ));
        $validclassids = array_map('intval', $DB->get_fieldset_select(
            'block_playerhud_classes',
            'id',
            'blockinstanceid = ?',
            [$instanceid]
        ));
        $validitemids = array_map('intval', $DB->get_fieldset_select(
            'block_playerhud_items',
            'id',
            'blockinstanceid = ?',
            [$instanceid]
        ));

        $previousdestid = 0;

        foreach ($submittedchoices as $submitted) {
            $textval = $submitted['text'] ?? '';
            if (empty($textval)) {
                continue;
            }

            $ch         = new \stdClass();
            $ch->nodeid = $nodeid;
            $ch->text   = $textval;

            $rawnextid = (int) ($submitted['next_nodeid'] ?? 0);
            if ($rawnextid === -1) {
                $content         = get_string('scene_auto_from', 'block_playerhud', $textval);
                $ch->next_nodeid = $this->create_followup_node($chapterid, $content);
                $previousdestid  = $ch->next_nodeid;
            } else if ($rawnextid === -2) {
                if ($previousdestid > 0) {
                    $ch->next_nodeid = $previousdestid;
                } else {
                    $content         = get_string('scene_auto_fallback', 'block_playerhud');
                    $ch->next_nodeid = $this->create_followup_node($chapterid, $content);
                    $previousdestid  = $ch->next_nodeid;
                }
            } else {
                $ch->next_nodeid = in_array($rawnextid, $validnodeids, true) ? $rawnextid : 0;
                $previousdestid  = $ch->next_nodeid;
            }

            $reqclassid        = (int) ($submitted['req_class_id'] ?? 0);
            $setclassid        = (int) ($submitted['set_class_id'] ?? 0);
            $costitemid        = (int) ($submitted['cost_itemid'] ?? 0);
            $ch->req_class_id  = in_array($reqclassid, $validclassids, true) ? $reqclassid : 0;
            $ch->req_karma_min = (int) ($submitted['req_karma_min'] ?? 0);
            $ch->karma_delta   = (int) ($submitted['karma_delta'] ?? 0);
            $ch->set_class_id  = in_array($setclassid, $validclassids, true) ? $setclassid : 0;
            $ch->cost_itemid   = in_array($costitemid, $validitemids, true) ? $costitemid : 0;
            $ch->cost_item_qty = max(1, (int) ($submitted['cost_item_qty'] ?? 1));

            $DB->insert_record('block_playerhud_choices', $ch);
        }
    }

    /**
     * Creates an empty follow-up story node in the chapter.
     *
     * @param int $chapterid The owning chapter.
     * @param string $content The node content.
     * @return int The new node ID.
     */
    private function create_followup_node(int $chapterid, string $content): int {
        global $DB;

        $node            = new \stdClass();
        $node->chapterid = $chapterid;
        $node->content   = $content;
        $node->is_start  = 0;

        return (int) $DB->insert_record('block_playerhud_story_nodes', $node);
    }
}
