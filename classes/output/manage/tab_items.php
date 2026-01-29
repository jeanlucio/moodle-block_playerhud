<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY;
// without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.
// If not, see <http://www.gnu.org/licenses/>.

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
     * Deve ser chamado ANTES do $OUTPUT->header() no manage.php.
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

        // Verifica se estamos no modo de Edição ou Adição
        if ($action === 'add' || ($action === 'edit' && $editid)) {
            
            $actionurl = new moodle_url($baseurl, [
                'action' => $editid ? 'edit' : 'add',
                'itemid' => $editid
            ]);

            // Instancia o formulário com a URL de ação correta
            $this->mform = new edit_item_form($actionurl->out(false), ['instanceid' => $this->instanceid]);

            // 1. Caso Cancelar
            if ($this->mform->is_cancelled()) {
                redirect($baseurl);
            } 
            // 2. Caso Salvar (Submissão válida)
            else if ($data = $this->mform->get_data()) {
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
                
                // Defaults
                $record->tradable = 1;
                $record->maxusage = 1;
                $record->respawntime = 0;
                $record->timemodified = time();

                // Tratamento de array para string (Classes)
                if (!empty($data->required_class_id) && is_array($data->required_class_id)) {
                    $record->required_class_id = implode(',', $data->required_class_id);
                } else {
                    $record->required_class_id = '0';
                }

                // Insert ou Update
                if ($itemid > 0) {
                    $DB->update_record('block_playerhud_items', $record);
                    $newitemid = $itemid;
                } else {
                    $record->timecreated = time();
                    $newitemid = $DB->insert_record('block_playerhud_items', $record);
                }

                // Salvar arquivo de imagem (File API)
                $context = \context_block::instance($this->instanceid);
                file_save_draft_area_files($data->image_file, $context->id, 'block_playerhud', 'item_image', $newitemid, ['subdirs' => 0]);

                // Redireciona (Aqui é seguro pois o header ainda não foi enviado)
                redirect($baseurl, get_string('changessaved'), \core\output\notification::NOTIFY_SUCCESS);
            }

            // 3. Caso Carregar Dados Iniciais (Apenas visualizando o form pela primeira vez)
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
                    
                    // Prepara área de rascunho para arquivos existentes
                    $draftitemid = file_get_submitted_draft_itemid('image_file');
                    $context = \context_block::instance($this->instanceid);
                    file_prepare_draft_area($draftitemid, $context->id, 'block_playerhud', 'item_image', $item->id);
                    $data['image_file'] = $draftitemid;

                    $this->mform->set_data($data);
                }
            } else if (!$editid && !$this->mform->is_submitted()) {
                // Novo item: prepara área de rascunho vazia
                $draftitemid = file_get_unused_draft_itemid();
                $this->mform->set_data(['image_file' => $draftitemid, 'itemid' => 0]);
            }
        }
    }

    /**
     * Render the tab content.
     */
    public function display() {
        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'items'
        ]);

        // Se o formulário foi instanciado no process(), exibimos ele.
        if ($this->mform !== null) {
            return $this->render_form();
        }

        // Caso contrário, exibe a lista.
        return $this->render_list_view($baseurl);
    }

    /**
     * Render the Add/Edit form.
     */
    protected function render_form() {
        global $OUTPUT;
        
        $editid = optional_param('itemid', 0, PARAM_INT);
        $title = $editid ? 
            (get_string('edit') . ' ' . get_string('item', 'block_playerhud')) : 
            get_string('item_new', 'block_playerhud');
            
        return $OUTPUT->heading($title) . $this->mform->render();
    }

    /**
     * Render the main table view with Native Bootstrap Modal.
     */
    protected function render_list_view($baseurl) {
        global $DB, $PAGE;

        // 1. O Modal Completo (Bootstrap 5 Structure)
        // Agora escrevemos o HTML completo do modal, não apenas o corpo.
        $modalhtml = '
        <div class="modal fade" id="phAiModal" tabindex="-1" aria-labelledby="phAiModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="phAiModalLabel">' . get_string('ai_btn_create', 'block_playerhud') . '</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <form id="ph-ai-form">
                    <div class="mb-3">
                        <label for="ai-theme" class="form-label">' . get_string('ai_prompt_theme_item', 'block_playerhud') . '</label>
                        <input type="text" class="form-control" id="ai-theme" placeholder="' . get_string('ai_theme_placeholder', 'block_playerhud') . '">
                    </div>
                    <div class="mb-3">
                        <label for="ai-xp" class="form-label">' . get_string('ai_placeholder_xp', 'block_playerhud') . '</label>
                        <input type="number" class="form-control" id="ai-xp" placeholder="100">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="ai-drop">
                        <label class="form-check-label" for="ai-drop">' . get_string('ai_create_drop', 'block_playerhud') . '</label>
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

        // 2. Barra de Botões
        $html = '<div class="d-flex justify-content-end mb-3">';
        // Nota: O botão agora usa atributos data-bs para abrir o modal sem precisar de JS complexo
        $html .= '<button type="button" class="btn btn-info text-white shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#phAiModal">
                    <i class="fa fa-magic" aria-hidden="true"></i> ' .
                    get_string('ai_btn_create', 'block_playerhud') . '
                  </button>';
        
        $html .= \html_writer::link(
            new moodle_url($baseurl, ['action' => 'add']), 
            '<i class="fa fa-plus-circle" aria-hidden="true"></i> ' . get_string('item_new', 'block_playerhud'), 
            ['class' => 'btn btn-primary shadow-sm']
        );
        $html .= '</div>';

        // 3. Tabela (Código padrão mantido)
        $html .= '<div class="card shadow-sm border-0"><div class="card-body p-0">';
        $html .= '<table class="table table-hover table-striped mb-0">';
        $html .= '<thead class="bg-light border-bottom"><tr>';
        $html .= '<th style="width: 60px;">' . get_string('item_image', 'block_playerhud') . '</th>';
        $html .= '<th>' . $this->get_sort_link('name', get_string('item_name', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th style="width: 100px;">' . $this->get_sort_link('xp', get_string('item_xp', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th style="width: 140px;">' . $this->get_sort_link('enabled', get_string('enabled', 'block_playerhud'), $baseurl) . '</th>';
        $html .= '<th style="width: 160px;">' . get_string('drops', 'block_playerhud') . '</th>';
        $html .= '<th class="text-end" style="width: 200px;">' . get_string('actions') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        $allowedsorts = ['name', 'xp', 'enabled'];
        if (!in_array($this->sort, $allowedsorts)) $this->sort = 'xp';
        
        $items = $DB->get_records('block_playerhud_items', 
            ['blockinstanceid' => $this->instanceid], 
            "{$this->sort} {$this->dir}"
        );

        if ($items) {
            $context = \context_block::instance($this->instanceid);
            $stredit = get_string('edit');
            $strdelete = get_string('delete');
            $stractive = get_string('yes');
            $strinactive = get_string('no');
            $strconfirm = get_string('confirm_delete', 'block_playerhud');
            
            foreach ($items as $item) {
                $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);
                if ($mediadata['is_image']) {
                    $icon = "<img src='{$mediadata['url']}' style='width:32px; height:32px; object-fit:contain;' alt=''>";
                } else {
                    $icon = "<span style='font-size:24px;'>{$mediadata['content']}</span>";
                }

                $namehtml = "<strong>" . format_string($item->name) . "</strong>";
                if ($item->secret) $namehtml .= ' <i class="fa fa-user-secret text-warning" title="' . get_string('secret', 'block_playerhud') . '"></i>';

                $xphtml = "<span class='badge bg-primary'>+{$item->xp} XP</span>";
                
                $toggleurl = new moodle_url($baseurl, ['action' => 'toggle', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir]);
                $editurl = new moodle_url($baseurl, ['action' => 'edit', 'itemid' => $item->id]);
                $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir]);
                $managedropurl = new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $this->instanceid, 'itemid' => $item->id, 'id' => $this->courseid]);
                
                $dropscount = $DB->count_records('block_playerhud_drops', ['itemid' => $item->id]);
                $btnclass = ($dropscount > 0) ? 'btn-info text-white' : 'btn-outline-secondary';
                $locationshtml = '<a href="' . $managedropurl->out() . '" class="btn btn-sm ' . $btnclass . ' w-100"><i class="fa fa-map-marker"></i> Drops: <strong>' . $dropscount . '</strong></a>';

                if ($item->enabled) {
                    $statuslabel = '<span class="badge bg-success">' . $stractive . '</span>';
                    $eyebtn = '<a href="' . $toggleurl . '" class="btn btn-sm btn-light border ms-1"><i class="fa fa-eye text-success"></i></a>';
                    $opacity = '';
                } else {
                    $statuslabel = '<span class="badge bg-secondary">' . $strinactive . '</span>';
                    $eyebtn = '<a href="' . $toggleurl . '" class="btn btn-sm btn-warning ms-1"><i class="fa fa-eye-slash"></i></a>';
                    $opacity = 'opacity: 0.5;';
                }

                $safeconfirmmsg = s($strconfirm . " '" . format_string($item->name) . "'?");
                
                $html .= "<tr style='{$opacity}'>
                        <td class='align-middle text-center'>{$icon}</td>
                        <td class='align-middle'>{$namehtml}</td>
                        <td class='align-middle'>{$xphtml}</td>
                        <td class='align-middle'>{$statuslabel} {$eyebtn}</td>
                        <td class='align-middle'>{$locationshtml}</td>
                        <td class='align-middle text-end'>
                            <a href='{$editurl}' class='btn btn-sm btn-primary me-1 shadow-sm'><i class='fa fa-pencil'></i> {$stredit}</a>
                            <a href='{$deleteurl}' class='btn btn-sm btn-danger shadow-sm js-delete-btn' data-confirm-msg=\"{$safeconfirmmsg}\"><i class='fa fa-trash'></i> {$strdelete}</a>
                        </td>
                    </tr>";
            }
        } else {
            $html .= "<tr><td colspan='6' class='text-center py-5 text-muted'>" . get_string('items_none', 'block_playerhud') . "</td></tr>";
        }

        $html .= '</tbody></table></div></div>';

        // 4. Injeta o HTML do Modal no final
        $html .= $modalhtml;

        // 5. Chamada JavaScript Simplificada
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
                'cancel' => get_string('cancel')
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
