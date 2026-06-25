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
 * Builds the template context for the item-deletion confirmation screen.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

/**
 * Prepares the confirmation context shown before deleting items that would
 * leave one or more trades with no requirements or rewards.
 *
 * Pure builder: it receives already-resolved values (no DB, no URL building)
 * and returns the array consumed by the manage_item_delete_confirm template.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_delete_confirm {
    /**
     * Builds the template context for the confirmation screen.
     *
     * @param string $heading Pre-formatted heading (item name, or a label for bulk).
     * @param \stdClass[] $orphanedtrades Trades that would be deleted (each with ->name).
     * @param \stdClass[] $survivingtrades Trades that lose the item but remain (each with ->name).
     * @param bool $isbulk Whether this confirms a bulk deletion.
     * @param int[] $ids The item IDs involved (one for single, many for bulk).
     * @param array $urls URL strings keyed 'form', 'cancel' and 'edit'.
     * @param string $sort Current sort column, carried through the form.
     * @param string $dir Current sort direction, carried through the form.
     * @return array The template context.
     */
    public static function build_context(
        string $heading,
        array $orphanedtrades,
        array $survivingtrades,
        bool $isbulk,
        array $ids,
        array $urls,
        string $sort,
        string $dir
    ): array {
        $orphanedcount = count($orphanedtrades);

        $orphaned = [];
        foreach ($orphanedtrades as $trade) {
            $orphaned[] = ['name' => $trade->name];
        }
        $surviving = [];
        foreach ($survivingtrades as $trade) {
            $surviving[] = ['name' => $trade->name];
        }

        // Only the bulk path posts the id list; the single path posts item_id.
        $bulkids = [];
        if ($isbulk) {
            foreach ($ids as $id) {
                $bulkids[] = ['id' => $id];
            }
        }

        $hasorphaned = $orphanedcount > 0;
        if ($hasorphaned) {
            $confirmlabel = $orphanedcount === 1
                ? get_string('item_delete_confirm_trade', 'block_playerhud')
                : get_string('item_delete_confirm_trades', 'block_playerhud', $orphanedcount);
        } else {
            $confirmlabel = get_string('item_delete_confirm_simple', 'block_playerhud');
        }
        $warningkey = $orphanedcount === 1 ? 'item_delete_trade_impact_single' : 'item_delete_trade_impact';

        return [
            'heading'          => $heading,
            'has_orphaned'     => $hasorphaned,
            'orphaned_warning' => get_string($warningkey, 'block_playerhud'),
            'orphaned_trades'  => $orphaned,
            'has_surviving'    => !empty($surviving),
            'surviving_notice' => get_string('item_delete_trade_kept', 'block_playerhud'),
            'surviving_trades' => $surviving,
            'form_action'      => $urls['form'],
            'sesskey'          => sesskey(),
            'action'           => $isbulk ? 'bulk_delete_force' : 'delete_force',
            'is_bulk'          => $isbulk,
            'item_id'          => $isbulk ? 0 : ($ids[0] ?? 0),
            'bulk_ids'         => $bulkids,
            'sort'             => $sort,
            'dir'              => $dir,
            'cancel_url'       => $urls['cancel'],
            'str_cancel'       => get_string('cancel'),
            'confirm_label'    => $confirmlabel,
            'edit_url'         => $urls['edit'],
            'str_edit_trades'  => get_string('item_delete_edit_trades', 'block_playerhud'),
        ];
    }
}
