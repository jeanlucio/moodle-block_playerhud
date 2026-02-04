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
 * Items tab management for Block PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use html_writer;
use moodle_url;
use block_playerhud\form\edit_item_form;

defined('MOODLE_INTERNAL') || die();

class tab_items implements renderable {

    /** @var int Block Instance ID */
    protected $instanceid;
    /** @var int Course ID */
    protected $courseid;
    /** @var string Sort column */
    protected $sort;
    /** @var string Sort direction */
    protected $dir;

    /** @var edit_item_form|null Formulário instanciado (se houver ação de edição/adição) */
    protected $mform = null;

    /**
     * Constructor.
     */
    public function __construct($instanceid, $courseid, $sort = 'xp', $dir = 'ASC') {
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
        $this->sort = $sort ?: 'xp';
        $this->dir = $dir ?: 'ASC';
    }

    /**
     * Processa a lógica do formulário e redirecionamentos.
     */
    public function process() {
        global $DB;

        $action = optional_param('action', '', PARAM_ALPHA);
        $editid = optional_param('itemid', 0, PARAM_INT);
        
        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'items'
        ]);

        if ($action === 'add' || ($action === 'edit' && $editid)) {
            $actionurl = new moodle_url($baseurl, [
                'action' => $editid ? 'edit' : 'add',
                'itemid' => $editid
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
                file_save_draft_area_files($data->image_file, $context->id, 'block_playerhud', 'item_image', $newitemid, ['subdirs' => 0]);

                redirect($baseurl, get_string('changessaved'), \core\output\notification::NOTIFY_SUCCESS);
            }

            if ($editid && !$this->mform->is_submitted()) {
                $item = $DB->get_record('block_playerhud_items', ['id' => $editid, 'blockinstanceid' => $this->instanceid]);
                if ($item) {
                    $data = (array)$item;
                    $data['itemid'] = $item->id;
                    if (!empty($item->required_class_id) && $item->required_class_id !== '0') {
                        $data['required_class_id'] = explode(',', $item->required_class_id);
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

    public function display() {
        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'items'
        ]);

        if ($this->mform !== null) {
            return $this->render_form();
        }
        return $this->render_list_view($baseurl);
    }

    protected function render_form() {
        global $OUTPUT;
        $editid = optional_param('itemid', 0, PARAM_INT);
        $title = $editid ? (get_string('edit') . ' ' . get_string('item', 'block_playerhud')) : get_string('item_new', 'block_playerhud');
        return $OUTPUT->heading($title) . $this->mform->render();
    }

    protected function render_list_view($baseurl) {
        global $DB, $PAGE, $OUTPUT;

        // --- 1. MODAL IA (Mantido) ---
        $modalhtml = '
        <div class="modal fade" id="phAiModal" tabindex="-1" aria-labelledby="phAiModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="phAiModalLabel">' . get_string('ai_btn_create', 'block_playerhud') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . get_string('close', 'block_playerhud') . '"></button>
              </div>
              <div class="modal-body">
                <form id="ph-ai-form">
                    <div class="mb-3">
                        <label for="ai-theme" class="form-label">' . get_string('ai_prompt_theme_item', 'block_playerhud') . '</label>
                        <input type="text" class="form-control" id="ai-theme" placeholder="' . get_string('ai_theme_placeholder', 'block_playerhud') . '">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label for="ai-xp" class="form-label">' . get_string('xp', 'block_playerhud') . '</label>
                            <input type="number" class="form-control" id="ai-xp" placeholder="' . get_string('ai_rnd_xp', 'block_playerhud') . '">
                        </div>
                        <div class="col-6">
                            <label for="ai-amount" class="form-label">' . get_string('qty', 'block_playerhud') . '</label>
                            <input type="number" class="form-control" id="ai-amount" value="1" min="1" max="5">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="ai-drop">
                        <label class="form-check-label fw-bold" for="ai-drop">' . get_string('ai_create_drop', 'block_playerhud') . '</label>
                    </div>
                    <div id="ai-drop-options" class="p-3 bg-light border rounded mb-3" style="display:none;">
                        <h6 class="border-bottom pb-2 mb-3">' . get_string('ai_drop_settings', 'block_playerhud') . '</h6>
                        <div class="mb-3">
                            <label for="ai-location" class="form-label small">' . get_string('drop_name_label', 'block_playerhud') . '</label>
                            <input type="text" class="form-control form-control-sm" id="ai-location" placeholder="Ex: Biblioteca">
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label for="ai-maxusage" class="form-label small">' . get_string('drop_max_qty', 'block_playerhud') . '</label>
                                <input type="number" class="form-control form-control-sm" id="ai-maxusage" value="0">
                                <small class="text-muted" style="font-size:0.7rem;">0 = ' . get_string('unlimited', 'block_playerhud') . '</small>
                            </div>
                            <div class="col-6">
                                <label for="ai-respawn" class="form-label small">' . get_string('drop_interval', 'block_playerhud') . ' (min)</label>
                                <input type="number" class="form-control form-control-sm" id="ai-respawn" value="0">
                                <small class="text-muted" style="font-size:0.7rem;">0 = ' . get_string('drops_immediate', 'block_playerhud') . '</small>
                            </div>
                        </div>
                    </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . get_string('cancel') . '</button>
                <button type="button" class="btn btn-primary" id="ph-btn-conjure">' . get_string('ai_btn_conjure', 'block_playerhud') . '</button>
              </div>
            </div>
          </div>
        </div>';

        // --- 2. MODAL DE DETALHES (Preview) ---
        $strdetails = get_string('details', 'block_playerhud');
        $strclose = get_string('close', 'block_playerhud');
        
        $modalhtml .= '
        <div class="modal fade" id="phItemModalView" tabindex="-1" aria-labelledby="phModalTitleView" aria-hidden="true" style="z-index: 10550;">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
              <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold m-0" id="phModalTitleView">' . $strdetails . '</h5>
                <button type="button" class="btn-close ph-modal-close-view ms-auto"
                        data-bs-dismiss="modal" aria-label="' . $strclose . '"></button>
              </div>
              <div class="modal-body">
                <div class="d-flex align-items-start">
                    <div id="phModalImageContainerView" class="me-4 text-center" style="min-width: 100px;"></div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center flex-wrap mb-3">
                            <h4 id="phModalNameView" class="m-0 me-2" style="font-weight: bold;"></h4>
                            <span id="phModalXPView" class="badge bg-info text-dark ph-badge-count">XP</span>
                        </div>
                        <div id="phModalDescView" class="text-muted text-break"></div>
                    </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . $strclose . '</button>
              </div>
            </div>
          </div>
        </div>';

        // --- 3. DADOS DE CONTAGEM E RESUMO ---
        $total_items = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        
        // Query para contar todos os drops desta instância
        $sql_drops = "SELECT COUNT(d.id) 
                        FROM {block_playerhud_drops} d 
                        JOIN {block_playerhud_items} i ON d.itemid = i.id 
                       WHERE i.blockinstanceid = ?";
        $total_drops = $DB->count_records_sql($sql_drops, [$this->instanceid]);

        $summary_text = get_string('summary_stats', 'block_playerhud', [
            'items' => $total_items,
            'drops' => $total_drops
        ]);

        // --- 4. BARRA DE TOPO (Com Sumário) ---
        $html = '<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-light rounded border shadow-sm">';
        $html .= '<div class="d-flex align-items-center">';
        $html .= '<i class="fa fa-info-circle text-primary me-2 fa-lg" aria-hidden="true"></i>';
        $html .= '<span class="fw-bold text-dark">' . $summary_text . '</span>';
        $html .= '</div>';
        
        $html .= '<div class="d-flex gap-2">';
        $html .= '<button type="button" class="btn btn-info text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#phAiModal">
                    <i class="fa fa-magic" aria-hidden="true"></i> ' .
                    get_string('ai_btn_create', 'block_playerhud') . '
                  </button>';
        $html .= \html_writer::link(
            new moodle_url($baseurl, ['action' => 'add']), 
            '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' . get_string('item_new', 'block_playerhud'), 
            ['class' => 'btn btn-primary shadow-sm']
        );
        $html .= '</div></div>';

        // --- 5. PREPARAÇÃO DA PAGINAÇÃO ---
        $page    = optional_param('page', 0, PARAM_INT);
        $perpage = 30; // Limite por página
        
        $allowedsorts = ['name', 'xp', 'enabled'];
        if (!in_array($this->sort, $allowedsorts)) $this->sort = 'xp';

        $items = $DB->get_records(
            'block_playerhud_items', 
            ['blockinstanceid' => $this->instanceid], 
            "{$this->sort} {$this->dir}",
            '*',
            $page * $perpage,
            $perpage
        );

        // --- 6. TABELA DE ITENS ---
        $html .= html_writer::start_tag('form', [
            'action' => $baseurl->out(), 
            'method' => 'post', 
            'id' => 'bulk-action-form'
        ]);
        $html .= html_writer::input_hidden_params($baseurl);
        $html .= '<input type="hidden" name="action" value="bulk_delete">';
        $html .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

        $html .= '<div class="card shadow-sm border-0"><div class="card-body p-0">';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-hover table-striped mb-0 align-middle">'; 
        
        $html .= '<thead class="bg-light border-bottom"><tr class="text-nowrap">';
        
        $html .= '<th scope="col" style="width: 40px;" class="text-center">
                    <label class="visually-hidden" for="ph-select-all">' . get_string('selectall') . '</label>
                    <input type="checkbox" id="ph-select-all" class="form-check-input">
                  </th>';

        // Coluna N. (Substituindo #)
        $html .= '<th scope="col" style="width: 50px;" class="text-center">N.</th>';

        $html .= '<th scope="col" style="min-width: 80px;">' . get_string('item_image', 'block_playerhud') . '</th>';
        $html .= '<th scope="col">' . $this->get_sort_link('name', get_string('item_name', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th scope="col" style="width: 100px;">' . $this->get_sort_link('xp', get_string('item_xp', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th scope="col" style="width: 140px;">' . $this->get_sort_link('enabled', get_string('enabled', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th scope="col" style="width: 160px;">' . get_string('drops', 'block_playerhud') . '</th>';
        $html .= '<th scope="col" class="text-end" style="width: 220px;">' . get_string('actions') . '</th>';
        
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        if ($items) {
            $context = \context_block::instance($this->instanceid);
            $stredit = get_string('edit');
            $strdelete = get_string('delete');
            $stractive = get_string('yes');
            $strinactive = get_string('no');
            $strconfirm = get_string('confirm_delete', 'block_playerhud');
            
            require_once($GLOBALS['CFG']->dirroot . '/blocks/playerhud/lib.php');

            $counter = ($page * $perpage) + 1;

            foreach ($items as $item) {
                $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);
                $desc_html = !empty($item->description) ? format_text($item->description, FORMAT_HTML) : "";
                
                $preview_attrs = " data-name='" . s($item->name) . "' " .
                                 " data-xp='+{$item->xp} XP' " .
                                 " data-image='" . ($mediadata['is_image'] ? $mediadata['url'] : strip_tags($mediadata['content'])) . "' " .
                                 " data-isimage='" . ($mediadata['is_image'] ? 1 : 0) . "'";
                
                $desc_hidden_id = 'desc_hidden_' . $item->id;
                $desc_hidden = "<div id='{$desc_hidden_id}' class='d-none'>{$desc_html}</div>";

                if ($mediadata['is_image']) {
                    $icon = "<img src='{$mediadata['url']}' style='width:32px; height:32px; object-fit:contain;' alt=''>";
                } else {
                    $icon = "<span style='font-size:24px;'>{$mediadata['content']}</span>";
                }

                $icon_link = "<a href='#' class='ph-preview-trigger text-decoration-none d-block' {$preview_attrs} data-desc-target='{$desc_hidden_id}'>{$icon}</a>";
                
                $namehtml = "<strong>" . format_string($item->name) . "</strong>";
                $name_link = "<a href='#' class='ph-preview-trigger text-dark text-decoration-none' {$preview_attrs} data-desc-target='{$desc_hidden_id}'>{$namehtml}</a>";
                
                if ($item->secret) $name_link .= ' <i class="fa fa-user-secret text-warning" title="' . get_string('secret', 'block_playerhud') . '"></i>';

                $xphtml = "<span class='badge bg-primary'>+{$item->xp} XP</span>";
                
                $toggleurl = new moodle_url($baseurl, ['action' => 'toggle', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir, 'page' => $page]);
                $editurl = new moodle_url($baseurl, ['action' => 'edit', 'itemid' => $item->id]);
                $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir]);
                $managedropurl = new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $this->instanceid, 'itemid' => $item->id, 'id' => $this->courseid]);
                
                $dropscount = $DB->count_records('block_playerhud_drops', ['itemid' => $item->id]);
                $btnclass = ($dropscount > 0) ? 'btn-info text-white' : 'btn-outline-secondary';
                $locationshtml = '<a href="' . $managedropurl->out() . '" class="btn btn-sm ' . $btnclass . ' w-100" aria-label="'.get_string('manage_drops_title', 'block_playerhud', format_string($item->name)).'"><i class="fa fa-map-marker" aria-hidden="true"></i> Drops: <strong>' . $dropscount . '</strong></a>';

                if ($item->enabled) {
                    $statuslabel = '<span class="badge bg-success">' . $stractive . '</span>';
                    $eyebtn = '<a href="' . $toggleurl . '" class="btn btn-sm btn-light border ms-1" aria-label="' . get_string('click_to_hide', 'block_playerhud') . '"><i class="fa fa-eye text-success" aria-hidden="true"></i></a>';
                    $opacity = '';
                } else {
                    $statuslabel = '<span class="badge bg-secondary">' . $strinactive . '</span>';
                    $eyebtn = '<a href="' . $toggleurl . '" class="btn btn-sm btn-warning ms-1" aria-label="' . get_string('click_to_show', 'block_playerhud') . '"><i class="fa fa-eye-slash" aria-hidden="true"></i></a>';
                    $opacity = 'opacity: 0.5;';
                }

                $safeconfirmmsg = s($strconfirm . " '" . format_string($item->name) . "'?");
                
                // Botões com Texto Restaurados
                $btnEdit = "<a href='{$editurl}' class='btn btn-sm btn-primary me-1 shadow-sm' aria-label='{$stredit}'><i class='fa fa-pencil' aria-hidden='true'></i> {$stredit}</a>";
                $btnDelete = "<a href='{$deleteurl}' class='btn btn-sm btn-danger shadow-sm js-delete-btn' aria-label='{$strdelete}' data-confirm-msg=\"{$safeconfirmmsg}\"><i class='fa fa-trash' aria-hidden='true'></i> {$strdelete}</a>";

                $html .= "<tr style='{$opacity}'>
                        <td class='align-middle text-center'>
                            <label class='visually-hidden' for='chk-{$item->id}'>" . get_string('select') . " " . s($item->name) . "</label>
                            <input type='checkbox' name='bulk_ids[]' value='{$item->id}' id='chk-{$item->id}' class='form-check-input ph-bulk-check'>
                        </td>
                        <td class='align-middle text-center text-muted small'>{$counter}</td>
                        <td class='align-middle text-center position-relative'>
                            {$icon_link}
                        </td>
                        <td class='align-middle'>
                            {$name_link}
                            {$desc_hidden}
                        </td>
                        <td class='align-middle'>{$xphtml}</td>
                        <td class='align-middle'>{$statuslabel} {$eyebtn}</td>
                        <td class='align-middle'>{$locationshtml}</td>
                        <td class='align-middle text-end'>
                            {$btnEdit}
                            {$btnDelete}
                        </td>
                    </tr>";
                
                $counter++;
            }
        } else {
            $html .= "<tr><td colspan='8' class='text-center py-5 text-muted'>" . get_string('items_none', 'block_playerhud') . "</td></tr>";
        }

        $html .= '</tbody></table></div></div>';

        $html .= '<div class="mt-3">
                    <button type="submit" class="btn btn-danger shadow-sm disabled" id="ph-btn-bulk-delete" disabled>
                        <i class="fa fa-trash"></i> ' . get_string('delete_selected', 'block_playerhud') . '
                    </button>
                  </div>';

        $html .= html_writer::end_tag('form');

        $html .= $OUTPUT->paging_bar($total_items, $page, $perpage, $baseurl, 'page');

        $html .= $modalhtml;

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
            ]
        ];
        
        $PAGE->requires->js_call_amd('block_playerhud/manage_items', 'init', [$jsvars]);

        return $html;
    }

    private function get_sort_link($colname, $label, $baseurl) {
        $icon = '<i class="fa fa-sort text-muted" style="opacity:0.3; margin-left:5px;" aria-hidden="true"></i>';
        $nextdir = 'ASC';
        if ($this->sort == $colname) {
            if ($this->dir == 'ASC') {
                $icon = '<i class="fa fa-sort-asc text-primary" style="margin-left:5px;" aria-hidden="true"></i>';
                $nextdir = 'DESC';
            } else {
                $icon = '<i class="fa fa-sort-desc text-primary" style="margin-left:5px;" aria-hidden="true"></i>';
                $nextdir = 'ASC';
            }
        }
        $url = new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]);
        return "<a href='$url' class='text-dark text-decoration-none fw-bold d-flex align-items-center'>$label $icon</a>";
    }
}
