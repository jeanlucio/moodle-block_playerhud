<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/playerhud/backup/moodle2/restore_playerhud_stepslib.php');

class restore_playerhud_block_task extends restore_block_task {

    protected function define_my_settings() {
        // Sem configurações especiais.
    }

    protected function define_my_steps() {
        // Adiciona o passo que lê o XML e grava no banco.
        $this->add_step(new restore_playerhud_block_structure_step('playerhud_structure', 'playerhud.xml'));
    }

    public function get_fileareas() {
        return array('item_image');
    }

    /**
     * Este método é obrigatório. Define quais atributos do configdata
     * foram codificados no backup (ex: links) e precisam de tratamento.
     * Retornamos array vazio pois não estamos codificando nada no configdata por enquanto.
     */
    public function get_configdata_encoded_attributes() {
        return array();
    }

    static public function define_decode_contents() {
        return array();
    }

    static public function define_decode_rules() {
        return array();
    }
}
