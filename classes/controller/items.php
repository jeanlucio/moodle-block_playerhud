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
 * Controller for item lifecycle: enable toggle, manual grant/revoke and
 * deletion (with cascade to orphaned trades).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class items {
    /**
     * Flips the enabled flag of an item belonging to the given instance.
     *
     * A foreign item id is a no-op (returns false) so callers do not redirect
     * with a success message for something they did not change.
     *
     * @param int $itemid The item to toggle.
     * @param int $instanceid The owning block instance ID.
     * @return bool True when the item was found and toggled, false otherwise.
     */
    public static function toggle_item(int $itemid, int $instanceid): bool {
        global $DB;

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
        if (!$item) {
            return false;
        }

        $DB->set_field('block_playerhud_items', 'enabled', $item->enabled ? 0 : 1, ['id' => $itemid]);

        return true;
    }

    /**
     * Manually grants an item to a player and awards its XP.
     *
     * The item must belong to the instance. The inventory row is tagged with
     * source 'teacher' and no originating drop.
     *
     * @param int $itemid The item to grant.
     * @param int $userid The recipient user ID.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    public static function grant_item(int $itemid, int $userid, int $instanceid): void {
        global $DB;

        $item = $DB->get_record(
            'block_playerhud_items',
            ['id' => $itemid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );
        $player = \block_playerhud\game::get_player($instanceid, $userid);
        $xpgain = ($item->xp > 0) ? (int)$item->xp : 0;

        $newinv              = new \stdClass();
        $newinv->userid      = $userid;
        $newinv->itemid      = $item->id;
        $newinv->dropid      = 0;
        $newinv->source      = 'teacher';
        $newinv->timecreated = time();
        $newinv->xpawarded   = $xpgain;
        $DB->insert_record('block_playerhud_inventory', $newinv);

        if ($xpgain > 0) {
            \block_playerhud\game::change_xp($player, $xpgain, $instanceid);
        }
    }

    /**
     * Soft-revokes a granted item, marking the inventory row as 'revoked' and deducting the
     * XP actually recorded for that copy at grant time.
     *
     * Deducting the recorded xpawarded (rather than the item's current xp) means an infinite
     * drop's copy (xpawarded = 0) is naturally a no-op, with no separate drop lookup needed.
     * A foreign inventory row is a no-op.
     *
     * @param int $invid The inventory row to revoke.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    public static function revoke_item(int $invid, int $instanceid): void {
        global $DB;

        $inv = $DB->get_record_sql(
            "SELECT inv.*
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} i ON i.id = inv.itemid
              WHERE inv.id = :invid AND i.blockinstanceid = :instanceid",
            ['invid' => $invid, 'instanceid' => $instanceid]
        );
        if (!$inv) {
            return;
        }

        $player = $DB->get_record('block_playerhud_user', ['blockinstanceid' => $instanceid, 'userid' => $inv->userid]);
        if ($player && (int)$inv->xpawarded > 0) {
            \block_playerhud\game::change_xp($player, -(int)$inv->xpawarded, $instanceid);
        }

        // Soft revoke: mark the inventory record as revoked instead of deleting.
        $inv->source      = 'revoked';
        $inv->timecreated = time();
        $DB->update_record('block_playerhud_inventory', $inv);
    }

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
     * Returns trades that reference the given items but would survive their
     * removal — i.e. the item is stripped from the trade, yet it keeps at least
     * one requirement and one reward, so it is NOT deleted.
     *
     * These are the trades shown as an informational notice (the item is
     * silently removed from them) as opposed to the orphaned ones that are
     * deleted outright. Orphaned trades are excluded from the result.
     *
     * @param int $instanceid Block instance ID.
     * @param int[] $itemids Item IDs being deleted.
     * @return \stdClass[] Surviving affected trade records (id, name) keyed by trade ID.
     */
    public static function find_affected_surviving_trades(int $instanceid, array $itemids): array {
        global $DB;

        if (empty($itemids)) {
            return [];
        }

        [$rsql, $rparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'sr');
        [$wsql, $wparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'sw');

        $affected = $DB->get_records_sql(
            "SELECT t.id, t.name
               FROM {block_playerhud_trades} t
              WHERE t.blockinstanceid = :iid
                AND (
                    EXISTS (
                        SELECT 1 FROM {block_playerhud_trade_reqs} r
                         WHERE r.tradeid = t.id AND r.itemid $rsql
                    )
                    OR EXISTS (
                        SELECT 1 FROM {block_playerhud_trade_rewards} w
                         WHERE w.tradeid = t.id AND w.itemid $wsql
                    )
                )",
            array_merge(['iid' => $instanceid], $rparams, $wparams)
        );

        // The orphaned trades are reported separately (they get deleted), so drop them here.
        foreach (self::find_orphaned_trades($instanceid, $itemids) as $id => $orphan) {
            unset($affected[$id]);
        }

        return $affected;
    }

    /**
     * Summarises the recorded XP impact of deleting the given items.
     *
     * Aggregate only (student count and total XP), never a per-student breakdown, so the
     * confirmation screen stays short even on a large course. Only counts copies that actually
     * earned XP (xpawarded > 0); a copy from an infinite drop or a zero-XP item never shows up
     * here, since deleting it never touches anyone's balance.
     *
     * @param int[] $itemids Item IDs being deleted.
     * @return \stdClass {studentcount: int, totalxp: int}.
     */
    public static function find_xp_impact(array $itemids): \stdClass {
        global $DB;

        $impact = (object) ['studentcount' => 0, 'totalxp' => 0];
        if (empty($itemids)) {
            return $impact;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids);
        $row = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT userid) AS studentcount, COALESCE(SUM(xpawarded), 0) AS totalxp
               FROM {block_playerhud_inventory}
              WHERE itemid $insql AND xpawarded > 0",
            $inparams
        );

        $impact->studentcount = (int) $row->studentcount;
        $impact->totalxp = (int) $row->totalxp;

        return $impact;
    }

    /**
     * Deletes an item record, its inventory/drop dependencies, and any orphaned
     * trades in a single delegated transaction.
     *
     * Caller is responsible for capability checks and for finding $tradeids
     * via find_orphaned_trades() before calling this method.
     *
     * @param \stdClass $item Item record (must include id).
     * @param int $instanceid Block instance ID.
     * @param context_block $context Block context (for file deletion).
     * @param int[] $tradeids Orphaned trade IDs to cascade-delete.
     */
    public static function delete_item(\stdClass $item, int $instanceid, context_block $context, array $tradeids = []): void {
        global $DB;

        $itemid = $item->id;
        $transaction = $DB->start_delegated_transaction();

        self::delete_orphaned_trades($tradeids);

        // Remove each holder's recorded XP for this item.
        $holders = $DB->get_records_sql(
            "SELECT userid, SUM(xpawarded) AS totalxp
               FROM {block_playerhud_inventory}
              WHERE itemid = ?
           GROUP BY userid",
            [$itemid]
        );
        if ($holders) {
            self::remove_xp_from_holders($holders, $instanceid);
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
            "SELECT userid, SUM(xpawarded) AS totalxp
               FROM {block_playerhud_inventory}
              WHERE itemid $iteminsql
           GROUP BY userid",
            $iteminparams
        );
        if ($holders) {
            self::remove_xp_from_holders($holders, $instanceid);
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
     * Subtracts each player's recorded XP (SUM of xpawarded) for the deleted item(s).
     *
     * Deducting the recorded value instead of the item's current xp means a holder whose
     * copies came from a mix of finite and infinite drops only loses what they actually
     * earned. The two former call sites (single-item and bulk delete) collapsed into this one
     * helper once both started reading the same recorded total instead of recomputing it two
     * different (and differently buggy) ways.
     *
     * @param \stdClass[] $holders Records with userid and totalxp (SUM of xpawarded).
     * @param int $instanceid Block instance ID.
     */
    private static function remove_xp_from_holders(array $holders, int $instanceid): void {
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

        foreach ($holders as $holder) {
            if (isset($players[$holder->userid]) && (int)$holder->totalxp > 0) {
                $player = $players[$holder->userid];
                \block_playerhud\game::change_xp($player, -(int)$holder->totalxp, $instanceid);
            }
        }
    }
}
