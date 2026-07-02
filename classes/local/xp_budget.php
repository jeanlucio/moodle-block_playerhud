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
 * Deterministic XP division for the gamification wizard.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Divides the remaining XP room under the level ceiling across generated elements.
 *
 * Replaces `ai\generator`'s per-batch random XP guess (picked once from a coarse bucket by gap
 * size and applied identically to every item in the batch, regardless of how many items are
 * being generated) with a deterministic share of the actual gap. Pure and side-effect free, so
 * it is fully testable without touching the database or an AI provider.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class xp_budget {
    /**
     * Computes how much XP each of a batch of items should be worth so the batch's total
     * approximates the remaining XP room, rather than a per-item value that ignores how many
     * items are being generated.
     *
     * At least 1 XP is returned whenever there is any positive gap and at least one item, even
     * if the floor division would otherwise round down to 0 — a generated item is never left
     * worthless while the economy still has room. The floor division's remainder is not
     * redistributed here; each item gets an equal share.
     *
     * @param int $gap Remaining XP room under the level ceiling (target minus current economy).
     * @param int $itemcount Number of items about to be generated.
     * @return int XP to assign to each item. 0 when there is no gap or no items.
     */
    public static function compute_item_xp(int $gap, int $itemcount): int {
        if ($itemcount <= 0 || $gap <= 0) {
            return 0;
        }

        return max(1, intdiv($gap, $itemcount));
    }
}
