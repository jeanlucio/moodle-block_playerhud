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
}
