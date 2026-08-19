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
 * Safe external API for other plugins to operate on PlayerHUD items.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * The sanctioned integration surface for external plugins that grant or consume PlayerHUD
 * items (e.g. mod_playerwords and other Player-family game plugins).
 *
 * External plugins previously read and wrote block_playerhud's own tables directly, with
 * nothing validating that an item actually belonged to the caller's own block instance. That
 * let a copied activity (backup, restore or course import) silently keep operating on another
 * course's item, since block_playerhud_items.id is a single site-wide sequence, not scoped per
 * course. Every method here checks ownership first, so that class of bug cannot recur for this
 * or any future integration.
 *
 * @package block_playerhud
 */
class external_items {
    /**
     * Whether the given item belongs to the given block instance.
     *
     * The shared primitive every other method in this class builds on, so "does this item
     * really belong here" is checked in one place instead of reimplemented per caller.
     *
     * @param int $itemid PlayerHUD item ID.
     * @param int $blockinstanceid Block instance ID the caller expects the item to belong to.
     * @return bool
     */
    public static function belongs_to_instance(int $itemid, int $blockinstanceid): bool {
        global $DB;

        if ($itemid <= 0 || $blockinstanceid <= 0) {
            return false;
        }

        return $DB->record_exists('block_playerhud_items', [
            'id'              => $itemid,
            'blockinstanceid' => $blockinstanceid,
        ]);
    }

    /**
     * Returns the per-user-per-item lock key shared by grant() and consume() (and, in
     * classes\controller\items, revoke_stack_log_entry()), so no two of them can ever race each
     * other's read-modify-write of block_playerhud_stack.qty for the same user+item.
     *
     * @param int $itemid PlayerHUD item ID.
     * @param int $userid User ID.
     * @return string
     */
    public static function stack_lock_key(int $itemid, int $userid): string {
        return 'stack_usr_' . $userid . '_item_' . $itemid;
    }

