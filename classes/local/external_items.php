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
     * Grants $qty units of $itemid to $userid, awarding the item's own XP unless $suppressxp
     * is set. A no-op (returns false) when the item does not belong to $blockinstanceid or is
     * disabled — disabled stops new acquisition through every channel, including this one.
     *
     * $suppressxp exists to mirror block_playerhud's own "infinite drop gives no XP"
     * anti-farming rule: since an external grant never goes through a real drop, block_playerhud
     * has no way to know on its own whether the caller represents an unbounded source — callers
     * must decide that themselves and pass it in.
     *
     * Each granted unit is its own inventory row carrying its own xpawarded, mirroring how
     * block_playerhud records its own grants — the audit log and any future revoke/delete
     * reversal read xpawarded per row, never the item's current xp.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item ID.
     * @param int $userid Recipient user ID.
     * @param int $qty Number of units to grant.
     * @param string $source Inventory source tag identifying the calling plugin.
     * @param bool $suppressxp Whether to withhold the item's XP even though it was granted.
     * @return bool True on success, false on no-op.
     */
    public static function grant(
        int $blockinstanceid,
        int $itemid,
        int $userid,
        int $qty,
        string $source,
        bool $suppressxp
    ): bool {
        global $DB;

        if ($qty <= 0 || !self::belongs_to_instance($itemid, $blockinstanceid)) {
            return false;
        }

        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], '*', MUST_EXIST);
        if (!$item->enabled) {
            return false;
        }

        $xpperunit = (!$suppressxp && (int)$item->xp > 0) ? (int)$item->xp : 0;

        for ($i = 0; $i < $qty; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object)[
                'userid'      => $userid,
                'itemid'      => $itemid,
                'dropid'      => 0,
                'source'      => $source,
                'timecreated' => time(),
                'xpawarded'   => $xpperunit,
            ]);
        }

        if ($xpperunit > 0) {
            $player = \block_playerhud\game::get_player($blockinstanceid, $userid);
            \block_playerhud\game::change_xp($player, $xpperunit * $qty, $blockinstanceid);
        }

        return true;
    }

    /**
     * Atomically consumes $qty units of $itemid from $userid's inventory, FIFO (oldest first).
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
        $lockkey = 'consume_usr_' . $userid . '_item_' . $itemid;
        $lock = $lockfactory->get_lock($lockkey, 5);

        if (!$lock) {
            return false;
        }

        try {
            $sql = "SELECT id
                      FROM {block_playerhud_inventory}
                     WHERE userid = :uid AND itemid = :iid
                           AND source NOT IN ('revoked', 'consumed')
                  ORDER BY timecreated ASC";

            $records = $DB->get_records_sql($sql, ['uid' => $userid, 'iid' => $itemid], 0, $qty);

            if (count($records) < $qty) {
                return false;
            }

            $ids = array_keys($records);
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ci');
            $DB->set_field_select('block_playerhud_inventory', 'source', 'consumed', "id $insql", $inparams);

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
     * Returns how many available (not consumed or revoked) units of an item a user currently
     * holds, using the same eligibility filter as consume(). Zero if the item does not belong
     * to $blockinstanceid.
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

        return $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :userid AND itemid = :itemid AND source NOT IN ('revoked', 'consumed')",
            ['userid' => $userid, 'itemid' => $itemid]
        );
    }
}
