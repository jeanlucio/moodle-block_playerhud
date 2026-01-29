<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/playerhud/backup/moodle2/backup_playerhud_stepslib.php');

class backup_playerhud_block_task extends backup_block_task {

    protected function define_my_settings() {
        // Sem configurações especiais por enquanto.
    }

    protected function define_my_steps() {
        // Adiciona o passo que define a estrutura dos dados (XML).
        $this->add_step(new backup_playerhud_block_structure_step('playerhud_structure', 'playerhud.xml'));
    }

    public function get_fileareas() {
        // Avisa ao Moodle que temos arquivos na área 'item_image'.
        return array('item_image');
    }

    public function get_configdata_encoded_attributes() {
        return array(); // Retorna configurações codificadas se houver (padrão vazio).
    }

    static public function encode_content_links($content) {
        // Usado para converter links internos (ex: $a->href) no restore.
        // Por enquanto, retorna o conteúdo sem alterações para o teste.
        return $content;
    }
}
