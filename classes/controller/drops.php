<?php
namespace block_playerhud\controller;

use moodle_url;
use html_writer;
use html_table;

defined('MOODLE_INTERNAL') || die();

/**
 * Controller class for Drop Management.
 * Handles both listing (manage) and editing (add/update) actions.
 */
class drops {

    /**
     * Exibe a página de listagem e gerencia exclusões.
     * Entry point para: manage_drops.php
     */
    public function view_manage_page() {
        global $DB, $PAGE, $OUTPUT, $COURSE;

        $instanceid = required_param('instanceid', PARAM_INT);
        $courseid   = required_param('id', PARAM_INT);
        $itemid     = required_param('itemid', PARAM_INT);
        $action     = optional_param('action', '', PARAM_ALPHA);
        $dropid     = optional_param('dropid', 0, PARAM_INT);

        require_login($courseid);
        $context = \context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);

        $url = new moodle_url('/blocks/playerhud/manage_drops.php', ['instanceid' => $instanceid, 'id' => $courseid, 'itemid' => $itemid]);
        $PAGE->set_url($url);
        $PAGE->set_context($context);
        $PAGE->set_heading($COURSE->fullname);
        $PAGE->set_pagelayout('standard');

        // Handle Delete Action
        if ($action === 'delete' && $dropid && confirm_sesskey()) {
            $DB->delete_records('block_playerhud_drops', ['id' => $dropid]);
            redirect($url, get_string('deleted', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
        }

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $itemid]);

        $output = $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('manage_drops_title', 'block_playerhud', format_string($item->name)));

        // Buttons
        $output .= html_writer::start_div('d-flex justify-content-between mb-3');
        $output .= html_writer::link(
            new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'items']),
            '← ' . get_string('back', 'block_playerhud'),
            ['class' => 'btn btn-secondary']
        );
        $output .= html_writer::link(
            new moodle_url('/blocks/playerhud/edit_drop.php', ['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]),
            get_string('drops_btn_new', 'block_playerhud'),
            ['class' => 'btn btn-primary']
        );
        $output .= html_writer::end_div();

        // Render Table
        if ($drops) {
            $table = new html_table();
            $table->head = [
                get_string('drop_name_label', 'block_playerhud'),
                get_string('dropcode', 'block_playerhud'),
                get_string('drop_max_qty', 'block_playerhud'),
                get_string('drop_interval', 'block_playerhud'),
                get_string('actions')
            ];

            foreach ($drops as $drop) {
                $editurl = new moodle_url('/blocks/playerhud/edit_drop.php', ['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid, 'dropid' => $drop->id]);
                $deleteurl = new moodle_url($url, ['action' => 'delete', 'dropid' => $drop->id, 'sesskey' => sesskey()]);
                
                $shortcode = '<code>[PLAYERHUD_DROP id=' . $drop->id . ']</code>';
                $qty = ($drop->maxusage == 0) ? get_string('drop_unlimited_label', 'block_playerhud') : $drop->maxusage;
                $cooldown = ($drop->respawntime == 0) ? '-' : format_time($drop->respawntime);

                $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-info me-1']);
                $actions .= html_writer::link($deleteurl, get_string('delete'), ['class' => 'btn btn-sm btn-danger']);

                $table->data[] = [format_string($drop->name), $shortcode, $qty, $cooldown, $actions];
            }
            $output .= html_writer::table($table);
            $output .= html_writer::div(get_string('dropcode_help', 'block_playerhud'), 'alert alert-info mt-3');
        } else {
            $output .= $OUTPUT->notification(get_string('drops_empty', 'block_playerhud'), 'info');
        }

        $output .= $OUTPUT->footer();
        return $output;
    }

/**
     * Gerencia o formulário de criação/edição.
     */
    public function handle_edit_form() {
        global $DB, $PAGE, $OUTPUT, $COURSE, $CFG;
        
        require_once($CFG->dirroot . '/blocks/playerhud/classes/form/edit_drop_form.php');

        // Esta linha que estava dando erro agora vai funcionar porque o form enviará 'instanceid'
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

        // Load data if editing
        if ($dropid && !$mform->is_submitted()) {
            $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid]);
            $data = (array)$drop;
            $data['unlimited'] = ($drop->maxusage == 0) ? 1 : 0;
            $data['maxusage']  = ($drop->maxusage == 0) ? 1 : $drop->maxusage;
            
            // CORREÇÃO NO LOAD: Mapear coluna do banco para campo do form
            $data['instanceid'] = $instanceid; 
            $data['courseid'] = $courseid;
            
            $mform->set_data($data);
        } else if (!$mform->is_submitted()) {
            // CORREÇÃO NO LOAD (NOVO): Usar a chave 'instanceid'
            $mform->set_data(['instanceid' => $instanceid, 'courseid' => $courseid, 'itemid' => $itemid]);
        }

        // Process Form
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

    /**
     * Helper privado para salvar os dados no banco.
     */
    private function save_drop($data) {
        global $DB, $USER;
        $record = new \stdClass();
        
        // CORREÇÃO NO SAVE: Pegar do campo 'instanceid' do form e jogar na coluna 'blockinstanceid' do banco
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
