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
                    $data['required_class_id'] = '0';
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

        // 1. Strings para o Template
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
            'delete_selected' => get_string('delete_selected', 'block_playerhud')
        ];

        // 2. Estatísticas
        $total_items = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid]);
        $sql_drops = "SELECT COUNT(d.id) 
                        FROM {block_playerhud_drops} d 
                        JOIN {block_playerhud_items} i ON d.itemid = i.id 
                       WHERE i.blockinstanceid = ?";
        $total_drops = $DB->count_records_sql($sql_drops, [$this->instanceid]);
        $summary_text = get_string('summary_stats', 'block_playerhud', ['items' => $total_items, 'drops' => $total_drops]);

        // 3. Paginação e Busca
        $page    = optional_param('page', 0, PARAM_INT);
        $perpage = 30;
        
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

        // 4. Prepara Dados para o Template
        $items_data = [];
        $context = \context_block::instance($this->instanceid);
        $counter = ($page * $perpage) + 1;

        if ($items) {
            require_once($GLOBALS['CFG']->dirroot . '/blocks/playerhud/lib.php');
            
            foreach ($items as $item) {
                $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);
                
                // Atributos de Preview para o JS
                $preview_attrs = 'data-name="' . s($item->name) . '" ' .
                                 'data-xp="' . $item->xp . ' XP" ' .
                                 'data-image="' . ($mediadata['is_image'] ? $mediadata['url'] : strip_tags($mediadata['content'])) . '" ' .
                                 'data-isimage="' . ($mediadata['is_image'] ? 1 : 0) . '"';

                $dropscount = $DB->count_records('block_playerhud_drops', ['itemid' => $item->id]);
                
                $items_data[] = [
                    'id' => $item->id,
                    'counter' => $counter++,
                    'name' => format_string($item->name),
                    'xp' => $item->xp,
                    'enabled' => (bool)$item->enabled,
                    'secret' => (bool)$item->secret,
                    
                    // Imagem
                    'is_image' => $mediadata['is_image'],
                    'image_url' => $mediadata['is_image'] ? $mediadata['url'] : '',
                    'image_content' => $mediadata['is_image'] ? '' : strip_tags($mediadata['content']),
                    
                    // Descrição (HTML Seguro)
                    'description_html' => !empty($item->description) ? format_text($item->description, FORMAT_HTML) : "",
                    'preview_attributes' => $preview_attrs,
                    
                    // Drops & Botões
                    'drops_count' => $dropscount,
                    'btn_drops_class' => ($dropscount > 0) ? 'btn-info text-white' : 'btn-outline-secondary',
                    'confirm_msg' => s(get_string('confirm_delete', 'block_playerhud') . " '" . format_string($item->name) . "'?"),
                    
                    // URLs
                    'url_toggle' => (new moodle_url($baseurl, ['action' => 'toggle', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir, 'page' => $page]))->out(false),
                    'url_edit' => (new moodle_url($baseurl, ['action' => 'edit', 'itemid' => $item->id]))->out(false),
                    'url_delete' => (new moodle_url($baseurl, ['action' => 'delete', 'itemid' => $item->id, 'sesskey' => sesskey(), 'sort' => $this->sort, 'dir' => $this->dir]))->out(false),
                    'url_drops' => (new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $this->instanceid, 'itemid' => $item->id, 'id' => $this->courseid]))->out(false),
                    
                    // Strings específicas por item (para aria-labels e titles)
                    'str_manage_drops' => get_string('manage_drops_title', 'block_playerhud', format_string($item->name)),
                    'str_secret' => $str['secret'],
                    'str_yes' => $str['yes'],
                    'str_no' => $str['no'],
                    'str_hide' => $str['hide'],
                    'str_show' => $str['show'],
                    'str_edit' => $str['edit'],
                    'str_delete' => $str['delete'],
                    'str_select' => $str['select']
                ];
            }
        }

       // 5. Links de Ordenação (Agora retornamos DADOS, não HTML)
        $headers = [
            'name' => $this->get_sort_data('name', $str['col_name'], $baseurl),
            'xp' => $this->get_sort_data('xp', $str['col_xp'], $baseurl),
            'enabled' => $this->get_sort_data('enabled', $str['col_enabled'], $baseurl),
        ];

        // 6. Dados Finais para o Mustache
        $template_data = [
            'base_url' => $baseurl->out(false),
            'sesskey' => sesskey(),
            'summary_text' => $summary_text,
            'url_add' => (new moodle_url($baseurl, ['action' => 'add']))->out(false),
            'items' => $items_data,
            'paging_bar' => $OUTPUT->paging_bar($total_items, $page, $perpage, $baseurl, 'page'),
            
            // Passamos o objeto headers estruturado
            'headers' => $headers,

            // Strings Globais
            'str_ai_create' => $str['ai_create'],
            'str_add_item' => $str['add_item'],
            'str_select_all' => $str['select_all'],
            'str_col_image' => $str['col_image'],
            'str_col_drops' => $str['col_drops'],
            'str_actions' => $str['actions'],
            'str_empty' => $str['empty'],
            'str_delete_selected' => $str['delete_selected'],
            
            // Modais
            'modal_ai_html' => $OUTPUT->render_from_template('block_playerhud/modal_ai', []),
            'modal_preview_html' => $OUTPUT->render_from_template('block_playerhud/modal_item', [])
        ];

        // 7. Inicialização do JS
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
            ]
        ];
        $PAGE->requires->js_call_amd('block_playerhud/manage_items', 'init', [$jsvars]);

        return $OUTPUT->render_from_template('block_playerhud/manage_items_table', $template_data);
    }

    /**
     * Helper para gerar dados de ordenação (Separando HTML do PHP).
     */
    private function get_sort_data($colname, $label, $baseurl) {
        $icon = 'fa-sort text-muted opacity-25'; // Classe padrão
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
            'active' => $active
        ];
    }
}
