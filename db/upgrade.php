<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_playerhud_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Adicionando o campo 'code' na tabela block_playerhud_drops
    if ($oldversion < 2026020301) { // Use a nova versão que definiremos no passo 3
        $table = new xmldb_table('block_playerhud_drops');
        $field = new xmldb_field('code', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'itemid');

        // Adiciona o campo se ele não existir
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Ponto de salvamento do upgrade
        upgrade_block_savepoint(true, 2026020301, 'playerhud');
    }

    return true;
}