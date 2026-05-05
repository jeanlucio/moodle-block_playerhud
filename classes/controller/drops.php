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

use moodle_url;
use html_writer;
use html_table;

/**
 * Controller for managing Drops (Location-based items).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drops {
    /**
     * Helper to generate sort data (HTML-free).
     * Replaces the old get_sort_link.
     *
     * @param string $colname Column name.
     * @param string $label Label text.
     * @param string $currentsort Current sort column.
     * @param string $currentdir Current direction.
     * @param moodle_url $baseurl Base URL.
     * @return array Sort data structure.
     */
    private function get_sort_data($colname, $label, $currentsort, $currentdir, $baseurl) {
        $icon = 'fa-sort text-muted opacity-25'; // Default inactive icon class.
        $nextdir = 'ASC';

        if ($currentsort == $colname) {
            if ($currentdir == 'ASC') {
                $icon = 'fa-sort-asc text-primary';
                $nextdir = 'DESC';
            } else {
                $icon = 'fa-sort-desc text-primary';
                $nextdir = 'ASC';
            }
        }

        return [
            'url' => (new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]))->out(false),
            'label' => $label,
            'icon_class' => $icon,
        ];
    }

    /**
     * Renders the main Drop management page (List view).
     *
     * @return string The HTML content.
     */
    public function view_manage_page() {
        global $DB, $PAGE, $OUTPUT, $COURSE, $CFG;

        // 1. Parameters.
        $instanceid = required_param('instanceid', PARAM_INT);
        $courseid   = required_param('id', PARAM_INT);
        $itemid     = required_param('itemid', PARAM_INT);
        $action     = optional_param('action', '', PARAM_ALPHANUMEXT);
        $dropid     = optional_param('dropid', 0, PARAM_INT);
        $sort       = optional_param('sort', 'id', PARAM_ALPHA);
        $dir        = optional_param('dir', 'DESC', PARAM_ALPHA);

        $allowedsorts = ['id', 'mapcode', 'maxusage', 'respawntime', 'timecreated'];
        if (!in_array($sort, $allowedsorts, true)) {
            $sort = 'id';
        }
        $dir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

        // 2. Security.
        require_login($courseid);
        $context = \context_block::instance($instanceid);
        $coursecontext = \context_course::instance($courseid);
        require_capability('block/playerhud:manage', $context);

        $baseurl = new moodle_url('/blocks/playerhud/manage_drops.php', [
            'instanceid' => $instanceid,
            'id' => $courseid,
            'itemid' => $itemid,
        ]);

        $PAGE->set_url($baseurl);
        $PAGE->set_context($context);
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('incourse');

        // 3. Actions (Delete).
        if ($action === 'delete' && $dropid && confirm_sesskey()) {
            $drop = $DB->get_record_sql(
                "SELECT d.id
                   FROM {block_playerhud_drops} d
                   JOIN {block_playerhud_items} i ON i.id = d.itemid
                  WHERE d.id = :dropid AND i.blockinstanceid = :instanceid",
                ['dropid' => $dropid, 'instanceid' => $instanceid]
            );
            if ($drop) {
                $DB->delete_records('block_playerhud_drops', ['id' => $drop->id]);
            }
            redirect(
                $baseurl,
                get_string('deleted', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        if ($action === 'bulk_delete' && confirm_sesskey()) {
            $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
            if (!empty($bulkids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($bulkids, SQL_PARAMS_NAMED, 'did');

                // Pre-filter: only keep IDs that truly belong to this block instance.
                $validids = $DB->get_fieldset_sql(
                    "SELECT d.id
                       FROM {block_playerhud_drops} d
                       JOIN {block_playerhud_items} i ON i.id = d.itemid
                      WHERE d.id $insql AND i.blockinstanceid = :instanceid",
                    array_merge($inparams, ['instanceid' => $instanceid])
                );

                $count = 0;
                if (!empty($validids)) {
                    [$delsql, $delparams] = $DB->get_in_or_equal($validids);
                    $DB->delete_records_select('block_playerhud_drops', "id $delsql", $delparams);
                    $count = count($validids);
                }

                redirect(
                    $baseurl,
                    get_string('deleted_bulk', 'block_playerhud', $count),
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
        }

        // 4. Data Preparation.
        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $itemid], "$sort $dir");

        $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);

        $dropsdata = [];
        $counter = 1;
        if ($drops) {
            foreach ($drops as $drop) {
                $editurl = new moodle_url('/blocks/playerhud/edit_drop.php', [
                    'instanceid' => $instanceid,
                    'courseid' => $courseid,
                    'itemid' => $itemid,
                    'dropid' => $drop->id,
                ]);
                $deleteurl = new moodle_url($baseurl, [
                    'action' => 'delete',
                    'dropid' => $drop->id,
                    'sesskey' => sesskey(),
                ]);

                // Code logic.
                $dropsdata[] = [
                    'id' => $drop->id,
                    'counter' => $counter++,
                    'name' => format_text($drop->name, FORMAT_HTML, ['context' => $coursecontext]),
                    'is_infinite' => ($drop->maxusage == 0),
                    'maxusage' => $drop->maxusage,
                    'is_immediate' => ($drop->respawntime == 0),
                    'respawntime' => $drop->respawntime,
                    'respawntime_fmt' => format_time($drop->respawntime),
                    'display_code' => $drop->code,
                    'full_default_code' => '[PLAYERHUD_DROP code=' . $drop->code . ']',
                    'confirm_msg' => get_string('drops_confirm_delete', 'block_playerhud'),
                    'url_edit' => $editurl->out(false),
                    'url_delete' => $deleteurl->out(false),

                    // Internal loop strings.
                    'str_infinite' => get_string('infinite', 'block_playerhud'),
                    'str_immediate' => get_string('drops_immediate', 'block_playerhud'),
                    'str_gen_title' => get_string('gen_customize', 'block_playerhud'), // Text: Customize.
                    'str_quick_copy' => get_string('gen_copy_short', 'block_playerhud'), // Text: Copy.
                    'str_edit' => get_string('edit'),
                    'str_delete' => get_string('delete'),
                    'str_select' => get_string('select'),
                ];
            }
        }

        $totaldrops = count($drops);
        $summarytext = get_string('drops_summary', 'block_playerhud', $totaldrops);

        // New: Sort Headers (Structured data).
        $headers = [
            'name' => $this->get_sort_data(
                'name',
                get_string('drop_name_label', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'qty' => $this->get_sort_data(
                'maxusage',
                get_string('drop_max_qty', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'time' => $this->get_sort_data(
                'respawntime',
                get_string('respawntime', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
        ];

        $templatedata = [
            'base_url' => $baseurl->out(false),
            'sesskey' => sesskey(),
            'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'str_back_course' => get_string('back_to_course', 'block_playerhud'),
            'url_library' => (new moodle_url('/blocks/playerhud/manage.php', [
                'id' => $courseid,
                'instanceid' => $instanceid,
                'tab' => 'items',
            ]))->out(false),
            'str_back_lib' => get_string('back_to_library', 'block_playerhud'),
            'url_new_drop' => (new moodle_url('/blocks/playerhud/edit_drop.php', [
                'instanceid' => $instanceid,
                'courseid' => $courseid,
                'itemid' => $itemid,
            ]))->out(false),
            'str_new_drop' => get_string('drops_btn_new', 'block_playerhud'),

            // Media Data (Page Header).
            'is_image' => $mediadata['is_image'],
            'media_url' => $mediadata['is_image'] ? $mediadata['url'] : '',
            'media_content' => $mediadata['is_image'] ? '' : strip_tags($mediadata['content']),

            'str_managing' => get_string('drops_header_managedrops', 'block_playerhud'),
            'item_name' => format_string($item->name),
            'summary_text' => $summarytext,

            // Pass headers to template.
            'headers' => $headers,

            'drops' => $dropsdata,
            'str_col_gen' => get_string('gen_title', 'block_playerhud'),
            'str_actions' => get_string('actions'),
            'str_select_all' => get_string('selectall'),
            'str_empty_drops' => get_string('drops_empty', 'block_playerhud'),
            'str_delete_selected' => get_string('delete_selected', 'block_playerhud'),
            'str_help_code' => get_string('dropcode_help', 'block_playerhud'),

            'modal_gen_html' => $OUTPUT->render_from_template('block_playerhud/modal_generator', []),
        ];

        // JS Init.
        $jsconfig = [
            'item' => [
                'name' => format_string($item->name),
                'isImage' => $mediadata['is_image'],
                'url' => $mediadata['is_image'] ? $mediadata['url'] : '',
                'content' => $mediadata['is_image'] ? '' : strip_tags($mediadata['content']),
                'xp' => "+{$item->xp} XP",
            ],
            'strings' => [
                'defaultText' => get_string('gen_link_placeholder', 'block_playerhud'),
                'takeBtn' => get_string('take', 'block_playerhud'),
                'yours' => get_string('gen_yours', 'block_playerhud'),
                'confirm_title' => get_string('confirmation', 'admin'),
                'confirm_bulk' => get_string('confirm_bulk_delete', 'block_playerhud'),
                'delete_selected' => get_string('delete_selected', 'block_playerhud'),
                'delete_n_items' => get_string('delete_n_items', 'block_playerhud'),
                'yes' => get_string('yes'),
                'cancel' => get_string('cancel'),
                'gen_copied' => get_string('gen_copied', 'block_playerhud'),
                'error' => get_string('error'),
                'err_clipboard' => get_string('err_clipboard', 'block_playerhud'),
                'ok' => get_string('ok'),
            ],
        ];
        $PAGE->requires->js_call_amd('block_playerhud/manage_drops', 'init', [$jsconfig]);

        $output = $OUTPUT->header();
        $output .= $OUTPUT->render_from_template('block_playerhud/manage_drops_table', $templatedata);
        $output .= $OUTPUT->footer();

        return $output;
    }

    /**
     * Handles the form submission for editing a drop.
     *
     * @return string The HTML output.
     */
    public function handle_edit_form() {
        global $DB, $PAGE, $OUTPUT, $COURSE, $CFG;

        $instanceid = required_param('instanceid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);
        $itemid     = required_param('itemid', PARAM_INT);
        $dropid     = optional_param('dropid', 0, PARAM_INT);

        require_login($courseid);
        $context = \context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);

        $url = new moodle_url('/blocks/playerhud/edit_drop.php', [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'itemid' => $itemid,
        ]);
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('standard');

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $mform = new \block_playerhud\form\edit_drop_form(null, ['itemname' => $item->name]);

        if ($dropid && !$mform->is_submitted()) {
            $drop = $DB->get_record_sql(
                "SELECT d.*
                   FROM {block_playerhud_drops} d
                   JOIN {block_playerhud_items} i ON i.id = d.itemid
                  WHERE d.id = :dropid AND i.blockinstanceid = :instanceid",
                ['dropid' => $dropid, 'instanceid' => $instanceid]
            );
            $data = $drop ? (array) $drop : [];
            $data['unlimited'] = ($drop->maxusage == 0) ? 1 : 0;
            $data['maxusage']  = ($drop->maxusage == 0) ? 1 : $drop->maxusage;
            $data['instanceid'] = $instanceid;
            $data['courseid'] = $courseid;
            $mform->set_data($data);
        } else if (!$mform->is_submitted()) {
            $mform->set_data(['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]);
        }

        if ($mform->is_cancelled()) {
            redirect(new moodle_url('/blocks/playerhud/manage_drops.php', [
                'instanceid' => $instanceid,
                'id' => $courseid,
                'itemid' => $itemid,
            ]));
        } else if ($data = $mform->get_data()) {
            $this->save_drop($data);
            redirect(
                new moodle_url('/blocks/playerhud/manage_drops.php', [
                    'instanceid' => $instanceid,
                    'id' => $courseid,
                    'itemid' => $itemid,
                ]),
                get_string('drop_configured_msg', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $output = $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('drop_new_title', 'block_playerhud'));
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Saves or updates a Drop record.
     *
     * @param stdClass $data The data from the form.
     */
    private function save_drop($data) {
        global $DB, $USER;

        $record = new \stdClass();
        $record->blockinstanceid = $data->instanceid;
        $record->itemid          = $data->itemid;
        $record->name            = $data->name;
        $record->respawntime     = $data->respawntime;
        $record->timemodified    = time();

        // Unlimited logic.
        $record->maxusage = (!empty($data->unlimited)) ? 0 : max(1, (int)$data->maxusage);

        if (!empty($data->id)) {
            // Verify the drop belongs to this instance before updating to prevent cross-instance edits.
            $existing = $DB->get_record_sql(
                "SELECT d.id, d.blockinstanceid, d.itemid
                   FROM {block_playerhud_drops} d
                   JOIN {block_playerhud_items} i ON i.id = d.itemid
                  WHERE d.id = :dropid AND i.blockinstanceid = :instanceid",
                ['dropid' => $data->id, 'instanceid' => $data->instanceid]
            );
            if (!$existing) {
                throw new \moodle_exception('accessdenied', 'admin');
            }
            // Preserve ownership fields from the DB record — never trust form input for these.
            $record->blockinstanceid = $existing->blockinstanceid;
            $record->itemid          = $existing->itemid;
            $record->id              = $existing->id;
            $DB->update_record('block_playerhud_drops', $record);
        } else {
            // Verify the item belongs to this block instance before creating the drop.
            $DB->get_record(
                'block_playerhud_items',
                ['id' => $record->itemid, 'blockinstanceid' => $record->blockinstanceid],
                'id',
                MUST_EXIST
            );
            $record->timecreated = time();
            $record->code = \block_playerhud\utils::generate_drop_code($data->instanceid);
            $DB->insert_record('block_playerhud_drops', $record);
        }
    }
}
