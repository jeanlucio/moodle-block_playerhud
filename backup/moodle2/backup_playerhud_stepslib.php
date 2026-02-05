<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete structure for backup of playerhud block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_playerhud_block_structure_step extends backup_block_structure_step {

    protected function define_structure() {
        // 1. Root element.
        $playerhud = new backup_nested_element('playerhud', ['id'], null);

        // 2. Items structure.
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], [
            'name', 'description', 'image', 'xp', 'enabled',
            'maxusage', 'respawntime', 'tradable', 'secret',
            'required_class_id', 'timecreated', 'timemodified'
        ]);

        // 3. Drops structure (New).
        $drops = new backup_nested_element('drops');
        $drop = new backup_nested_element('drop', ['id'], [
            'itemid', 'code', 'name', 'maxusage', 'respawntime',
            'timecreated', 'timemodified'
        ]);

        // 4. Hierarchy.
        $playerhud->add_child($items);
        $items->add_child($item);

        $playerhud->add_child($drops);
        $drops->add_child($drop);

        // 5. Data sources.
        $item->set_source_table('block_playerhud_items', ['blockinstanceid' => backup::VAR_BLOCKID]);
        
        // Define drop source. Note: We use blockinstanceid to filter drops belonging to this block.
        $drop->set_source_table('block_playerhud_drops', ['blockinstanceid' => backup::VAR_BLOCKID]);

        // 6. File annotations.
        $item->annotate_files('block_playerhud', 'item_image', 'id');

        return $this->prepare_block_structure($playerhud);
    }
}
