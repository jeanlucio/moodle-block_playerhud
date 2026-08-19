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
     * Deferred req_itemid fixups for TYPE_SPECIFIC_TRADE quests: new quest ID → old trade ID.
     * Trades are restored after quests (later sibling under /block/playerhud), so the
     * 'playerhud_trade' mapping does not exist yet when the quest is processed.
     *
     * @var array<int,int>
     */
    private array $deferredtradequests = [];

    /**
     * Deferred course module fixups for TYPE_ACTIVITY quests: new quest ID → old cmid.
     * Course modules belong to later tasks in the restore plan (course-level blocks are
     * restored before sections/activities), so the 'course_module' mapping only exists once
     * the whole restore finishes. Resolved in after_restore().
     *
     * @var array<int,int>
     */
    private array $deferredactivityquests = [];

    /**
     * Deferred course module fixups for the deadline_extension item power's optional pinned
     * cmid in action_value. Same ordering constraint as $deferredactivityquests.
     *
     * @var array<int,int>
     */
    private array $deferreditemcmids = [];

    /**
     * Deferred required_class_id fixups for items: new item ID → old required_class_id CSV.
     * Classes are restored after items (later sibling under /block/playerhud), so the
     * 'playerhud_class' mapping does not exist yet when the item is processed.
     *
     * @var array<int,string>
     */
    private array $deferreditemclasses = [];

    /**
     * Deferred required_class_id fixups for quests, same reason and shape as
     * $deferreditemclasses.
     *
     * @var array<int,string>
     */
    private array $deferredquestclasses = [];

    /**
     * Deferred requirement fixups for TYPE_CHAPTER quests: new quest ID → old chapter ID.
     * Chapters are restored after quests (later sibling under /block/playerhud), so the
     * 'playerhud_chapter' mapping does not exist yet when the quest is processed.
     *
     * @var array<int,int>
     */
    private array $deferredquestchapters = [];

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
                'stack',
                '/block/playerhud/stacks/stack'
            );
            $paths[] = new restore_path_element(
                'stack_log',
                '/block/playerhud/stack_logs/stack_log'
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

        // The "deadline_extension" power may pin a specific course module in action_value
        // ({"cmid":N,...}). Course modules belong to later tasks in the restore plan, so that
        // old cmid cannot be remapped here yet. Default it to "any activity" (cmid=0) and
        // queue the real fixup for after_restore(), once every course module is in place.
        $oldcmid = 0;
        if ($data->action_type === 'deadline_extension' && !empty($data->action_value)) {
            $actionvalue = json_decode($data->action_value, true);
            if (is_array($actionvalue) && !empty($actionvalue['cmid'])) {
                $oldcmid = (int)$actionvalue['cmid'];
                $actionvalue['cmid'] = 0;
                $data->action_value = json_encode($actionvalue);
            }
        }

        // The required_class_id column is a CSV of block_playerhud_classes IDs, read by
        // utils::is_visible_for_class() — not a single FK column, so it is not covered by any
        // mapping table yet. Classes are restored after items, so blank it out here and remap
        // each ID in after_execute() once the 'playerhud_class' mapping is complete.
        $oldrequiredclassid = (string)($data->required_class_id ?? '');
        if ($oldrequiredclassid !== '') {
            $data->required_class_id = '';
        }

        $newitemid = $DB->insert_record('block_playerhud_items', $data);

        if ($oldcmid > 0) {
            $this->deferreditemcmids[$newitemid] = $oldcmid;
        }
        if ($oldrequiredclassid !== '') {
            $this->deferreditemclasses[$newitemid] = $oldrequiredclassid;
        }

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
     * Process item quantity balance rows (new-engine storage).
     *
     * @param array $data
     */
    public function process_stack($data) {
        global $DB;
        $data = (object)$data;

        $newuserid = $this->get_mappingid('user', $data->userid);
        if (!$newuserid) {
            return;
        }

        $newitemid = $this->get_mappingid('playerhud_item', $data->itemid);
        if (!$newitemid) {
            return;
        }

        $data->userid = $newuserid;
        $data->itemid = $newitemid;
        unset($data->id);

        // Avoid a duplicate row on the table's UNIQUE(userid, itemid) key if restore is run
        // multiple times or merged.
        $exists = $DB->record_exists('block_playerhud_stack', [
            'userid' => $newuserid,
            'itemid' => $newitemid,
        ]);
        if (!$exists) {
            $DB->insert_record('block_playerhud_stack', $data);
        }
    }

    /**
     * Process item quantity ledger entries (new-engine storage).
     *
     * @param array $data
     */
    public function process_stack_log($data) {
        global $DB;
        $data = (object)$data;
        $olddropid = $data->dropid;

        $newuserid = $this->get_mappingid('user', $data->userid);
        if (!$newuserid) {
            return;
        }

        $newitemid = $this->get_mappingid('playerhud_item', $data->itemid);
        if (!$newitemid) {
            return;
        }

        $newdropid = 0;
        if (!empty($olddropid)) {
            $newdropid = $this->get_mappingid('playerhud_drop', $olddropid) ?: 0;
        }

        $data->userid = $newuserid;
        $data->itemid = $newitemid;
        $data->dropid = $newdropid;
        unset($data->id);

        $DB->insert_record('block_playerhud_stack_log', $data);
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
        $oldreqitemid = $data->req_itemid;

        $data->blockinstanceid = $this->task->get_blockid();
        unset($data->id);

        // Remap reward item if present.
        if (!empty($data->reward_itemid)) {
            $newrewardid = $this->get_mappingid('playerhud_item', $data->reward_itemid);
            $data->reward_itemid = $newrewardid ?: 0;
        }

        // The req_itemid field is overloaded: it holds an item ID for TYPE_SPECIFIC_ITEM but a
        // trade ID for TYPE_SPECIFIC_TRADE. Trades are restored after quests, so the trade case
        // is resolved later in after_execute() once the 'playerhud_trade' mapping exists.
        if ((int)$data->type === \block_playerhud\quest::TYPE_SPECIFIC_TRADE) {
            $data->req_itemid = 0;
        } else if (!empty($data->req_itemid)) {
            $newreqid = $this->get_mappingid('playerhud_item', $data->req_itemid);
            $data->req_itemid = $newreqid ?: 0;
        }

        // TYPE_ACTIVITY stores a raw course module ID directly in requirement (not a foreign
        // key column, so it is not covered by any mapping table yet). Course modules belong to
        // later tasks in the restore plan, so blank it out here and resolve it in
        // after_restore() once every course module of the target course is in place.
        $oldactivitycmid = 0;
        if ((int)$data->type === \block_playerhud\quest::TYPE_ACTIVITY) {
            $oldactivitycmid = (int)$data->requirement;
            $data->requirement = '0';
        }

        // TYPE_CHAPTER stores a raw chapter ID directly in requirement, same overload pattern
        // as TYPE_ACTIVITY above. Chapters are restored after quests, so blank it out here and
        // resolve it in after_execute() once the 'playerhud_chapter' mapping is complete.
        $oldrequirementchapterid = 0;
        if ((int)$data->type === \block_playerhud\quest::TYPE_CHAPTER) {
            $oldrequirementchapterid = (int)$data->requirement;
            $data->requirement = '0';
        }

        // The required_class_id column is a CSV of block_playerhud_classes IDs — same overload
        // and ordering constraint as process_item()'s own required_class_id handling.
        $oldrequiredclassid = (string)($data->required_class_id ?? '');
        if ($oldrequiredclassid !== '') {
            $data->required_class_id = '';
        }

        $newid = $DB->insert_record('block_playerhud_quests', $data);
        $this->set_mapping('playerhud_quest', $oldid, $newid);

        if ((int)$data->type === \block_playerhud\quest::TYPE_SPECIFIC_TRADE && !empty($oldreqitemid)) {
            $this->deferredtradequests[$newid] = $oldreqitemid;
        }

        if ($oldactivitycmid > 0) {
            $this->deferredactivityquests[$newid] = $oldactivitycmid;
        }

        if ($oldrequirementchapterid > 0) {
            $this->deferredquestchapters[$newid] = $oldrequirementchapterid;
        }

        if ($oldrequiredclassid !== '') {
            $this->deferredquestclasses[$newid] = $oldrequiredclassid;
        }
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

        // The groupid column is overloaded: a positive value references a group directly, a
        // negative value references a grouping by its absolute value (see execute_trade()
        // and groups_get_all_groups() call sites). Group/grouping IDs are global to the site,
        // so an unmapped old ID surviving the restore could coincidentally match a real but
        // unrelated group/grouping in the target course. Groups/groupings are restored earlier
        // in restore_root_task (restore_groups_structure_step), so the mapping already exists
        // by the time this runs. No mapping (e.g. a backup taken without groups, or the group
        // since deleted) zeroes the restriction explicitly rather than persisting a foreign ID.
        $groupid = (int) $data->groupid;
        if ($groupid > 0) {
            $data->groupid = $this->get_mappingid('group', $groupid) ?: 0;
        } else if ($groupid < 0) {
            $newgroupingid = $this->get_mappingid('grouping', abs($groupid));
            $data->groupid = $newgroupingid ? -$newgroupingid : 0;
        }

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

        foreach ($this->deferredtradequests as $questid => $oldtradeid) {
            $newtradeid = $this->get_mappingid('playerhud_trade', $oldtradeid);
            if ($newtradeid) {
                $DB->set_field('block_playerhud_quests', 'req_itemid', $newtradeid, ['id' => $questid]);
            }
        }

        foreach ($this->deferredquestchapters as $questid => $oldchapterid) {
            $newchapterid = $this->get_mappingid('playerhud_chapter', $oldchapterid);
            if ($newchapterid) {
                $DB->set_field('block_playerhud_quests', 'requirement', (string) $newchapterid, ['id' => $questid]);
            }
        }

        foreach ($this->deferreditemclasses as $itemid => $oldcsv) {
            $DB->set_field(
                'block_playerhud_items',
                'required_class_id',
                $this->remap_class_id_csv($oldcsv),
                ['id' => $itemid]
            );
        }

        foreach ($this->deferredquestclasses as $questid => $oldcsv) {
            $DB->set_field(
                'block_playerhud_quests',
                'required_class_id',
                $this->remap_class_id_csv($oldcsv),
                ['id' => $questid]
            );
        }
    }

    /**
     * Remaps a CSV of old block_playerhud_classes IDs to their restored counterparts.
     *
     * A "0" entry is a visibility sentinel (see utils::is_visible_for_class()), not a real
     * class reference, and is passed through unchanged. Any other ID with no mapping (the
     * referenced class was not included in the backup) is dropped rather than left pointing at
     * a foreign or nonexistent class.
     *
     * @param string $csv Comma-separated old class IDs.
     * @return string Comma-separated new class IDs, possibly empty.
     */
    private function remap_class_id_csv(string $csv): string {
        $newids = [];
        foreach (explode(',', $csv) as $oldid) {
            $oldid = trim($oldid);
            if ($oldid === '') {
                continue;
            }
            if ($oldid === '0') {
                $newids[] = '0';
                continue;
            }
            $newid = $this->get_mappingid('playerhud_class', (int) $oldid);
            if ($newid) {
                $newids[] = (string) $newid;
            }
        }
        return implode(',', $newids);
    }

    /**
     * Resolve deferred course module fixups once the whole restore has finished.
     *
     * Course-level blocks are restored before the course's sections/activities (see
     * restore_plan_builder::build_course_plan()), so the 'course_module' mapping is not
     * populated yet during process_item()/process_quest()/after_execute(). This method runs
     * as one of the very last steps of the entire restore plan (via restore_final_task's
     * restore_execute_after_restore step), by which point every activity has been restored
     * and the mapping is complete.
     */
    protected function after_restore(): void {
        global $DB;

        foreach ($this->deferredactivityquests as $questid => $oldcmid) {
            $newcmid = $this->get_mappingid('course_module', $oldcmid);
            if ($newcmid) {
                $DB->set_field('block_playerhud_quests', 'requirement', (string)$newcmid, ['id' => $questid]);
            }
        }

        if (empty($this->deferreditemcmids)) {
            return;
        }

        // Batch-load every deferred item once instead of a get_record() per iteration.
        $items = $DB->get_records_list('block_playerhud_items', 'id', array_keys($this->deferreditemcmids));

        foreach ($this->deferreditemcmids as $itemid => $oldcmid) {
            $newcmid = $this->get_mappingid('course_module', $oldcmid);
            if (!$newcmid) {
                continue;
            }
            $item = $items[$itemid] ?? null;
            if (!$item || empty($item->action_value)) {
                continue;
            }
            $actionvalue = json_decode($item->action_value, true);
            if (!is_array($actionvalue)) {
                continue;
            }
            $actionvalue['cmid'] = $newcmid;
            $DB->set_field('block_playerhud_items', 'action_value', json_encode($actionvalue), ['id' => $itemid]);
        }
    }
}
