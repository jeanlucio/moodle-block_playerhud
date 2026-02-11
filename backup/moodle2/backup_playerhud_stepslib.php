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
 * Define the complete structure for backup of playerhud block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_playerhud_block_structure_step extends backup_block_structure_step {
    /**
     * Define the structure of the block.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // 1. Root element.
        $playerhud = new backup_nested_element('playerhud', ['id'], null);

        // 2. Items structure.
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], [
            'name', 'description', 'image', 'xp', 'enabled',
            'maxusage', 'respawntime', 'tradable', 'secret',
            'required_class_id', 'timecreated', 'timemodified',
        ]);

        // 3. Drops structure (New).
        $drops = new backup_nested_element('drops');
        $drop = new backup_nested_element('drop', ['id'], [
            'itemid', 'code', 'name', 'maxusage', 'respawntime',
            'timecreated', 'timemodified',
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
