<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete structure for backup of playerhud block.
 */
class backup_playerhud_block_structure_step extends backup_block_structure_step { // <--- VOLTAR PARA ESTA CLASSE

    protected function define_structure() {
        // 1. Define o elemento raiz.
        $playerhud = new backup_nested_element('playerhud', array('id'), null);

        // 2. Define a estrutura dos ITENS.
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', array('id'), array(
            'name', 'description', 'image', 'xp', 'enabled', 
            'maxusage', 'respawntime', 'tradable', 'secret', 
            'required_class_id', 'timecreated', 'timemodified'
        ));

        // 3. Hierarquia.
        $playerhud->add_child($items);
        $items->add_child($item);

        // 4. Fontes de dados.
        $item->set_source_table('block_playerhud_items', array('blockinstanceid' => backup::VAR_BLOCKID));

        // 5. Arquivos.
        $item->annotate_files('block_playerhud', 'item_image', 'id');

        // Retorna usando o método especial que só existe na backup_block_structure_step
        return $this->prepare_block_structure($playerhud);
    }
}
