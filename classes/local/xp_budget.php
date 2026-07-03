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
    /** @var int Item count generated for the "short journey" size. */
    private const ITEM_COUNT_SHORT = 5;

    /** @var int Item count generated for the "medium journey" size. */
    private const ITEM_COUNT_MEDIUM = 10;

    /** @var int Item count generated for the "long journey" size. */
    private const ITEM_COUNT_LONG = 15;

    /** @var int Mission count generated for the "short journey" size. */
    private const MISSION_COUNT_SHORT = 3;

    /** @var int Mission count generated for the "medium journey" size. */
    private const MISSION_COUNT_MEDIUM = 5;

    /** @var int Mission count generated for the "long journey" size. */
    private const MISSION_COUNT_LONG = 8;

    /**
     * Maps a wizard journey size to how many items to generate.
     *
     * @param string $size Journey size: short, medium or long.
     * @return int Item count.
     */
    public static function compute_item_count(string $size): int {
        return match ($size) {
            'long' => self::ITEM_COUNT_LONG,
            'medium' => self::ITEM_COUNT_MEDIUM,
            default => self::ITEM_COUNT_SHORT,
        };
    }

    /**
     * Counts how many XP-budget-sharing elements (items and/or missions) a wizard call will
     * generate for the given journey size, so their combined total can share a single XP split
     * computed once — see execute()'s $sharedxp, which this feeds.
     *
     * @param string $size Journey size: short, medium or long.
     * @param bool $includeitems Whether the Items module is running this call.
     * @param bool $includemissions Whether the Missions module is running this call.
     * @return int Combined element count. 0 when neither module is running.
     */
    public static function compute_element_count(string $size, bool $includeitems, bool $includemissions): int {
        $count = 0;
        if ($includeitems) {
            $count += self::compute_item_count($size);
        }
        if ($includemissions) {
            $count += self::compute_mission_count($size);
        }

        return $count;
    }

    // The 3 constants below are echoed as plain numbers in the wizard_levels_suggestion_* lang
    // strings (shown in the modal before this class is ever called), since that text is fetched
    // once at modal init, not recomputed per value. Keep both in sync if these ever change.

    /** @var int Suggested max_levels for the "short journey" size. */
    private const SUGGESTED_LEVELS_SHORT = 10;

    /** @var int Suggested max_levels for the "medium journey" size. */
    private const SUGGESTED_LEVELS_MEDIUM = 15;

    /** @var int Suggested max_levels for the "long journey" size. */
    private const SUGGESTED_LEVELS_LONG = 20;

    /**
     * Maps a wizard journey size to a suggested max_levels for the block's own "XP per level" /
     * "Max levels" settings, offered as an opt-in suggestion when the instance is still at the
     * form's out-of-the-box defaults (100 XP per level, 20 levels — see edit_form.php).
     *
     * XP per level is deliberately left at the same 100 for every size: only the level count
     * (granularity of the climb) is tuned, not the per-level cost, keeping the suggestion a
     * single easy-to-explain number. The Long suggestion (20) matches today's existing default,
     * so only Short and Medium actually change anything if applied.
     *
     * @param string $size Journey size: short, medium or long.
     * @return int Suggested max_levels.
     */
    public static function compute_suggested_max_levels(string $size): int {
        return match ($size) {
            'long' => self::SUGGESTED_LEVELS_LONG,
            'medium' => self::SUGGESTED_LEVELS_MEDIUM,
            default => self::SUGGESTED_LEVELS_SHORT,
        };
    }

    /**
     * Distributes the remaining XP room across a batch of elements (items or missions) so the
     * batch's total always lands on exactly the gap, instead of a flat floor share per element
     * that quietly leaves the division's remainder unused.
     *
     * Mirrors `drop_distribution::compute_pill_quotas()`'s remainder-to-the-front pattern: every
     * element gets the base floor share, and the leftover remainder is added as a +1 bonus to
     * the first `$gap % $count` elements, in order — so `array_sum()` of the result always
     * equals `min($gap, ...)`, never leaving XP on the table.
     *
     * When there is less gap than elements (the economy is nearly at the ceiling but a full
     * batch is still being generated), only the first `$gap` elements get 1 XP each and the
     * rest get 0 — honest about there being no more room left, rather than inflating past the
     * ceiling to avoid a 0-XP element.
     *
     * @param int $gap Remaining XP room under the level ceiling (target minus current economy).
     * @param int $count Number of elements about to be generated.
     * @return int[] $count XP values, order-significant (earlier elements get the remainder
     *     bonus first), summing to exactly max(0, min($gap, ...)). Empty when $count <= 0.
     */
    public static function distribute_share(int $gap, int $count): array {
        if ($count <= 0) {
            return [];
        }
        if ($gap <= 0) {
            return array_fill(0, $count, 0);
        }
        if ($gap < $count) {
            $shares = array_fill(0, $count, 0);
            for ($i = 0; $i < $gap; $i++) {
                $shares[$i] = 1;
            }
            return $shares;
        }

        $base = intdiv($gap, $count);
        $remainder = $gap % $count;
        $shares = array_fill(0, $count, $base);
        for ($i = 0; $i < $remainder; $i++) {
            $shares[$i]++;
        }

        return $shares;
    }

    /**
     * Maps a wizard journey size to how many heuristic mission suggestions to create.
     *
     * Deliberately smaller than the matching item count (`wizard_generate`'s own SIZE_*_AMOUNT
     * constants): missions represent milestones, not small collectibles, so a course does not
     * need as many of them to feel complete.
     *
     * @param string $size Journey size: short, medium or long.
     * @return int Mission count cap.
     */
    public static function compute_mission_count(string $size): int {
        return match ($size) {
            'long' => self::MISSION_COUNT_LONG,
            'medium' => self::MISSION_COUNT_MEDIUM,
            default => self::MISSION_COUNT_SHORT,
        };
    }

    /**
     * Picks at most $limit suggestions from a candidate list, round-robining across their
     * `type` values so no single type crowds out the others.
     *
     * Without this, a course with many completion-enabled activities would fill the entire
     * mission count with activity-completion suggestions before a level or collection milestone
     * ever got a chance, since `quest::get_heuristic_suggestions()` lists activities first.
     * Selection order within each type is preserved (its own suggestion order is untouched); only
     * the interleaving across types changes.
     *
     * @param array $suggestions Candidate suggestions, each with at least a 'type' key.
     * @param int $limit Maximum number of suggestions to return.
     * @return array At most $limit suggestions, round-robin selected across types.
     */
    public static function select_balanced_missions(array $suggestions, int $limit): array {
        if ($limit <= 0) {
            return [];
        }

        $bytype = [];
        foreach ($suggestions as $suggestion) {
            $bytype[$suggestion['type']][] = $suggestion;
        }

        $selected = [];
        $tookany = true;
        while (count($selected) < $limit && $tookany) {
            $tookany = false;
            foreach ($bytype as $type => &$bucket) {
                if (count($selected) >= $limit) {
                    break;
                }
                if (!empty($bucket)) {
                    $selected[] = array_shift($bucket);
                    $tookany = true;
                }
            }
            unset($bucket);
        }

        return $selected;
    }
}
