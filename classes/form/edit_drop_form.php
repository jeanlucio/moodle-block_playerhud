<?php
namespace block_playerhud\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class edit_drop_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        // --- Header e Nome ---
        $mform->addElement('header', 'general', get_string('drop_config_header', 'block_playerhud', $this->_customdata['itemname']));

        // CORREÇÃO: O texto de exemplo agora é passado como atributo 'placeholder' (4º argumento)
        $mform->addElement('text', 'name', get_string('drop_name_label', 'block_playerhud'), [
            'placeholder' => get_string('drop_name_default', 'block_playerhud')
        ]);
        
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        
        // --- Regras de Coleta ---
        $mform->addElement('header', 'rules', get_string('drop_rules_header', 'block_playerhud'));

        // Ilimitado?
        $mform->addElement('advcheckbox', 'unlimited', get_string('drop_supplies_label', 'block_playerhud'), get_string('drop_unlimited_label', 'block_playerhud'), [], [0, 1]);

        // Quantidade Máxima (Se não for ilimitado)
        $mform->addElement('text', 'maxusage', get_string('drop_max_qty', 'block_playerhud'), ['type' => 'number', 'min' => '1']);
        $mform->setType('maxusage', PARAM_INT);
        $mform->setDefault('maxusage', 1);
        $mform->hideIf('maxusage', 'unlimited', 'checked');

        // Tempo de Respawn (Cooldown)
        // Nota: O duration retorna segundos. O banco deve guardar segundos.
        $mform->addElement('duration', 'respawntime', get_string('drop_interval', 'block_playerhud'), ['optional' => false, 'defaultunit' => 60]);
        $mform->setDefault('respawntime', 0);
        $mform->addHelpButton('respawntime', 'respawntime', 'block_playerhud');

        // --- Campos Ocultos ---
        $mform->addElement('hidden', 'id'); // ID do Drop (se edição)
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid'); // Necessário para redirecionamento
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('drop_save_btn', 'block_playerhud'));
    }
}
