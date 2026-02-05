<?php
namespace block_playerhud\controller;

use moodle_url;
use html_writer;
use html_table;

defined('MOODLE_INTERNAL') || die();

class drops {

    private function get_sort_link($colname, $label, $currentsort, $currentdir, $baseurl) {
        $icon = '<i class="fa fa-sort text-muted" style="opacity:0.3; margin-left:5px;" aria-hidden="true"></i>';
        $nextdir = 'ASC';

        if ($currentsort == $colname) {
            if ($currentdir == 'ASC') {
                $icon = '<i class="fa fa-sort-asc text-primary" style="margin-left:5px;" aria-hidden="true"></i>';
                $nextdir = 'DESC';
            } else {
                $icon = '<i class="fa fa-sort-desc text-primary" style="margin-left:5px;" aria-hidden="true"></i>';
                $nextdir = 'ASC';
            }
        }
        
        $url = new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]);
        return html_writer::link($url, $label . $icon, [
            'class' => 'text-dark text-decoration-none fw-bold d-flex align-items-center'
        ]);
    }

    public function view_manage_page() {
        global $DB, $PAGE, $OUTPUT, $COURSE, $CFG;

        $instanceid = required_param('instanceid', PARAM_INT);
        $courseid   = required_param('id', PARAM_INT);
        $itemid     = required_param('itemid', PARAM_INT);
        $action     = optional_param('action', '', PARAM_ALPHANUMEXT);
        $dropid     = optional_param('dropid', 0, PARAM_INT);
        $sort = optional_param('sort', 'id', PARAM_ALPHA);
        $dir  = optional_param('dir', 'DESC', PARAM_ALPHA);

        require_login($courseid);
        $context = \context_block::instance($instanceid);
        $coursecontext = \context_course::instance($courseid); 
        
        require_capability('block/playerhud:manage', $context);

        $baseurl = new moodle_url('/blocks/playerhud/manage_drops.php', [
            'instanceid' => $instanceid, 
            'id' => $courseid, 
            'itemid' => $itemid
        ]);

        $PAGE->set_url($baseurl);
        $PAGE->set_context($context);
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('incourse'); 

        // --- AÇÕES ---
        if ($action === 'delete' && $dropid && confirm_sesskey()) {
            $DB->delete_records('block_playerhud_drops', ['id' => $dropid]);
            redirect($baseurl, get_string('deleted', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
        }

        if ($action === 'bulk_delete' && confirm_sesskey()) {
            $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
            if (!empty($bulkids)) {
                $count = 0;
                foreach ($bulkids as $did) {
                    $DB->delete_records('block_playerhud_drops', ['id' => $did, 'itemid' => $itemid]);
                    $count++;
                }
                redirect($baseurl, get_string('deleted_bulk', 'block_playerhud', $count), \core\output\notification::NOTIFY_SUCCESS);
            }
        }

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        
        $allowedsorts = ['id', 'name', 'maxusage', 'respawntime'];
        if (!in_array($sort, $allowedsorts)) $sort = 'id';
        $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $itemid], "$sort $dir");

        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');
        $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);
        $strgentitle = get_string('gen_title', 'block_playerhud');

        $output = $OUTPUT->header();
        $output .= html_writer::start_div('container-fluid p-0 animate__animated animate__fadeIn');

        // --- HEADER ---
        $iconhtml = $mediadata['is_image'] 
            ? "<img src='{$mediadata['url']}' style='width:50px; height:50px; object-fit:contain; margin-right:15px;' alt=''>"
            : "<span style='font-size:40px; margin-right:15px;' aria-hidden='true'>{$mediadata['content']}</span>";

        $url_course = new moodle_url('/course/view.php', ['id' => $courseid]);
        $url_library = new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'items']);

        $output .= '
        <div class="d-flex align-items-center mb-4 border-bottom pb-3">
            <a href="' . $url_course . '" 
               class="btn btn-outline-secondary me-3 shadow-sm">
                <i class="fa fa-arrow-left" aria-hidden="true"></i> ' . get_string('back_to_course', 'block_playerhud') . '
            </a>
            <div class="d-flex align-items-center">
                ' . $iconhtml . '
                <div>
                    <small class="text-muted text-uppercase fw-bold">' . get_string('drops_header_managedrops', 'block_playerhud') . '</small>
                    <h2 class="m-0">' . format_string($item->name) . '</h2>
                </div>
            </div>
            
            <div class="ms-auto d-flex gap-2">
                <a href="' . $url_library . '" class="btn btn-outline-dark shadow-sm">
                    <i class="fa fa-book" aria-hidden="true"></i> ' . get_string('back_to_library', 'block_playerhud') . '
                </a>
                <a href="' . new moodle_url('/blocks/playerhud/edit_drop.php', ['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]) . '" 
                   class="btn btn-success shadow-sm">
                    <i class="fa fa-plus" aria-hidden="true"></i> ' . get_string('drops_btn_new', 'block_playerhud') . '
                </a>
            </div>
        </div>';

        $total_drops = count($drops);
        $summary_text = get_string('drops_summary', 'block_playerhud', $total_drops);
        $output .= '<div class="alert alert-light border shadow-sm d-flex align-items-center mb-3">
                        <i class="fa fa-map-signs text-info me-2 fa-lg" aria-hidden="true"></i>
                        <span class="fw-bold text-dark">' . $summary_text . '</span>
                    </div>';

        // --- TABELA ---
        $output .= html_writer::start_tag('form', [
            'action' => $baseurl->out(), 
            'method' => 'post', 
            'id' => 'bulk-action-form'
        ]);
        $output .= html_writer::input_hidden_params($baseurl);
        $output .= '<input type="hidden" name="action" value="bulk_delete">';
        $output .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

        if ($drops) {
            $output .= '<div class="card shadow-sm border-0"><div class="card-body p-0">';
            $output .= '<table class="table table-hover table-striped mb-0 align-middle">';
            
            $output .= '<thead class="bg-light"><tr>';
            $output .= '<th scope="col" style="width: 40px;" class="text-center">
                            <label class="visually-hidden" for="ph-select-all">' . get_string('selectall') . '</label>
                            <input type="checkbox" id="ph-select-all" class="form-check-input">
                        </th>';
            $output .= '<th scope="col" style="width: 50px;">N.</th>';
            $output .= '<th scope="col">' . $this->get_sort_link('name', get_string('drop_name_label', 'block_playerhud'), $sort, $dir, $baseurl) . '</th>';
            $output .= '<th scope="col" style="width: 130px;">' . $this->get_sort_link('maxusage', get_string('drop_max_qty', 'block_playerhud'), $sort, $dir, $baseurl) . '</th>';
            $output .= '<th scope="col" style="width: 150px;">' . $this->get_sort_link('respawntime', get_string('drop_interval', 'block_playerhud'), $sort, $dir, $baseurl) . '</th>';
            $output .= '<th scope="col" style="width: 180px;">' . get_string('gen_title', 'block_playerhud') . '</th>';
            $output .= '<th scope="col" class="text-end" style="width: 220px;">' . get_string('actions') . '</th>';
            $output .= '</tr></thead><tbody>';

            $strgen = get_string('gen_btn', 'block_playerhud');
            $strinf = get_string('infinite', 'block_playerhud');
            $strimm = get_string('drops_immediate', 'block_playerhud');
            
            $counter = 1;

            foreach ($drops as $drop) {
                $editurl = new moodle_url('/blocks/playerhud/edit_drop.php', ['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid, 'dropid' => $drop->id]);
                $deleteurl = new moodle_url($baseurl, ['action' => 'delete', 'dropid' => $drop->id, 'sesskey' => sesskey()]);
                
                $qtdhtml = ($drop->maxusage == 0) 
                    ? '<span class="badge bg-success"><i class="fa fa-infinity" aria-hidden="true"></i> ' . $strinf . '</span>'
                    : '<span class="badge bg-light text-dark border border-secondary">Max: ' . $drop->maxusage . '</span>';

                if ($drop->respawntime > 0) {
                    $timehtml = '<span class="badge bg-warning text-dark border border-warning" title="' . $drop->respawntime . 's"><i class="fa fa-clock-o" aria-hidden="true"></i> ' . format_time($drop->respawntime) . '</span>';
                } else {
                    $timehtml = '<span class="badge bg-light text-dark border shadow-sm"><i class="fa fa-bolt text-warning" aria-hidden="true"></i> ' . $strimm . '</span>';
                }

                $display_code = !empty($drop->code) ? $drop->code : $drop->id;
                
                $btncode = '<button type="button" class="btn btn-outline-dark btn-sm js-open-gen-modal w-100"
                                data-dropcode="' . $display_code . '" title="' . $strgentitle . '">
                                <i class="fa fa-cogs" aria-hidden="true"></i> ' . $strgen . '
                             </button>';

                $safeconfirm = s(get_string('drops_confirm_delete', 'block_playerhud'));
                
                $actions = html_writer::link($editurl, '<i class="fa fa-pencil" aria-hidden="true"></i> ' . get_string('edit'), ['class' => 'btn btn-sm btn-primary me-1 shadow-sm']);
                $actions .= html_writer::link($deleteurl, '<i class="fa fa-trash" aria-hidden="true"></i> ' . get_string('delete'), [
                    'class' => 'btn btn-sm btn-danger shadow-sm js-delete-btn',
                    'data-confirm-msg' => $safeconfirm
                ]);

                $drop_name_display = format_text($drop->name, FORMAT_HTML, ['context' => $coursecontext]);

                $output .= "<tr>
                    <td class='align-middle text-center'>
                        <label class='visually-hidden' for='chk-{$drop->id}'>" . get_string('select') . "</label>
                        <input type='checkbox' name='bulk_ids[]' value='{$drop->id}' id='chk-{$drop->id}' class='form-check-input ph-bulk-check'>
                    </td>
                    <td class='align-middle text-muted small'>{$counter}</td>
                    <td class='align-middle fw-bold'>{$drop_name_display}</td>
                    <td class='align-middle'>{$qtdhtml}</td>
                    <td class='align-middle'>{$timehtml}</td>
                    <td class='align-middle'>{$btncode}</td>
                    <td class='align-middle text-end'>{$actions}</td>
                </tr>";
                $counter++;
            }
            $output .= '</tbody></table></div></div>';
            
            $output .= '<div class="mt-3 mb-3">
                        <button type="button" class="btn btn-danger shadow-sm disabled" id="ph-btn-bulk-delete" disabled>
                            <i class="fa fa-trash" aria-hidden="true"></i> ' . get_string('delete_selected', 'block_playerhud') . '
                        </button>
                      </div>';

            $output .= html_writer::div(get_string('dropcode_help', 'block_playerhud'), 'alert alert-info mt-3');
        } else {
            $output .= $OUTPUT->notification(get_string('drops_empty', 'block_playerhud'), 'info');
        }

        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_div();

        // --- HTML DO MODAL GERADOR (Atualizado) ---
        $strgenstyle = get_string('gen_style', 'block_playerhud');
        $strgencard = get_string('gen_style_card', 'block_playerhud');
        $strgencarddesc = get_string('gen_style_card_desc', 'block_playerhud');
        $strgentext = get_string('gen_style_text', 'block_playerhud');
        $strgentextdesc = get_string('gen_style_text_desc', 'block_playerhud');
        $strgenimage = get_string('gen_style_image', 'block_playerhud');
        $strgenimagedesc = get_string('gen_style_image_desc', 'block_playerhud');
        $strgenlinklabel = get_string('gen_link_label', 'block_playerhud');
        $strgenlinkph = get_string('gen_link_placeholder', 'block_playerhud');
        $strgenlinkhelp = get_string('gen_link_help', 'block_playerhud');
        $strgenpreview = get_string('gen_preview', 'block_playerhud');
        $strgencodelabel = get_string('gen_code_label', 'block_playerhud');
        $strgencopy = get_string('gen_copy', 'block_playerhud');
        $strgencopied = get_string('gen_copied', 'block_playerhud');
        $strbtntxt = get_string('choice_text', 'block_playerhud'); 
        $strtake = get_string('take', 'block_playerhud'); 

        // Botão de Copiar usando data-action="copytoclipboard"
        $output .= '
        <div class="modal fade" id="codeGenModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 10500;">
          <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
              <div class="modal-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="modal-title m-0 fw-bold">' . $strgentitle . '</h5>
                <button type="button" class="btn-close btn-close-white js-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                  <div class="row">
                      <div class="col-md-7 border-end">
                          <form>
                              <div class="mb-3">
                                  <label class="fw-bold text-dark mb-3">' . $strgenstyle . '</label>
                                  <div class="form-check mb-2">
                                      <input type="radio" id="modeCard" name="codeMode" class="form-check-input js-mode-trigger" value="card" checked>
                                      <label class="form-check-label" for="modeCard"><strong>' . $strgencard . '</strong><br><small class="text-muted">' . $strgencarddesc . '</small></label>
                                  </div>
                                  <div class="form-check mb-2">
                                      <input type="radio" id="modeText" name="codeMode" class="form-check-input js-mode-trigger" value="text">
                                      <label class="form-check-label" for="modeText"><strong>' . $strgentext . '</strong><br><small class="text-muted">' . $strgentextdesc . '</small></label>
                                  </div>
                                  <div class="form-check">
                                      <input type="radio" id="modeImage" name="codeMode" class="form-check-input js-mode-trigger" value="image">
                                      <label class="form-check-label" for="modeImage"><strong>' . $strgenimage . '</strong><br><small class="text-muted">' . $strgenimagedesc . '</small></label>
                                  </div>
                              </div>

                              <div id="cardCustomOptions" class="mt-3 pt-3 border-top">
                                  <label class="form-label fw-bold small text-uppercase text-muted mb-2">' . get_string('visual_content', 'block_playerhud') . '</label>
                                  <div class="row">
                                      <div class="col-8">
                                          <label for="customBtnText" class="form-label small mb-1">' . $strbtntxt . '</label>
                                          <input type="text" class="form-control form-control-sm" id="customBtnText" placeholder="' . $strtake . '">
                                      </div>
                                      <div class="col-4">
                                          <label for="customBtnEmoji" class="form-label small mb-1">Emoji</label>
                                          <input type="text" class="form-control form-control-sm text-center" id="customBtnEmoji" placeholder="🖐" maxlength="4">
                                      </div>
                                  </div>
                                  <div class="form-text mt-1" style="font-size: 0.7rem;">Deixe vazio para usar o padrão.</div>
                              </div>

                              <div class="mb-3 mt-4" id="textInputGroup" style="display:none; background:#fff3cd; padding:10px; border-radius:5px; border:1px solid #ffeeba;">
                                  <label class="fw-bold text-dark">' . $strgenlinklabel . '</label>
                                  <input type="text" class="form-control" id="customText" placeholder="' . $strgenlinkph . '">
                                  <small class="text-muted">' . $strgenlinkhelp . '</small>
                              </div>
                          </form>
                      </div>

                      <div class="col-md-5 d-flex flex-column bg-light rounded-end">
                          <div class="text-center p-3 flex-grow-1 d-flex flex-column justify-content-center align-items-center">
                              <h6 class="text-muted text-uppercase mb-4" style="font-size:0.7rem; letter-spacing:1px;">' . $strgenpreview . '</h6>
                              <div id="previewContainer" class="w-100 d-flex justify-content-center align-items-center"></div>
                          </div>
                      </div>
                  </div>

                  <div class="row mt-3 pt-3 border-top">
                      <div class="col-12">
                         <label class="fw-bold text-success">' . $strgencodelabel . '</label>
                          <div class="input-group input-group-lg">
                              <input type="text" class="form-control font-monospace" id="finalCode" readonly style="background:#f0f0f0; color:#d63384; font-size: 0.95rem; font-weight:bold;">
                              
                              <button class="btn btn-primary fw-bold shadow-sm" type="button" 
                                      id="copyFinalCode"
                                      data-action="copytoclipboard" 
                                      data-clipboard-target="#finalCode">
                                  <i class="fa fa-copy" aria-hidden="true"></i> ' . $strgencopy . '
                              </button>
                          </div>
                      </div>
                  </div>
              </div>
            </div>
          </div>
        </div>';

        $jsconfig = [
            'item' => [
                'name' => format_string($item->name),
                'isImage' => $mediadata['is_image'],
                'url' => $mediadata['is_image'] ? $mediadata['url'] : '',
                'content' => $mediadata['is_image'] ? '' : $mediadata['content'],
                'xp' => "+{$item->xp} XP",
            ],
            'strings' => [
                'defaultText' => get_string('gen_link_placeholder', 'block_playerhud'),
                'takeBtn' => $strtake,
                'yours' => get_string('gen_yours', 'block_playerhud'),
                'confirm_title' => get_string('confirmation', 'admin'),
                'confirm_bulk' => get_string('confirm_bulk_delete', 'block_playerhud'),
                'delete_selected' => get_string('delete_selected', 'block_playerhud'),
                'delete_n_items' => get_string('delete_n_items', 'block_playerhud'),
                'yes' => get_string('yes'),
                'cancel' => get_string('cancel')
            ]
        ];

        $PAGE->requires->js_call_amd('block_playerhud/manage_drops', 'init', [$jsconfig]);

        $output .= $OUTPUT->footer();
        return $output;
    }

    public function handle_edit_form() {
        global $DB, $PAGE, $OUTPUT, $COURSE, $CFG;
        require_once($CFG->dirroot . '/blocks/playerhud/classes/form/edit_drop_form.php');
        $instanceid = required_param('instanceid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);
        $itemid     = required_param('itemid', PARAM_INT);
        $dropid     = optional_param('dropid', 0, PARAM_INT);
        require_login($courseid);
        $context = \context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);
        $url = new moodle_url('/blocks/playerhud/edit_drop.php', ['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]);
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('standard');
        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $mform = new \block_playerhud\form\edit_drop_form(null, ['itemname' => $item->name]);
        if ($dropid && !$mform->is_submitted()) {
            $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid]);
            $data = (array)$drop;
            $data['unlimited'] = ($drop->maxusage == 0) ? 1 : 0;
            $data['maxusage']  = ($drop->maxusage == 0) ? 1 : $drop->maxusage;
            $data['instanceid'] = $instanceid; 
            $data['courseid'] = $courseid;
            $mform->set_data($data);
        } else if (!$mform->is_submitted()) {
            $mform->set_data(['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]);
        }
        if ($mform->is_cancelled()) {
            redirect(new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $instanceid, 'id' => $courseid, 'itemid' => $itemid]));
        } else if ($data = $mform->get_data()) {
            $this->save_drop($data);
            redirect(
                new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $instanceid, 'id' => $courseid, 'itemid' => $itemid]),
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

    private function save_drop($data) {
        global $DB, $USER;
        $record = new \stdClass();
        $record->blockinstanceid = $data->instanceid; 
        $record->itemid          = $data->itemid;
        $record->name            = $data->name;
        $record->respawntime     = $data->respawntime;
        $record->timemodified    = time();
        $record->maxusage        = (!empty($data->unlimited)) ? 0 : max(1, (int)$data->maxusage);
        if (!empty($data->id)) {
            $record->id = $data->id;
            $DB->update_record('block_playerhud_drops', $record);
        } else {
            $record->timecreated = time();
            $record->code = strtoupper(substr(md5(time() . $USER->id . rand()), 0, 6)); 
            $DB->insert_record('block_playerhud_drops', $record);
        }
    }
}