    /**
     * Grants $qty units of $itemid to $userid, awarding the item's own XP (multiplied by
     * $qty) unless $suppressxp is set. A no-op (returns 0) when the item does not belong to
     * $blockinstanceid, is disabled, or $qty is not positive — disabled stops new acquisition
     * through every channel, including this one.
     *
     * $suppressxp exists to mirror block_playerhud's own "infinite drop gives no XP"
     * anti-farming rule: since an external grant never goes through a real drop, block_playerhud
     * has no way to know on its own whether the caller represents an unbounded source — callers
     * must decide that themselves and pass it in.
     *
     * Every grant increments a single balance row (block_playerhud_stack) and appends one
     * ledger row (block_playerhud_stack_log) carrying this action's own xpawarded — the audit
     * log and any future single-action revoke read xpawarded per log row, never the item's
     * current xp. block_playerhud_inventory is never written here; it holds only quantity
     * granted before this mechanism existed, and is summed alongside the new balance by
     * get_available_quantity().
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @param int $userid Recipient user ID.
     * @param int $qty Number of units to grant.
     * @param string $source Ledger source tag identifying the calling plugin or feature.
     * @param bool $suppressxp Whether to withhold the item's XP even though it was granted.
     * @param int $dropid Originating drop ID, if this grant came from a map collection (0 for
     *        any other source). Recorded on the ledger row so drop_guard can enforce a
     *        drop's own pickup limit/cooldown regardless of storage generation.
     * @return int The new block_playerhud_stack_log row ID on success, 0 on no-op.
     */
    public static function grant(
        int $blockinstanceid,
        int $itemid,
        int $userid,
        int $qty,
        string $source,
        bool $suppressxp,
        int $dropid = 0
    ): int {
        global $DB;

        if ($qty <= 0 || !self::belongs_to_instance($itemid, $blockinstanceid)) {
            return 0;
        }

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        if (!$item->enabled) {
            return 0;
        }

        $xpperunit = (!$suppressxp && (int)$item->xp > 0) ? (int)$item->xp : 0;
        $xptotal = $xpperunit * $qty;

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
        $lock = $lockfactory->get_lock(self::stack_lock_key($itemid, $userid), 5);
        if (!$lock) {
            return 0;
        }

        try {
            $now = time();
            $transaction = $DB->start_delegated_transaction();
            try {
                $stack = $DB->get_record('block_playerhud_stack', ['userid' => $userid, 'itemid' => $itemid]);
                if ($stack) {
                    $stack->qty = (int)$stack->qty + $qty;
                    $stack->timemodified = $now;
                    $DB->update_record('block_playerhud_stack', $stack);
                } else {
                    $DB->insert_record('block_playerhud_stack', (object)[
                        'userid'       => $userid,
                        'itemid'       => $itemid,
                        'qty'          => $qty,
                        'timemodified' => $now,
                    ]);
                }

                $logid = (int) $DB->insert_record('block_playerhud_stack_log', (object)[
                    'userid'      => $userid,
                    'itemid'      => $itemid,
                    'dropid'      => $dropid,
                    'delta'       => $qty,
                    'source'      => $source,
                    'xpawarded'   => $xptotal,
                    'timecreated' => $now,
                ]);

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } finally {
            $lock->release();
        }

        if ($xptotal > 0) {
            $player = \block_playerhud\game::get_player($blockinstanceid, $userid);
            \block_playerhud\game::change_xp($player, $xptotal, $blockinstanceid);
        }

        return $logid;
    }

    /**
     * Atomically consumes $qty units of $itemid from $userid.
     *
     * Spends from block_playerhud_stack first (up to its current balance), then falls back to
     * FIFO-consuming block_playerhud_inventory rows (oldest first, same mechanism this method
     * always used) for any remainder — a user whose entire balance still sits in the legacy
     * table must be able to spend it, since nothing migrates it forward on its own.
     *
     * Returns null, not false, when the item does not belong to $blockinstanceid — a foreign or
     * deleted item can never be restocked, so the caller should waive the cost instead of
     * blocking the user forever. Returns false only for a genuine insufficient balance on a
     * valid item.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @param int $userid User ID.
     * @param int $qty Number of units to consume.
     * @return bool|null True on success, false if insufficient, null if the item is invalid.
     */
    public static function consume(int $blockinstanceid, int $itemid, int $userid, int $qty): ?bool {
        global $DB;

        if (!self::belongs_to_instance($itemid, $blockinstanceid)) {
            return null;
        }

        if ($qty <= 0) {
            return true;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
        $lock = $lockfactory->get_lock(self::stack_lock_key($itemid, $userid), 5);

        if (!$lock) {
            return false;
        }

        try {
            $stack = $DB->get_record('block_playerhud_stack', ['userid' => $userid, 'itemid' => $itemid]);
            $stackqty = $stack ? (int)$stack->qty : 0;

            $legacycount = $DB->count_records_select(
                'block_playerhud_inventory',
                "userid = :userid AND itemid = :itemid AND source NOT IN ('revoked', 'consumed')",
                ['userid' => $userid, 'itemid' => $itemid]
            );

            if (($stackqty + $legacycount) < $qty) {
                return false;
            }

            $fromstack = min($qty, $stackqty);
            $fromlegacy = $qty - $fromstack;
            $now = time();

            $transaction = $DB->start_delegated_transaction();
            try {
                if ($fromstack > 0) {
                    $stack->qty = $stackqty - $fromstack;
                    $stack->timemodified = $now;
                    $DB->update_record('block_playerhud_stack', $stack);

                    $DB->insert_record('block_playerhud_stack_log', (object)[
                        'userid'      => $userid,
                        'itemid'      => $itemid,
                        'dropid'      => 0,
                        'delta'       => -$fromstack,
                        'source'      => 'consumed',
                        'xpawarded'   => 0,
                        'timecreated' => $now,
                    ]);
                }

                if ($fromlegacy > 0) {
                    $sql = "SELECT id
                              FROM {block_playerhud_inventory}
                             WHERE userid = :uid AND itemid = :iid
                                   AND source NOT IN ('revoked', 'consumed')
                          ORDER BY timecreated ASC";

                    $records = $DB->get_records_sql($sql, ['uid' => $userid, 'iid' => $itemid], 0, $fromlegacy);
                    $ids = array_keys($records);
                    [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ci');
                    $DB->set_field_select('block_playerhud_inventory', 'source', 'consumed', "id $insql", $inparams);
                }

                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns the formatted display name of an item, or empty string if it does not belong to
     * $blockinstanceid.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @return string
     */
    public static function get_name(int $blockinstanceid, int $itemid): string {
        global $DB;

        if (!self::belongs_to_instance($itemid, $blockinstanceid)) {
            return '';
        }

        $name = $DB->get_field('block_playerhud_items', 'name', ['id' => $itemid]);
        return ($name !== false) ? format_string($name) : '';
    }

    /**
     * Returns the item's own XP value, or zero if it does not belong to $blockinstanceid.
     *
     * Lets an external plugin compute a potential-XP estimate (e.g. for
     * playerhud_grant_potential) without reading block_playerhud_items directly.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @return int
     */
    public static function get_xp(int $blockinstanceid, int $itemid): int {
        global $DB;

        if (!self::belongs_to_instance($itemid, $blockinstanceid)) {
            return 0;
        }

        $xp = $DB->get_field('block_playerhud_items', 'xp', ['id' => $itemid]);
        return ($xp !== false) ? (int)$xp : 0;
    }

    /**
     * Returns how many units of an item a user currently holds — the single source of truth
     * every read path in the plugin (and any external caller) should use instead of counting
     * block_playerhud_inventory directly.
     *
     * Sums two sources: block_playerhud_inventory (every unit granted before quantity tracking
     * moved to block_playerhud_stack, using the same eligibility filter consume() always used,
     * frozen and never written to again) and block_playerhud_stack.qty (every unit granted or
     * consumed since). Zero if the item does not belong to $blockinstanceid.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @param int $userid User ID.
     * @return int
     */
    public static function get_available_quantity(int $blockinstanceid, int $itemid, int $userid): int {
        global $DB;

        if (!self::belongs_to_instance($itemid, $blockinstanceid)) {
            return 0;
        }

        $legacycount = $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :userid AND itemid = :itemid AND source NOT IN ('revoked', 'consumed')",
            ['userid' => $userid, 'itemid' => $itemid]
        );
        $stackqty = $DB->get_field('block_playerhud_stack', 'qty', ['userid' => $userid, 'itemid' => $itemid]);

        return $legacycount + ($stackqty !== false ? (int)$stackqty : 0);
    }

    /**
     * Bulk variant of get_available_quantity() for checking several items of the same user at
     * once (e.g. a quest's TYPE_TOTAL_ITEMS/TYPE_UNIQUE_ITEMS possession count, or a story
     * chapter's affordability precheck across every choice on screen) without calling $DB once
     * per item.
     *
     * Items not belonging to $blockinstanceid are silently omitted from the result, the same
     * ownership guarantee every other method in this class enforces — never a KeyError for the
     * caller, just a missing key.
     *
     * @param int $blockinstanceid Block instance ID every item must belong to.
     * @param int[] $itemids PlayerHUD item IDs to resolve.
     * @param int $userid User ID.
     * @return array<int, int> Quantity available, keyed by item ID.
     */
    public static function get_available_quantities_bulk(int $blockinstanceid, array $itemids, int $userid): array {
        global $DB;

        $itemids = array_values(array_unique(array_filter(
            array_map('intval', $itemids),
            static fn(int $id): bool => $id > 0
        )));
        if (empty($itemids)) {
            return [];
        }

        [$ownsql, $ownparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'own');
        $ownparams['blockinstanceid'] = $blockinstanceid;
        $ownitemids = array_map('intval', $DB->get_fieldset_select(
            'block_playerhud_items',
            'id',
            "id $ownsql AND blockinstanceid = :blockinstanceid",
            $ownparams
        ));
        if (empty($ownitemids)) {
            return [];
        }

        [$legsql, $legparams] = $DB->get_in_or_equal($ownitemids, SQL_PARAMS_NAMED, 'leg');
        $legparams['userid'] = $userid;
        $legacycounts = $DB->get_records_sql_menu(
            "SELECT itemid, COUNT(id) AS qty
               FROM {block_playerhud_inventory}
              WHERE userid = :userid AND itemid $legsql AND source NOT IN ('revoked', 'consumed')
           GROUP BY itemid",
            $legparams
        );

        [$stsql, $stparams] = $DB->get_in_or_equal($ownitemids, SQL_PARAMS_NAMED, 'st');
        $stparams['userid'] = $userid;
        $stackqtys = $DB->get_records_sql_menu(
            "SELECT itemid, qty FROM {block_playerhud_stack} WHERE userid = :userid AND itemid $stsql",
            $stparams
        );

        $result = [];
        foreach ($ownitemids as $itemid) {
            $result[$itemid] = (int) ($legacycounts[$itemid] ?? 0) + (int) ($stackqtys[$itemid] ?? 0);
        }

        return $result;
    }

    /**
     * Returns how many distinct item types a user has ever obtained within a block instance —
     * the possession count behind quest::TYPE_UNIQUE_ITEMS.
     *
     * Combines block_playerhud_inventory and block_playerhud_stack_log with no eligibility
     * filter on either side, matching the pre-existing "ever obtained" semantics this quest
     * type has always used: a copy later revoked/consumed/spent still counts, since the
     * question is whether the type was ever reached, not whether it is still held.
     *
     * @param int $blockinstanceid Block instance ID.
     * @param int $userid User ID.
     * @return int
     */
    public static function count_distinct_items_for_instance(int $blockinstanceid, int $userid): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT itemid) FROM (
                 SELECT inv.itemid
                   FROM {block_playerhud_inventory} inv
                   JOIN {block_playerhud_items} it ON inv.itemid = it.id
                  WHERE inv.userid = :userid1 AND it.blockinstanceid = :bi1
                  UNION
                 SELECT sl.itemid
                   FROM {block_playerhud_stack_log} sl
                   JOIN {block_playerhud_items} it ON sl.itemid = it.id
                  WHERE sl.userid = :userid2 AND it.blockinstanceid = :bi2
             ) combined",
            ['userid1' => $userid, 'bi1' => $blockinstanceid, 'userid2' => $userid, 'bi2' => $blockinstanceid]
        );
    }

    /**
     * Returns the total quantity of every item a user currently holds within a block instance,
     * summed across all items — the possession count behind quest::TYPE_TOTAL_ITEMS.
     *
     * @param int $blockinstanceid Block instance ID.
     * @param int $userid User ID.
     * @return int
     */
    public static function count_total_items_for_instance(int $blockinstanceid, int $userid): int {
        global $DB;

        $legacy = $DB->count_records_sql(
            "SELECT COUNT(inv.id)
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} it ON inv.itemid = it.id
              WHERE inv.userid = :userid AND it.blockinstanceid = :bi
                    AND inv.source NOT IN ('revoked', 'consumed')",
            ['userid' => $userid, 'bi' => $blockinstanceid]
        );
        $stack = $DB->get_field_sql(
            "SELECT COALESCE(SUM(s.qty), 0)
               FROM {block_playerhud_stack} s
               JOIN {block_playerhud_items} it ON s.itemid = it.id
              WHERE s.userid = :userid AND it.blockinstanceid = :bi",
            ['userid' => $userid, 'bi' => $blockinstanceid]
        );

        return (int) $legacy + (int) $stack;
    }
}
