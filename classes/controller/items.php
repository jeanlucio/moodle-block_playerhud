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

namespace block_playerhud\controller;

use context_block;

/**
 * Controller for item deletion, including cascade to orphaned trades.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items {
    /**
     * Returns trades that would have 0 requirements OR 0 rewards after
     * all given item IDs are removed from the instance.
     *
     * A trade is "orphaned" when removing the items leaves it with no
     * requirements side OR no rewards side — making it impossible to execute.
     * Trades that still have other items on both sides are NOT returned.
     *
     * @param int $instanceid Block instance ID.
     * @param int[] $itemids Item IDs being deleted.
     * @return \stdClass[] Orphaned trade records (id, name) keyed by trade ID.
     */
    public static function find_orphaned_trades(int $instanceid, array $itemids): array {
        global $DB;

        if (empty($itemids)) {
            return [];
        }

        [$itsql, $itparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'di');
        [$itnot, $itnotparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'dn', false);

        $tradeswithzeroreqs = $DB->get_records_sql(
            "SELECT t.id, t.name
               FROM {block_playerhud_trades} t
              WHERE t.blockinstanceid = :iid
                AND EXISTS (
                    SELECT 1 FROM {block_playerhud_trade_reqs} r
                     WHERE r.tradeid = t.id AND r.itemid $itsql
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {block_playerhud_trade_reqs} r2
                     WHERE r2.tradeid = t.id AND r2.itemid $itnot
                )",
            array_merge(['iid' => $instanceid], $itparams, $itnotparams)
        );

        [$itsql2, $itparams2] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'di2');
        [$itnot2, $itnotparams2] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'dn2', false);

        $tradeswithzerorew = $DB->get_records_sql(
            "SELECT t.id, t.name
               FROM {block_playerhud_trades} t
              WHERE t.blockinstanceid = :iid
                AND EXISTS (
                    SELECT 1 FROM {block_playerhud_trade_rewards} r
                     WHERE r.tradeid = t.id AND r.itemid $itsql2
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {block_playerhud_trade_rewards} r2
                     WHERE r2.tradeid = t.id AND r2.itemid $itnot2
                )",
            array_merge(['iid' => $instanceid], $itparams2, $itnotparams2)
        );

        return $tradeswithzeroreqs + $tradeswithzerorew;
    }

    /**
     * Deletes an item record, its inventory/drop dependencies, and any orphaned
     * trades in a single delegated transaction.
     *
     * Caller is responsible for capability checks and for finding $tradeids
     * via find_orphaned_trades() before calling this method.
     *
     * @param \stdClass $item Item record (must include id and xp).
     * @param int $instanceid Block instance ID.
     * @param context_block $context Block context (for file deletion).
     * @param int[] $tradeids Orphaned trade IDs to cascade-delete.
     */
    public static function delete_item(\stdClass $item, int $instanceid, context_block $context, array $tradeids = []): void {
        global $DB;

        $itemid = $item->id;
        $transaction = $DB->start_delegated_transaction();

        self::delete_orphaned_trades($tradeids);

        // Remove XP from students holding this item.
        $holders = $DB->get_records_sql(
            "SELECT userid, COUNT(id) as qtd FROM {block_playerhud_inventory} WHERE itemid = ? GROUP BY userid",
            [$itemid]
        );
        if ($holders) {
            self::remove_xp_from_holders($holders, $instanceid, $item->xp);
        }

        $DB->delete_records('block_playerhud_inventory', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_drops', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_reqs', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['itemid' => $itemid]);

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $itemid);
        $DB->delete_records('block_playerhud_items', ['id' => $itemid]);

        $transaction->allow_commit();
    }

    /**
     * Bulk-deletes items, their inventory/drop dependencies, and any orphaned
     * trades in a single delegated transaction.
     *
     * Caller is responsible for capability checks and for finding $tradeids
     * via find_orphaned_trades() before calling this method.
     *
     * @param \stdClass[] $items Validated item records keyed by ID.
     * @param int $instanceid Block instance ID.
     * @param context_block $context Block context (for file deletion).
     * @param int[] $tradeids Orphaned trade IDs to cascade-delete.
     */
    public static function bulk_delete_items(
        array $items,
        int $instanceid,
        context_block $context,
        array $tradeids = []
    ): void {
        global $DB;

        $itemids = array_keys($items);
        [$iteminsql, $iteminparams] = $DB->get_in_or_equal($itemids);

        $transaction = $DB->start_delegated_transaction();

        self::delete_orphaned_trades($tradeids);

        $holders = $DB->get_records_sql(
            "SELECT inv.userid, SUM(it.xp) as totalxptoremove
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} it ON inv.itemid = it.id
              WHERE inv.itemid $iteminsql
           GROUP BY inv.userid",
            $iteminparams
        );
        if ($holders) {
            self::remove_bulk_xp_from_holders($holders, $instanceid);
        }

        $DB->delete_records_select('block_playerhud_inventory', "itemid $iteminsql", $iteminparams);
        $DB->delete_records_select('block_playerhud_drops', "itemid $iteminsql", $iteminparams);
        $DB->delete_records_select('block_playerhud_trade_reqs', "itemid $iteminsql", $iteminparams);
        $DB->delete_records_select('block_playerhud_trade_rewards', "itemid $iteminsql", $iteminparams);

        $fs = get_file_storage();
        foreach ($itemids as $delid) {
            $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $delid);
        }
        $DB->delete_records_select('block_playerhud_items', "id $iteminsql", $iteminparams);

        $transaction->allow_commit();
    }

    /**
     * Deletes orphaned trade records and all their req/reward rows.
     *
     * @param int[] $tradeids Trade IDs to delete.
     */
    private static function delete_orphaned_trades(array $tradeids): void {
        global $DB;

        if (empty($tradeids)) {
            return;
        }

        [$tdsql, $tdparams] = $DB->get_in_or_equal($tradeids);
        $DB->delete_records_select('block_playerhud_trade_reqs', "tradeid $tdsql", $tdparams);
        $DB->delete_records_select('block_playerhud_trade_rewards', "tradeid $tdsql", $tdparams);
        $DB->delete_records_select('block_playerhud_trades', "id $tdsql", $tdparams);
    }

    /**
     * Subtracts item XP from each player holding the deleted item.
     *
     * @param \stdClass[] $holders Records with userid and qtd (item count).
     * @param int $instanceid Block instance ID.
     * @param int $xpperitem XP value of the item being deleted.
     */
    private static function remove_xp_from_holders(array $holders, int $instanceid, int $xpperitem): void {
        global $DB;

        $userids = array_keys($holders);
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $uparams['instanceid'] = $instanceid;

        $players = $DB->get_records_select(
            'block_playerhud_user',
            "blockinstanceid = :instanceid AND userid $usql",
            $uparams,
            '',
            'userid, id, currentxp, timemodified, enable_gamification'
        );

        $now = time();
        foreach ($holders as $holder) {
            if (isset($players[$holder->userid])) {
                $player = $players[$holder->userid];
                $player->currentxp = max(0, $player->currentxp - ($xpperitem * $holder->qtd));
                $player->timemodified = $now;
                $DB->update_record('block_playerhud_user', $player);
            }
        }
    }

    /**
     * Subtracts aggregated XP from each player holding any of the bulk-deleted items.
     *
     * @param \stdClass[] $holders Records with userid and totalxptoremove.
     * @param int $instanceid Block instance ID.
     */
    private static function remove_bulk_xp_from_holders(array $holders, int $instanceid): void {
        global $DB;

        $userids = array_keys($holders);
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $uparams['instanceid'] = $instanceid;

        $players = $DB->get_records_select(
            'block_playerhud_user',
            "blockinstanceid = :instanceid AND userid $usql",
            $uparams,
            '',
            'userid, id, currentxp, timemodified, enable_gamification'
        );

        $now = time();
        foreach ($holders as $holder) {
            if (isset($players[$holder->userid])) {
                $player = $players[$holder->userid];
                $player->currentxp = max(0, $player->currentxp - $holder->totalxptoremove);
                $player->timemodified = $now;
                $DB->update_record('block_playerhud_user', $player);
            }
        }
    }
}
