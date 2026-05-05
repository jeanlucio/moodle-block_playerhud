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
 * Items tab management for Block PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use html_writer;
use moodle_url;
use block_playerhud\form\edit_item_form;

/**
 * Items tab management for Block PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_items implements renderable {
    /** @var int Block Instance ID */
    protected $instanceid;
    /** @var int Course ID */
    protected $courseid;
    /** @var string Sort column */
    protected $sort;
    /** @var string Sort direction */
    protected $dir;

    /** @var edit_item_form|null Instantiated form (if there is an edit/add action) */
    protected $mform = null;

    /**
     * Constructor.
     *
     * @param int $instanceid The block instance ID.
     * @param int $courseid The course ID.
     * @param string $sort The column to sort by.
     * @param string $dir The sort direction.
     */
    public function __construct($instanceid, $courseid, $sort = 'xp', $dir = 'ASC') {
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
        $this->sort = $sort ?: 'xp';
        $this->dir = $dir ?: 'ASC';
    }

    /**
     * Process form logic and redirects.
     */
    public function process() {
        global $DB;

        $action = optional_param('action', '', PARAM_ALPHA);
        $editid = optional_param('itemid', 0, PARAM_INT);

        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'items',
        ]);

        if ($action === 'add' || ($action === 'edit' && $editid)) {
            $actionurl = new moodle_url($baseurl, [
                'action' => $editid ? 'edit' : 'add',
                'itemid' => $editid,
            ]);

            $this->mform = new edit_item_form($actionurl->out(false), ['instanceid' => $this->instanceid]);

            if ($this->mform->is_cancelled()) {
                redirect($baseurl);
            } else if ($data = $this->mform->get_data()) {
                $itemid = isset($data->itemid) ? (int)$data->itemid : 0;

                $record = new \stdClass();
                if ($itemid > 0) {
                    $record->id = $itemid;
                }
                $record->blockinstanceid = $this->instanceid;
                $record->name = $data->name;
                $record->image = $data->image;
                $record->xp = $data->xp;
                $record->enabled = $data->enabled;
                $record->secret = $data->secret;
                $record->description = $data->description['text'];
                $record->tradable = 1;
                $record->maxusage = 1;
                $record->respawntime = 0;
                $record->timemodified = time();

                if (!empty($data->required_class_id) && is_array($data->required_class_id)) {
                    $record->required_class_id = implode(',', $data->required_class_id);
                } else {
                    $record->required_class_id = '0';
                }

                if ($itemid > 0) {
                    $DB->update_record('block_playerhud_items', $record);
                    $newitemid = $itemid;
                } else {
                    $record->timecreated = time();
                    $newitemid = $DB->insert_record('block_playerhud_items', $record);
                }

                $context = \context_block::instance($this->instanceid);
                file_save_draft_area_files(
                    $data->image_file,
                    $context->id,
                    'block_playerhud',
                    'item_image',
                    $newitemid,
                    ['subdirs' => 0]
                );

                redirect($baseurl, get_string('changessaved'), \core\output\notification::NOTIFY_SUCCESS);
            }

            if ($editid && !$this->mform->is_submitted()) {
                $item = $DB->get_record('block_playerhud_items', ['id' => $editid, 'blockinstanceid' => $this->instanceid]);
                if ($item) {
                    $data = (array)$item;
                    $data['itemid'] = $item->id;
                    if (!empty($item->required_class_id) && $item->required_class_id !== '0') {
                        $data['required_class_id'] = array_map('intval', explode(',', $item->required_class_id));
                    } else {
                        $data['required_class_id'] = [];
                    }
                    $data['description'] = ['text' => $item->description, 'format' => FORMAT_HTML];

                    $draftitemid = file_get_submitted_draft_itemid('image_file');
                    $context = \context_block::instance($this->instanceid);
                    file_prepare_draft_area($draftitemid, $context->id, 'block_playerhud', 'item_image', $item->id);
                    $data['image_file'] = $draftitemid;

                    $this->mform->set_data($data);
                }
            } else if (!$editid && !$this->mform->is_submitted()) {
                $draftitemid = file_get_unused_draft_itemid();
                $this->mform->set_data(['image_file' => $draftitemid, 'itemid' => 0]);
            }
        }
    }

    /**
     * Display the tab content.
     *
     * @return string HTML content.
     */
    public function display() {
        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'items',
        ]);

        $action = optional_param('action', '', PARAM_ALPHA);

        if ($this->mform !== null) {
            return $this->render_form();
        }
        if ($action === 'distribute') {
            return $this->render_distribute_view($baseurl);
        }
        return $this->render_list_view($baseurl);
    }

    /**
     * Render the editing form.
     *
     * @return string HTML content.
     */
    protected function render_form() {
        global $OUTPUT;
        $editid = optional_param('itemid', 0, PARAM_INT);
        $title = $editid ? (get_string('edit') . ' ' . get_string('item', 'block_playerhud'))
            : get_string('item_new', 'block_playerhud');
        return $OUTPUT->heading($title) . $this->mform->render();
    }

    /**
     * Render the list of items.
     *
     * @param moodle_url $baseurl The base URL for the page.
     * @return string HTML content.
     */
    protected function render_list_view($baseurl) {
        global $DB, $PAGE, $OUTPUT;

        // 1. Template Strings.
        $str = [
            'summary_stats' => get_string('summary_stats', 'block_playerhud'),
            'ai_create' => get_string('ai_btn_create', 'block_playerhud'),
            'add_item' => get_string('item_new', 'block_playerhud'),
            'select_all' => get_string('selectall'),
            'select' => get_string('select'),
            'col_image' => get_string('item_image', 'block_playerhud'),
            'col_name' => get_string('item_name', 'block_playerhud'),
            'col_xp' => get_string('item_xp', 'block_playerhud'),
            'col_enabled' => get_string('enabled', 'block_playerhud'),
            'col_drops' => get_string('drops', 'block_playerhud'),
            'actions' => get_string('actions'),
            'secret' => get_string('secret', 'block_playerhud'),
            'yes' => get_string('yes'),
            'no' => get_string('no'),
            'hide' => get_string('click_to_hide', 'block_playerhud'),
            'show' => get_string('click_to_show', 'block_playerhud'),
            'edit' => get_string('edit'),
            'delete' => get_string('delete'),
            'manage_drops' => get_string('manage_drops_title', 'block_playerhud', ''),
            'empty' => get_string('items_none', 'block_playerhud'),
            'delete_selected' => get_string('delete_selected', 'block_playerhud'),
            'infinite_drops' => get_string('infinite_drops', 'block_playerhud'),
        ];

        // 2. Statistics.
        $totalitems = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $sqldrops = "SELECT COUNT(d.id)
                       FROM {block_playerhud_drops} d
                       JOIN {block_playerhud_items} i ON d.itemid = i.id
                      WHERE i.blockinstanceid = ?";
        $totaldrops = $DB->count_records_sql($sqldrops, [$this->instanceid]);
        $summarytext = get_string('summary_stats', 'block_playerhud', ['items' => $totalitems, 'drops' => $totaldrops]);

        // 3. Pagination and Search.
        $page    = optional_param('page', 0, PARAM_INT);
        $perpage = 30;

        $allowedsorts = ['name', 'xp', 'enabled'];
        if (!in_array($this->sort, $allowedsorts)) {
            $this->sort = 'xp';
        }

        $items = $DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $this->instanceid],
            "{$this->sort} {$this->dir}",
            '*',
            $page * $perpage,
            $perpage
        );

        // 4. Prepare Data for Template.
        $itemsdata = [];
        $context = \context_block::instance($this->instanceid);
        $counter = ($page * $perpage) + 1;

        if ($items) {
            $dropscounts = [];
            $dropstotals = [];
            $dropsinfinite = [];
            $itemids = array_keys($items);
            if (!empty($itemids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($itemids);
                $sql = "SELECT itemid,
                               COUNT(id) AS count,
                               SUM(CASE WHEN maxusage > 0 THEN maxusage ELSE 0 END) AS totaluses,
                               MAX(CASE WHEN maxusage = 0 THEN 1 ELSE 0 END) AS hasinfinite
                          FROM {block_playerhud_drops}
                         WHERE itemid $insql
                      GROUP BY itemid";
                $dropsrows = $DB->get_records_sql($sql, $inparams);
                foreach ($dropsrows as $row) {
                    $dropscounts[$row->itemid] = (int)$row->count;
                    $dropstotals[$row->itemid] = (int)$row->totaluses;
                    $dropsinfinite[$row->itemid] = (bool)$row->hasinfinite;
                }
            }

            // Bulk load media.
            $allmedia = \block_playerhud\utils::get_items_display_data($items, $context);

            foreach ($items as $item) {
                $mediadata = $allmedia[$item->id];

                $dropscount = $dropscounts[$item->id] ?? 0;
                $dropstotaluses = $dropstotals[$item->id] ?? 0;
                $dropisinfinite = $dropsinfinite[$item->id] ?? false;

                $itemsdata[] = [
                    'id' => $item->id,
                    'counter' => $counter++,
                    'name' => format_string($item->name),
                    'xp' => $item->xp,
                    'enabled' => (bool)$item->enabled,
                    'secret' => (bool)$item->secret,

                    // Image.
                    'is_image' => $mediadata['is_image'],
                    'image_url' => $mediadata['is_image'] ? $mediadata['url'] : '',
                    'image_content' => $mediadata['is_image'] ? '' : strip_tags($mediadata['content']),

                    // Description (Safe HTML).
                    'description_html' => !empty($item->description) ? format_text($item->description, FORMAT_HTML) : "",

                    // Preview data-* attributes rendered individually via {{...}} in template.
                    'preview_data_name' => $item->name,
                    'preview_data_xp' => $item->xp . ' XP',
                    'preview_data_image' => $mediadata['is_image']
                        ? $mediadata['url']
                        : strip_tags($mediadata['content']),
                    'preview_data_isimage' => $mediadata['is_image'] ? 1 : 0,

                    // Drops & Buttons.
                    'drops_count' => $dropscount,
                    'drops_total_uses' => $dropstotaluses,
                    'drops_has_infinite' => $dropisinfinite,
                    'drops_has_finite' => ($dropstotaluses > 0),
                    'btn_drops_class' => ($dropscount > 0) ? 'btn-info text-white' : 'btn-outline-secondary',
                    'confirm_msg' => s(get_string('confirm_delete', 'block_playerhud') . " '" . format_string($item->name) . "'?"),

                    // URLs.
                    'url_toggle' => (new moodle_url($baseurl, [
                        'action' => 'toggle',
                        'itemid' => $item->id,
                        'sesskey' => sesskey(),
                        'sort' => $this->sort,
                        'dir' => $this->dir,
                        'page' => $page,
                    ]))->out(false),
                    'url_edit' => (new moodle_url($baseurl, ['action' => 'edit', 'itemid' => $item->id]))->out(false),
                    'url_delete' => (new moodle_url($baseurl, [
                        'action' => 'delete',
                        'itemid' => $item->id,
                        'sesskey' => sesskey(),
                        'sort' => $this->sort,
                        'dir' => $this->dir,
                    ]))->out(false),
                    'url_drops' => (new moodle_url('/blocks/playerhud/manage_drops.php', [
                        'instanceid' => $this->instanceid,
                        'itemid' => $item->id,
                        'id' => $this->courseid,
                    ]))->out(false),

                    // Item specific strings.
                    'str_manage_drops' => get_string('manage_drops_title', 'block_playerhud', format_string($item->name)),
                    'str_secret' => $str['secret'],
                    'str_yes' => $str['yes'],
                    'str_no' => $str['no'],
                    'str_hide' => $str['hide'],
                    'str_show' => $str['show'],
                    'str_edit' => $str['edit'],
                    'str_delete' => $str['delete'],
                    'str_select' => $str['select'],
                ];
            }
        }

        // 5. Sort Links (Returning Data, not HTML).
        $headers = [
            'name' => $this->get_sort_data('name', $str['col_name'], $baseurl),
            'xp' => $this->get_sort_data('xp', $str['col_xp'], $baseurl),
            'enabled' => $this->get_sort_data('enabled', $str['col_enabled'], $baseurl),
        ];

        // 6. Final Data for Mustache.
        $templatedata = [
            'base_url' => $baseurl->out(false),
            'sesskey' => sesskey(),
            'summary_text' => $summarytext,
            'summary_hint' => get_string('summary_stats_hint', 'block_playerhud'),
            'str_legend_title' => get_string('drops_legend_title', 'block_playerhud'),
            'str_legend_locations' => get_string('drops_legend_locations', 'block_playerhud'),
            'str_legend_uses' => get_string('drops_legend_uses', 'block_playerhud'),
            'str_legend_infinite' => get_string('drops_legend_infinite', 'block_playerhud'),
            'url_add' => (new moodle_url($baseurl, ['action' => 'add']))->out(false),
            'url_distribute' => (new moodle_url($baseurl, ['action' => 'distribute']))->out(false),
            'str_distribute' => get_string('distribute_btn', 'block_playerhud'),
            'items' => $itemsdata,
            'paging_bar' => $OUTPUT->paging_bar($totalitems, $page, $perpage, $baseurl, 'page'),

            // Structured headers object.
            'headers' => $headers,

            // Global Strings.
            'str_ai_create' => $str['ai_create'],
            'str_add_item' => $str['add_item'],
            'str_select_all' => $str['select_all'],
            'str_col_image' => $str['col_image'],
            'str_col_drops' => $str['col_drops'],
            'str_infinite_drops' => $str['infinite_drops'],
            'str_actions' => $str['actions'],
            'str_empty' => $str['empty'],
            'str_delete_selected' => $str['delete_selected'],

            // Modals.
            'modal_ai_html' => $OUTPUT->render_from_template('block_playerhud/modal_ai', []),
            'modal_preview_html' => $OUTPUT->render_from_template('block_playerhud/modal_item', []),
        ];

        // 7. JS Initialization.
        $jsvars = [
            'courseid' => $this->courseid,
            'instanceid' => $this->instanceid,
            'strings' => [
                'err_theme' => get_string('ai_validation_theme', 'block_playerhud'),
                'success' => get_string('ai_success', 'block_playerhud'),
                'copy' => get_string('gen_copy', 'block_playerhud'),
                'great' => get_string('great', 'block_playerhud'),
                'confirm_title' => get_string('confirmation', 'admin'),
                'yes' => get_string('yes'),
                'cancel' => get_string('cancel'),
                'ai_creating' => get_string('ai_creating', 'block_playerhud'),
                'success_title' => get_string('success', 'core'),
                'no_desc' => get_string('no_description', 'block_playerhud'),
                'delete_selected' => get_string('delete_selected', 'block_playerhud'),
                'delete_n_items' => get_string('delete_n_items', 'block_playerhud'),
                'confirm_bulk' => get_string('confirm_bulk_delete', 'block_playerhud'),
                'created_count' => get_string('ai_created_count', 'block_playerhud'),
            ],
        ];
        $PAGE->requires->js_call_amd('block_playerhud/manage_items', 'init', [$jsvars]);

        return $OUTPUT->render_from_template('block_playerhud/manage_items_table', $templatedata);
    }

    /**
     * Render the drop distribution view.
     *
     * @param moodle_url $baseurl Base URL for the items tab.
     * @return string HTML content.
     */
    protected function render_distribute_view($baseurl) {
        global $DB, $PAGE, $OUTPUT;

        // 1. Load all drops with their item info (including image) for this block instance.
        $sql = "SELECT d.id, d.code, d.name AS drop_name,
                       i.id AS item_id, i.name AS item_name, i.image AS item_image
                  FROM {block_playerhud_drops} d
                  JOIN {block_playerhud_items} i ON d.itemid = i.id
                 WHERE i.blockinstanceid = :instanceid
              ORDER BY i.name ASC, d.name ASC";
        $drops = $DB->get_records_sql($sql, ['instanceid' => $this->instanceid]);

        // 2. Load all course modules that have editable text fields.
        $course = get_course($this->courseid);
        $modinfo = get_fast_modinfo($course);
        $modules = [];
        foreach ($modinfo->get_cms() as $cm) {
            // Skip modules being deleted; include hidden modules (teachers can distribute to them).
            if (!empty($cm->deletioninprogress)) {
                continue;
            }
            // Only include modules whose table has an intro or content field.
            $columns = $DB->get_columns($cm->modname);
            if (!isset($columns['intro']) && !isset($columns['content'])) {
                continue;
            }
            $supportscontent = ($cm->modname === 'page');
            $islabel         = ($cm->modname === 'label');
            $modules[] = [
                'cmid'               => $cm->id,
                'instance'           => $cm->instance,
                'name'               => format_string($cm->name),
                'modname'            => $cm->modname,
                'modname_translated' => get_string('modulename', 'mod_' . $cm->modname),
                'supports_content'   => $supportscontent,
                'supports_content_int' => $supportscontent ? 1 : 0,
                'is_label'           => $islabel,
                'is_label_int'       => $islabel ? 1 : 0,
            ];
        }

        // 3. Pre-compute which cmids already contain each drop's shortcode.
        $insertedmap = $this->get_inserted_cmids($drops, $modules);

        // 4. Bulk-load item images to avoid N+1.
        $context = \context_block::instance($this->instanceid);
        $itemsforimg = [];
        foreach ($drops as $drop) {
            if (!isset($itemsforimg[$drop->item_id])) {
                $fakeitem = new \stdClass();
                $fakeitem->id = $drop->item_id;
                $fakeitem->image = $drop->item_image;
                $itemsforimg[$drop->item_id] = $fakeitem;
            }
        }
        $allmedia = \block_playerhud\utils::get_items_display_data($itemsforimg, $context);

        // 5. Build data for each drop row.
        $dropsdata = [];
        foreach ($drops as $drop) {
            $insertedinfo = $insertedmap[$drop->id] ?? ['cmids' => [], 'first_cmid' => null, 'first_field' => 'intro'];
            $insertedcmids = $insertedinfo['cmids'];
            $insertedanywhere = !empty($insertedcmids);

            // For inserted drops use the actual location; for pending use heuristic suggestion.
            if ($insertedanywhere) {
                $suggestedcmid = $insertedinfo['first_cmid'];
            } else {
                $suggested = $this->suggest_module($drop->drop_name . ' ' . $drop->item_name, $modules);
                $suggestedcmid = $suggested ? $suggested['cmid'] : null;
            }

            // Build human-readable list of activities where this drop is already present.
            $insertednames = [];
            foreach ($modules as $mod) {
                if (in_array($mod['cmid'], $insertedcmids)) {
                    $insertednames[] = format_string($mod['name']);
                }
            }

            $rowmodules = [];
            foreach ($modules as $mod) {
                $m = $mod;
                $m['selected'] = ($suggestedcmid && $mod['cmid'] === $suggestedcmid);
                $rowmodules[] = $m;
            }

            // Image data for the item.
            $mediadata = $allmedia[$drop->item_id] ?? ['is_image' => false, 'url' => '', 'content' => ''];

            $dropsdata[] = [
                'id'                  => $drop->id,
                'code'                => $drop->code,
                'drop_name'           => format_string($drop->drop_name),
                'item_name'           => format_string($drop->item_name),
                'is_image'            => (bool)$mediadata['is_image'],
                'image_url'           => $mediadata['is_image'] ? $mediadata['url'] : '',
                'image_content'       => $mediadata['is_image'] ? '' : strip_tags($mediadata['content']),
                'modules'             => $rowmodules,
                'inserted_cmids_json'  => json_encode($insertedcmids),
                'inserted_anywhere'     => $insertedanywhere,
                'inserted_anywhere_int' => $insertedanywhere ? 1 : 0,
                'inserted_field'      => $insertedinfo['first_field'],
                'inserted_field_label' => $insertedanywhere
                    ? get_string(
                        $insertedinfo['first_field'] === 'content'
                            ? 'distribute_field_content'
                            : 'distribute_field_intro',
                        'block_playerhud'
                    )
                    : '',
                'inserted_names'      => implode(', ', $insertednames),
            ];
        }

        // 4. Template data.
        $templatedata = [
            'instanceid'          => $this->instanceid,
            'courseid'            => $this->courseid,
            'url_back'            => $baseurl->out(false),
            'drops'               => $dropsdata,
            'has_drops'           => !empty($dropsdata),
            'has_modules'         => !empty($modules),
            'str_title'           => get_string('distribute_title', 'block_playerhud'),
            'str_desc'            => get_string('distribute_desc', 'block_playerhud'),
            'str_col_drop'        => get_string('distribute_col_drop', 'block_playerhud'),
            'str_col_activity'    => get_string('distribute_col_activity', 'block_playerhud'),
            'str_col_field'       => get_string('distribute_col_field', 'block_playerhud'),
            'str_col_position'    => get_string('distribute_col_position', 'block_playerhud'),
            'str_col_status'      => get_string('distribute_col_status', 'block_playerhud'),
            'str_no_drops'        => get_string('distribute_no_drops', 'block_playerhud'),
            'str_no_modules'      => get_string('distribute_err_no_modules', 'block_playerhud'),
            'str_back'            => get_string('back', 'block_playerhud'),
            'str_field_intro'     => get_string('distribute_field_intro', 'block_playerhud'),
            'str_field_content'   => get_string('distribute_field_content', 'block_playerhud'),
            'str_field_label'     => get_string('distribute_field_intro_label', 'block_playerhud'),
            'str_pos_top'         => get_string('distribute_pos_top', 'block_playerhud'),
            'str_pos_bottom'      => get_string('distribute_pos_bottom', 'block_playerhud'),
        ];

        // 5. JS initialisation.
        $jsvars = [
            'instanceid' => $this->instanceid,
            'courseid'   => $this->courseid,
            'strings'    => [
                'inserting'        => get_string('distribute_inserting', 'block_playerhud'),
                'inserted'         => get_string('distribute_inserted', 'block_playerhud'),
                'btn_insert'       => get_string('distribute_btn', 'block_playerhud'),
                'field_intro'      => get_string('distribute_field_intro', 'block_playerhud'),
                'field_content'    => get_string('distribute_field_content', 'block_playerhud'),
                'field_label'      => get_string('distribute_field_intro_label', 'block_playerhud'),
                'insert_selected'  => get_string('distribute_insert_selected', 'block_playerhud', '__N__'),
                'no_selection'     => get_string('distribute_no_selection', 'block_playerhud'),
                'select_all'       => get_string('selectall'),
                'remove'           => get_string('distribute_remove', 'block_playerhud'),
                'removing'         => get_string('distribute_removing', 'block_playerhud'),
                'remove_confirm'   => get_string('distribute_remove_confirm', 'block_playerhud'),
                'undo_selected'    => get_string('distribute_undo_selected', 'block_playerhud', '__N__'),
            ],
        ];
        $PAGE->requires->js_call_amd('block_playerhud/distribute_drops', 'init', [$jsvars]);

        return $OUTPUT->render_from_template('block_playerhud/distribute_drops', $templatedata);
    }

    /**
     * Suggest the best matching module for a drop based on name similarity.
     *
     * @param string $haystack Combined drop and item name.
     * @param array $modules List of module data arrays.
     * @return array|null Best matching module or null if list is empty.
     */
    private function suggest_module($haystack, $modules) {
        if (empty($modules)) {
            return null;
        }
        $best = null;
        $bestscore = -1;
        $haystack = strtolower($haystack);
        foreach ($modules as $mod) {
            similar_text($haystack, strtolower($mod['name']), $percent);
            if ($percent > $bestscore) {
                $bestscore = $percent;
                $best = $mod;
            }
        }
        return $best;
    }

    /**
     * Return a map of drop ID => list of cmids where the drop shortcode is already present.
     *
     * Queries are batched per module type to avoid N+1.
     *
     * @param array $drops Keyed by drop ID, each with a ->code property.
     * @param array $modules List of module data arrays (must include 'cmid', 'instance', 'modname').
     * @return array [dropid => int[]]
     */
    private function get_inserted_cmids(array $drops, array $modules): array {
        global $DB;

        if (empty($drops) || empty($modules)) {
            return [];
        }

        // Group modules by type: [modname => [instance_id => cmid]].
        $bytype = [];
        foreach ($modules as $mod) {
            $bytype[$mod['modname']][$mod['instance']] = $mod['cmid'];
        }

        // For each module type, load intro/content fields in one query.
        // Result: [cmid => ['intro' => text, 'content' => text]].
        $contentbycmid = [];
        foreach ($bytype as $modname => $instances) {
            $instanceids = array_keys($instances);
            [$insql, $inparams] = $DB->get_in_or_equal($instanceids);

            $columns = $DB->get_columns($modname);
            $fields = ['id'];
            if (isset($columns['intro'])) {
                $fields[] = 'intro';
            }
            if (isset($columns['content'])) {
                $fields[] = 'content';
            }

            $rows = $DB->get_records_select($modname, "id $insql", $inparams, '', implode(',', $fields));
            foreach ($rows as $row) {
                $cmid = $instances[$row->id];
                $contentbycmid[$cmid] = [];
                if (isset($row->intro)) {
                    $contentbycmid[$cmid]['intro'] = (string)$row->intro;
                }
                if (isset($row->content)) {
                    $contentbycmid[$cmid]['content'] = (string)$row->content;
                }
            }
        }

        // For each drop, collect cmids and note the first field where the shortcode was found.
        $result = [];
        foreach ($drops as $drop) {
            $needle = 'code=' . $drop->code;
            $insertedcmids = [];
            $firstcmid = null;
            $firstfield = 'intro';

            foreach ($contentbycmid as $cmid => $fields) {
                foreach ($fields as $fieldname => $text) {
                    if (strpos($text, $needle) !== false) {
                        if ($firstcmid === null) {
                            $firstcmid = $cmid;
                            $firstfield = $fieldname;
                        }
                        if (!in_array($cmid, $insertedcmids)) {
                            $insertedcmids[] = $cmid;
                        }
                        break; // One field match per cmid is enough.
                    }
                }
            }

            $result[$drop->id] = [
                'cmids'       => $insertedcmids,
                'first_cmid'  => $firstcmid,
                'first_field' => $firstfield,
            ];
        }

        return $result;
    }

    /**
     * Helper to generate sort data (HTML-free).
     *
     * @param string $colname Column name.
     * @param string $label Column label.
     * @param moodle_url $baseurl Base URL.
     * @return array Sort data.
     */
    private function get_sort_data($colname, $label, $baseurl) {
        // Default icon class.
        $icon = 'fa-sort text-muted opacity-25';
        $nextdir = 'ASC';
        $active = false;

        if ($this->sort == $colname) {
            $active = true;
            if ($this->dir == 'ASC') {
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
            'active' => $active,
        ];
    }
}
