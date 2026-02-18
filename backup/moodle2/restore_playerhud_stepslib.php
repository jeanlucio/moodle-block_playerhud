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
 * Structure step to restore playerhud block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_playerhud_block_structure_step extends restore_structure_step {
    /**
     * Define the structure of the restore step.
     *
     * @return array Array of restore_path_element.
     */
    protected function define_structure() {
        $paths = [];
        // Content.
        $paths[] = new restore_path_element('item', '/block/playerhud/items/item');
        $paths[] = new restore_path_element('drop', '/block/playerhud/drops/drop');
        // User Data (Verify if user data was included).
        if ($this->task->get_setting_value('users')) {
            $paths[] = new restore_path_element('player', '/block/playerhud/players/player');
            $paths[] = new restore_path_element('inventory', '/block/playerhud/inventories/inventory');
        }

        return $paths;
    }

    /**
     * Process items.
     *
     * @param array $data
     */
    public function process_item($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        $newitemid = $DB->insert_record('block_playerhud_items', $data);
        // Mapping for files and subsequent drops/inventory.
        $this->set_mapping('item', $oldid, $newitemid, true);
        $this->add_related_files('block_playerhud', 'item_image', 'item', null, $oldid);
    }

    /**
     * Process drops.
     *
     * @param array $data
     */
    public function process_drop($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $olditemid = $data->itemid;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        // Remap item ID.
        $newitemid = $this->get_mappingid('item', $olditemid);
        if (!$newitemid) {
            return; // Skip if item parent not found.
        }
        $data->itemid = $newitemid;
        // Create new unique code to avoid collisions if needed.
        // but restoring same code is usually preferred for hardcoded links.
        // We keep the original code from XML.

        $newdropid = $DB->insert_record('block_playerhud_drops', $data);
        $this->set_mapping('drop', $oldid, $newdropid);
    }

    /**
     * Process player profile (XP, Level).
     *
     * @param array $data
     */
    public function process_player($data) {
        global $DB;
        $data = (object)$data;
        $olduserid = $data->userid;

        // Map User ID.
        $newuserid = $this->get_mappingid('user', $olduserid);
        if (!$newuserid) {
            return; // User not included in restore.
        }

        $data->userid = $newuserid;
        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        // Avoid duplicate records if restore is run multiple times or merged.
        if (!$DB->record_exists('block_playerhud_user', ['userid' => $newuserid, 'blockinstanceid' => $data->blockinstanceid])) {
            $DB->insert_record('block_playerhud_user', $data);
        }
    }

    /**
     * Process inventory (Collected Items).
     *
     * @param array $data
     */
    public function process_inventory($data) {
        global $DB;
        $data = (object)$data;
        $olduserid = $data->userid;
        $olditemid = $data->itemid;
        $olddropid = $data->dropid; // Can be 0 or ID.

        // 1. Map User.
        $newuserid = $this->get_mappingid('user', $olduserid);
        if (!$newuserid) {
            return;
        }

        // 2. Map Item.
        $newitemid = $this->get_mappingid('item', $olditemid);
        if (!$newitemid) {
            return;
        }

        // 3. Map Drop (Optional).
        $newdropid = 0;
        if (!empty($olddropid)) {
            $newdropid = $this->get_mappingid('drop', $olddropid);
            if (!$newdropid) {
                // If drop wasn't restored (e.g. deleted), we still keep the item but set drop to 0.
                $newdropid = 0;
            }
        }

        $data->userid = $newuserid;
        $data->itemid = $newitemid;
        $data->dropid = $newdropid;
        unset($data->id);

        // Insert inventory record.
        $DB->insert_record('block_playerhud_inventory', $data);
    }
}
