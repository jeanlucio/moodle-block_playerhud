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
    /** @var int Mission count generated for the "short journey" size. */
    private const MISSION_COUNT_SHORT = 3;

    /** @var int Mission count generated for the "medium journey" size. */
    private const MISSION_COUNT_MEDIUM = 5;

    /** @var int Mission count generated for the "long journey" size. */
    private const MISSION_COUNT_LONG = 8;

    /**
     * Computes how much XP each of a batch of elements (items or missions) should be worth so
     * the batch's total approximates the remaining XP room, rather than a value that ignores how
     * many elements are being generated.
     *
     * At least 1 XP is returned whenever there is any positive gap and at least one element, even
     * if the floor division would otherwise round down to 0 — a generated element is never left
     * worthless while the economy still has room. The floor division's remainder is not
     * redistributed here; each element gets an equal share.
     *
     * @param int $gap Remaining XP room under the level ceiling (target minus current economy).
     * @param int $count Number of elements about to be generated.
     * @return int XP to assign to each element. 0 when there is no gap or no elements.
     */
    public static function compute_share(int $gap, int $count): int {
        if ($count <= 0 || $gap <= 0) {
            return 0;
        }

        return max(1, intdiv($gap, $count));
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
