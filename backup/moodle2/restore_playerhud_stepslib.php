<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one playerhud block.
 */
class restore_playerhud_block_structure_step extends restore_structure_step { // <--- CORREÇÃO AQUI

    protected function define_structure() {
        $paths = array();

        // O caminho deve bater com o XML gerado no backup.
        // Se no backup for /block/playerhud/items/item, aqui deve ser igual.
        $paths[] = new restore_path_element('item', '/block/playerhud/items/item');

        return $paths;
    }

    /**
     * Processa cada <item> encontrado no XML.
     */
    public function process_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Atribuímos o NOVO ID da instância do bloco.
        $data->blockinstanceid = $this->task->get_blockid();
        
        // Remove o ID antigo.
        unset($data->id);

        // Insere no banco.
        $newitemid = $DB->insert_record('block_playerhud_items', $data);

        // Mapeamento para arquivos (imagens).
        $this->set_mapping('item', $oldid, $newitemid, true);
        
        // Restaura as imagens.
        $this->add_related_files('block_playerhud', 'item_image', 'item', null, $oldid);
    }
}
