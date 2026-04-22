<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Structure step to restore playerhud block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_playerhud_block_structure_step extends restore_structure_step {
    /**
     * Deferred next_nodeid fixups: new choice ID → old node ID.
     * Choices are written with next_nodeid=0 and corrected in after_execute()
     * because the target node may not yet have been processed when the choice
     * is encountered in depth-first XML traversal.
     *
     * @var array<int,int>
     */
    private array $deferredchoicenodes = [];

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
        $paths[] = new restore_path_element('quest', '/block/playerhud/quests/quest');
        $paths[] = new restore_path_element('trade', '/block/playerhud/trades/trade');
        $paths[] = new restore_path_element(
            'trade_req',
            '/block/playerhud/trades/trade/trade_reqs/trade_req'
        );
        $paths[] = new restore_path_element(
            'trade_reward',
            '/block/playerhud/trades/trade/trade_rewards/trade_reward'
        );

        // RPG Classes (must be restored before chapters/choices so class ID mappings are ready).
        $paths[] = new restore_path_element('rpgclass', '/block/playerhud/classes/class');

        // Story Chapters, nodes, and choices.
        $paths[] = new restore_path_element('chapter', '/block/playerhud/chapters/chapter');
        $paths[] = new restore_path_element(
            'story_node',
            '/block/playerhud/chapters/chapter/story_nodes/story_node'
        );
        $paths[] = new restore_path_element(
            'choice',
            '/block/playerhud/chapters/chapter/story_nodes/story_node/choices/choice'
        );

        // User Data (verify if user data was included).
        if ($this->task->get_setting_value('users')) {
            $paths[] = new restore_path_element('player', '/block/playerhud/players/player');
            $paths[] = new restore_path_element(
                'inventory',
                '/block/playerhud/inventories/inventory'
            );
            $paths[] = new restore_path_element(
                'quest_log',
                '/block/playerhud/quests/quest/quest_logs/quest_log'
            );
            $paths[] = new restore_path_element(
                'trade_log',
                '/block/playerhud/trade_logs/trade_log'
            );
            $paths[] = new restore_path_element(
                'rpg_progress',
                '/block/playerhud/rpg_progresses/rpg_progress'
            );
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

        // Namespaced mapping to prevent ID collision with Moodle Core during restore.
        $this->set_mapping('playerhud_item', $oldid, $newitemid, true);
        $this->add_related_files('block_playerhud', 'item_image', 'playerhud_item', null, $oldid);
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

        // Remap item ID using the namespaced key.
        $newitemid = $this->get_mappingid('playerhud_item', $olditemid);
        if (!$newitemid) {
            return; // Skip if item parent not found.
        }

        $data->itemid = $newitemid;

        // We keep the original code from XML to maintain shortcode links valid.
        $newdropid = $DB->insert_record('block_playerhud_drops', $data);
        $this->set_mapping('playerhud_drop', $oldid, $newdropid);
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

        // Map User ID (Core mapping 'user' is safe).
        $newuserid = $this->get_mappingid('user', $olduserid);
        if (!$newuserid) {
            return; // User not included in restore.
        }

        $data->userid = $newuserid;
        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        // Avoid duplicate records if restore is run multiple times or merged.
        $exists = $DB->record_exists('block_playerhud_user', [
            'userid'          => $newuserid,
            'blockinstanceid' => $data->blockinstanceid,
        ]);
        if (!$exists) {
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
        $olddropid = $data->dropid;

        // 1. Map User.
        $newuserid = $this->get_mappingid('user', $olduserid);
        if (!$newuserid) {
            return;
        }

        // 2. Map Item using namespaced key.
        $newitemid = $this->get_mappingid('playerhud_item', $olditemid);
        if (!$newitemid) {
            return;
        }

        // 3. Map Drop using namespaced key (Optional).
        $newdropid = 0;
        if (!empty($olddropid)) {
            $newdropid = $this->get_mappingid('playerhud_drop', $olddropid);
            if (!$newdropid) {
                // If drop wasn't restored (e.g. deleted), we still keep the item but set drop to 0.
                $newdropid = 0;
            }
        }

        $data->userid = $newuserid;
        $data->itemid = $newitemid;
        $data->dropid = $newdropid;
        unset($data->id);

        $DB->insert_record('block_playerhud_inventory', $data);
    }

    /**
     * Process quests (game content).
     *
     * @param array $data
     */
    public function process_quest($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        // Remap reward item if present.
        if (!empty($data->reward_itemid)) {
            $newrewardid = $this->get_mappingid('playerhud_item', $data->reward_itemid);
            $data->reward_itemid = $newrewardid ?: 0;
        }

        // Remap req_itemid if present (used by TYPE_SPECIFIC_ITEM).
        if (!empty($data->req_itemid)) {
            $newreqid = $this->get_mappingid('playerhud_item', $data->req_itemid);
            $data->req_itemid = $newreqid ?: 0;
        }

        $newid = $DB->insert_record('block_playerhud_quests', $data);
        $this->set_mapping('playerhud_quest', $oldid, $newid);
    }

    /**
     * Process quest logs (user history).
     *
     * @param array $data
     */
    public function process_quest_log($data) {
        global $DB;
        $data = (object)$data;

        $newuserid = $this->get_mappingid('user', $data->userid);
        $newquestid = $this->get_mappingid('playerhud_quest', $data->questid);

        if ($newuserid && $newquestid) {
            $data->userid = $newuserid;
            $data->questid = $newquestid;
            unset($data->id);
            $DB->insert_record('block_playerhud_quest_log', $data);
        }
    }

    /**
     * Process trades.
     *
     * @param array $data
     */
    public function process_trade($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        $newid = $DB->insert_record('block_playerhud_trades', $data);
        $this->set_mapping('playerhud_trade', $oldid, $newid);
    }

    /**
     * Process trade requirements.
     *
     * @param array $data
     */
    public function process_trade_req($data) {
        global $DB;
        $data = (object)$data;

        $newtradeid = $this->get_mappingid('playerhud_trade', $data->tradeid);
        $newitemid = $this->get_mappingid('playerhud_item', $data->itemid);

        if ($newtradeid && $newitemid) {
            $data->tradeid = $newtradeid;
            $data->itemid = $newitemid;
            unset($data->id);
            $DB->insert_record('block_playerhud_trade_reqs', $data);
        }
    }

    /**
     * Process trade rewards.
     *
     * @param array $data
     */
    public function process_trade_reward($data) {
        global $DB;
        $data = (object)$data;

        $newtradeid = $this->get_mappingid('playerhud_trade', $data->tradeid);
        $newitemid = $this->get_mappingid('playerhud_item', $data->itemid);

        if ($newtradeid && $newitemid) {
            $data->tradeid = $newtradeid;
            $data->itemid = $newitemid;
            unset($data->id);
            $DB->insert_record('block_playerhud_trade_rewards', $data);
        }
    }

    /**
     * Process trade logs (User history).
     *
     * @param array $data
     */
    public function process_trade_log($data) {
        global $DB;
        $data = (object)$data;

        $newuserid = $this->get_mappingid('user', $data->userid);
        $newtradeid = $this->get_mappingid('playerhud_trade', $data->tradeid);

        if ($newuserid && $newtradeid) {
            $data->userid = $newuserid;
            $data->tradeid = $newtradeid;
            unset($data->id);
            $DB->insert_record('block_playerhud_trade_log', $data);
        }
    }

    /**
     * Process RPG Classes.
     * Must run before chapters/choices so that class ID mappings are available
     * when remapping req_class_id and set_class_id in choices.
     *
     * @param array $data
     */
    public function process_rpgclass($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        $newid = $DB->insert_record('block_playerhud_classes', $data);

        // Pass true so related files (portrait images tiers 1-5) can be restored.
        $this->set_mapping('playerhud_class', $oldid, $newid, true);
        for ($i = 1; $i <= 5; $i++) {
            $this->add_related_files('block_playerhud', 'class_image_' . $i, 'playerhud_class', null, $oldid);
        }
    }

    /**
     * Process Story Chapters.
     *
     * @param array $data
     */
    public function process_chapter($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        $newid = $DB->insert_record('block_playerhud_chapters', $data);
        $this->set_mapping('playerhud_chapter', $oldid, $newid);
    }

    /**
     * Process Story Nodes.
     *
     * @param array $data
     */
    public function process_story_node($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $newchapterid = $this->get_mappingid('playerhud_chapter', $data->chapterid);
        if (!$newchapterid) {
            return; // Skip orphaned node — chapter restore failed.
        }

        $data->chapterid = $newchapterid;
        unset($data->id);

        $newid = $DB->insert_record('block_playerhud_story_nodes', $data);
        $this->set_mapping('playerhud_node', $oldid, $newid);
    }

    /**
     * Process Choices between Story Nodes.
     *
     * next_nodeid may reference a node that has not yet been restored
     * (sibling node appearing later in the XML). Those are written as 0
     * and corrected by after_execute() once all nodes are mapped.
     *
     * @param array $data
     */
    public function process_choice($data) {
        global $DB;
        $data = (object)$data;

        $newnodeid = $this->get_mappingid('playerhud_node', $data->nodeid);
        if (!$newnodeid) {
            return; // Parent node was not restored; skip.
        }

        $data->nodeid = $newnodeid;

        // Save old next_nodeid for deferred fixup — it may reference an unprocessed node.
        $oldnextnodeid = (int)$data->next_nodeid;
        $data->next_nodeid = 0;

        // Remap class references (class IDs are available since classes are restored first).
        if (!empty($data->req_class_id)) {
            $data->req_class_id = $this->get_mappingid('playerhud_class', $data->req_class_id) ?: 0;
        }
        if (!empty($data->set_class_id)) {
            $data->set_class_id = $this->get_mappingid('playerhud_class', $data->set_class_id) ?: 0;
        }

        // Remap cost item reference (items are restored before chapters).
        if (!empty($data->cost_itemid)) {
            $data->cost_itemid = $this->get_mappingid('playerhud_item', $data->cost_itemid) ?: 0;
        }

        unset($data->id);

        $newid = $DB->insert_record('block_playerhud_choices', $data);

        if ($oldnextnodeid > 0) {
            $this->deferredchoicenodes[$newid] = $oldnextnodeid;
        }
    }

    /**
     * Process RPG progress records (karma, class, story positions) per user.
     * current_nodes and completed_chapters are JSON fields containing chapter/node IDs
     * that cannot be safely remapped without full JSON parsing. They are reset to null
     * so affected users simply restart story progress; karma and class are preserved.
     *
     * @param array $data
     */
    public function process_rpg_progress($data) {
        global $DB;
        $data = (object)$data;
        $olduserid = $data->userid;

        $newuserid = $this->get_mappingid('user', $olduserid);
        if (!$newuserid) {
            return;
        }

        $data->userid = $newuserid;
        $data->blockinstanceid = $this->task->get_blockid();

        if (!empty($data->classid)) {
            $data->classid = $this->get_mappingid('playerhud_class', $data->classid) ?: 0;
        }

        // Reset story-position JSON since embedded node/chapter IDs cannot be remapped safely.
        $data->current_nodes      = null;
        $data->completed_chapters = null;

        unset($data->id);

        $exists = $DB->record_exists('block_playerhud_rpg_progress', [
            'userid'          => $newuserid,
            'blockinstanceid' => $data->blockinstanceid,
        ]);
        if (!$exists) {
            $DB->insert_record('block_playerhud_rpg_progress', $data);
        }
    }

    /**
     * Resolve deferred next_nodeid values in choices after all nodes are mapped.
     * Called automatically by Moodle after all process_* methods have finished.
     */
    protected function after_execute(): void {
        global $DB;
        foreach ($this->deferredchoicenodes as $choiceid => $oldnodeid) {
            $newnodeid = $this->get_mappingid('playerhud_node', $oldnodeid);
            if ($newnodeid) {
                $DB->set_field('block_playerhud_choices', 'next_nodeid', $newnodeid, ['id' => $choiceid]);
            }
        }
    }
}
