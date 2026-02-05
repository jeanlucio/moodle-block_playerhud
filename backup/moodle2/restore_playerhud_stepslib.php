<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore playerhud block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_playerhud_block_structure_step extends restore_structure_step {

    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('item', '/block/playerhud/items/item');
        $paths[] = new restore_path_element('drop', '/block/playerhud/drops/drop');

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

        // Mapping for files and subsequent drops.
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

        // Remap item ID to the newly restored item.
        $newitemid = $this->get_mappingid('item', $olditemid);
        
        // Safety check: if item wasn't restored, we cannot create the drop.
        if (!$newitemid) {
            return;
        }
        
        $data->itemid = $newitemid;

        $DB->insert_record('block_playerhud_drops', $data);
        
        // No mapping needed for drops unless other tables refer to it (e.g. logs).
    }
}
