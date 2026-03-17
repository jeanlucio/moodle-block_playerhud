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

        // 2. Game Content Structure.
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], [
            'name', 'description', 'image', 'xp', 'enabled',
            'maxusage', 'respawntime', 'tradable', 'secret',
            'required_class_id', 'timecreated', 'timemodified',
        ]);

        $drops = new backup_nested_element('drops');
        $drop = new backup_nested_element('drop', ['id'], [
            'itemid', 'code', 'name', 'maxusage', 'respawntime',
            'timecreated', 'timemodified',
        ]);

        // Trades Structure (New).
        $trades = new backup_nested_element('trades');
        $trade = new backup_nested_element('trade', ['id'], [
            'name', 'groupid', 'centralized', 'onetime', 'timecreated',
        ]);

        $tradereqs = new backup_nested_element('trade_reqs');
        $tradereq = new backup_nested_element('trade_req', ['id'], ['tradeid', 'itemid', 'qty']);

        $traderewards = new backup_nested_element('trade_rewards');
        $tradereward = new backup_nested_element('trade_reward', ['id'], ['tradeid', 'itemid', 'qty']);

        // 3. User Data Structure.
        $players = new backup_nested_element('players');
        $player = new backup_nested_element('player', ['id'], [
            'userid', 'currentxp', 'enable_gamification',
            'ranking_visibility', 'last_inventory_view',
            'last_shop_view', 'timecreated', 'timemodified',
        ]);

        $inventories = new backup_nested_element('inventories');
        $inventory = new backup_nested_element('inventory', ['id'], [
            'userid', 'itemid', 'dropid', 'source', 'timecreated',
        ]);

        $tradelogs = new backup_nested_element('trade_logs');
        $tradelog = new backup_nested_element('trade_log', ['id'], ['userid', 'tradeid', 'timecreated']);

        // 4. Hierarchy.
        $playerhud->add_child($items);
        $items->add_child($item);

        $playerhud->add_child($drops);
        $drops->add_child($drop);

        $playerhud->add_child($trades);
        $trades->add_child($trade);
        $trade->add_child($tradereqs);
        $tradereqs->add_child($tradereq);
        $trade->add_child($traderewards);
        $traderewards->add_child($tradereward);

        $playerhud->add_child($players);
        $players->add_child($player);

        $playerhud->add_child($inventories);
        $inventories->add_child($inventory);

        $playerhud->add_child($tradelogs);
        $tradelogs->add_child($tradelog);

        // 5. Data Sources.
        $item->set_source_table('block_playerhud_items', ['blockinstanceid' => backup::VAR_BLOCKID]);
        $drop->set_source_table('block_playerhud_drops', ['blockinstanceid' => backup::VAR_BLOCKID]);

        $trade->set_source_table('block_playerhud_trades', ['blockinstanceid' => backup::VAR_BLOCKID]);
        $tradereq->set_source_table('block_playerhud_trade_reqs', ['tradeid' => backup::VAR_PARENTID]);
        $tradereward->set_source_table('block_playerhud_trade_rewards', ['tradeid' => backup::VAR_PARENTID]);

        if ($this->task->get_setting_value('users')) {
            $player->set_source_table('block_playerhud_user', ['blockinstanceid' => backup::VAR_BLOCKID]);

            $sqlinv = "SELECT inv.* FROM {block_playerhud_inventory} inv
                         JOIN {block_playerhud_items} i ON inv.itemid = i.id
                        WHERE i.blockinstanceid = :blockid";
            $inventory->set_source_sql($sqlinv, ['blockid' => backup::VAR_BLOCKID]);

            $sqltradelog = "SELECT tl.* FROM {block_playerhud_trade_log} tl
                              JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                             WHERE t.blockinstanceid = :blockid";
            $tradelog->set_source_sql($sqltradelog, ['blockid' => backup::VAR_BLOCKID]);

            // Annotate User IDs for core Moodle GDPR/Rollback systems.
            $player->annotate_ids('user', 'userid');
            $inventory->annotate_ids('user', 'userid');
            $tradelog->annotate_ids('user', 'userid');
        }

        // 6. File annotations.
        $item->annotate_files('block_playerhud', 'item_image', 'id');

        return $this->prepare_block_structure($playerhud);
    }
}
