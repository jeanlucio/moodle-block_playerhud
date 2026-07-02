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
 * Tests for the xp_budget shared class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use advanced_testcase;

/**
 * Tests for the xp_budget shared class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\local\xp_budget
 */
final class xp_budget_test extends advanced_testcase {
    /**
     * A batch's per-item XP is an even floor share of the gap, so the batch total scales with
     * how many items are being generated rather than staying fixed regardless of item count.
     */
    public function test_compute_share_divides_gap_evenly(): void {
        $this->assertSame(60, xp_budget::compute_share(300, 5));
        $this->assertSame(20, xp_budget::compute_share(300, 15));
    }

    /**
     * A remainder that does not divide evenly is floored, not rounded up or redistributed.
     */
    public function test_compute_share_floors_the_remainder(): void {
        $this->assertSame(3, xp_budget::compute_share(17, 5));
    }

    /**
     * A generated item is never left worth 0 XP while the economy still has any positive room,
     * even when the floor division would otherwise round down to 0.
     */
    public function test_compute_share_never_zero_when_gap_is_positive(): void {
        $this->assertSame(1, xp_budget::compute_share(3, 10));
    }

    /**
     * A non-positive gap or a non-positive item count yields 0: no room left, or nothing to
     * divide XP across.
     */
    public function test_compute_share_zero_on_non_positive_input(): void {
        $this->assertSame(0, xp_budget::compute_share(0, 5));
        $this->assertSame(0, xp_budget::compute_share(-10, 5));
        $this->assertSame(0, xp_budget::compute_share(300, 0));
        $this->assertSame(0, xp_budget::compute_share(300, -1));
    }

    /**
     * Each journey size maps to its own mission count, smaller than the matching item count
     * since missions represent milestones rather than small collectibles.
     */
    public function test_compute_mission_count_maps_each_size(): void {
        $this->assertSame(3, xp_budget::compute_mission_count('short'));
        $this->assertSame(5, xp_budget::compute_mission_count('medium'));
        $this->assertSame(8, xp_budget::compute_mission_count('long'));
    }

    /**
     * An unrecognised size string falls back to the short mission count, matching the size
     * parameter's own PARAM_ALPHA default of 'short'.
     */
    public function test_compute_mission_count_falls_back_to_short(): void {
        $this->assertSame(3, xp_budget::compute_mission_count('unknown'));
    }

    /**
     * Selection round-robins across suggestion types instead of taking candidates in list
     * order, so a type with many candidates cannot crowd out the others before the limit is
     * reached.
     */
    public function test_select_balanced_missions_round_robins_across_types(): void {
        $suggestions = [
            ['type' => 5, 'uid' => 'act_1'],
            ['type' => 5, 'uid' => 'act_2'],
            ['type' => 5, 'uid' => 'act_3'],
            ['type' => 5, 'uid' => 'act_4'],
            ['type' => 1, 'uid' => 'lvl_5'],
            ['type' => 1, 'uid' => 'lvl_10'],
            ['type' => 3, 'uid' => 'col_1'],
        ];

        $selected = xp_budget::select_balanced_missions($suggestions, 3);

        $this->assertCount(3, $selected);
        $uids = array_column($selected, 'uid');
        $this->assertSame(['act_1', 'lvl_5', 'col_1'], $uids);
    }

    /**
     * Order within a single type is preserved: the round-robin only changes the interleaving
     * across types, never the relative order candidates of the same type were offered in.
     */
    public function test_select_balanced_missions_preserves_order_within_a_type(): void {
        $suggestions = [
            ['type' => 1, 'uid' => 'lvl_5'],
            ['type' => 1, 'uid' => 'lvl_10'],
            ['type' => 1, 'uid' => 'lvl_15'],
        ];

        $selected = xp_budget::select_balanced_missions($suggestions, 2);

        $this->assertSame(['lvl_5', 'lvl_10'], array_column($selected, 'uid'));
    }

    /**
     * A limit at or above the candidate count returns every candidate, unchanged in count.
     */
    public function test_select_balanced_missions_returns_all_when_limit_covers_them(): void {
        $suggestions = [
            ['type' => 1, 'uid' => 'lvl_5'],
            ['type' => 3, 'uid' => 'col_1'],
        ];

        $this->assertCount(2, xp_budget::select_balanced_missions($suggestions, 5));
    }

    /**
     * A non-positive limit or an empty candidate list yields no selection.
     */
    public function test_select_balanced_missions_handles_edge_cases(): void {
        $this->assertSame([], xp_budget::select_balanced_missions([['type' => 1]], 0));
        $this->assertSame([], xp_budget::select_balanced_missions([['type' => 1]], -1));
        $this->assertSame([], xp_budget::select_balanced_missions([], 5));
    }
}
