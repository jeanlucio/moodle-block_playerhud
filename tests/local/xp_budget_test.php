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
     * Each journey size maps to its own item count.
     */
    public function test_compute_item_count_maps_each_size(): void {
        $this->assertSame(5, xp_budget::compute_item_count('short'));
        $this->assertSame(10, xp_budget::compute_item_count('medium'));
        $this->assertSame(15, xp_budget::compute_item_count('long'));
    }

    /**
     * Items and Missions running in the same call share ONE combined element count, so a
     * caller can split a single gap across both rather than each seeing the whole gap to
     * itself — the fix for wizard_generate running Items first and silently starving
     * Missions of the entire budget before it even ran.
     */
    public function test_compute_element_count_combines_both_modules(): void {
        $this->assertSame(5, xp_budget::compute_element_count('short', true, false));
        $this->assertSame(3, xp_budget::compute_element_count('short', false, true));
        $this->assertSame(8, xp_budget::compute_element_count('short', true, true));
        $this->assertSame(0, xp_budget::compute_element_count('short', false, false));
    }

    /**
     * A batch that divides evenly gets a flat share, and the shares always sum to exactly the
     * gap — never leaving a floor-division remainder unused.
     */
    public function test_distribute_share_divides_gap_evenly(): void {
        $this->assertSame([60, 60, 60, 60, 60], xp_budget::distribute_share(300, 5));
        $this->assertSame(array_fill(0, 15, 20), xp_budget::distribute_share(300, 15));
    }

    /**
     * A remainder that does not divide evenly is spread as a +1 bonus on the first elements
     * (mirrors drop_distribution::compute_activity_quotas()'s remainder-to-the-front pattern), so
     * the total always sums to exactly the gap instead of quietly losing the remainder.
     */
    public function test_distribute_share_spreads_the_remainder_on_the_first_elements(): void {
        $this->assertSame([4, 3, 3, 3, 3], xp_budget::distribute_share(16, 5));
        $this->assertSame(16, array_sum(xp_budget::distribute_share(16, 5)));
    }

    /**
     * When there is less gap than elements, only the first $gap elements get 1 XP each and the
     * rest get 0 — honest about there being no more room, rather than inflating past the ceiling
     * to avoid a 0-XP element.
     */
    public function test_distribute_share_caps_at_the_gap_when_elements_outnumber_it(): void {
        $this->assertSame([1, 1, 1, 0, 0], xp_budget::distribute_share(3, 5));
        $this->assertSame(3, array_sum(xp_budget::distribute_share(3, 5)));
    }

    /**
     * A non-positive gap yields all zeros (still one entry per element); a non-positive element
     * count yields an empty array.
     */
    public function test_distribute_share_handles_edge_cases(): void {
        $this->assertSame([0, 0, 0], xp_budget::distribute_share(0, 3));
        $this->assertSame([0, 0, 0], xp_budget::distribute_share(-10, 3));
        $this->assertSame([], xp_budget::distribute_share(300, 0));
        $this->assertSame([], xp_budget::distribute_share(300, -1));
    }

    /**
     * Each journey size maps to its own suggested max_levels, offered as an opt-in suggestion
     * when the instance is still at the form's defaults.
     */
    public function test_compute_suggested_max_levels_maps_each_size(): void {
        $this->assertSame(10, xp_budget::compute_suggested_max_levels('short'));
        $this->assertSame(15, xp_budget::compute_suggested_max_levels('medium'));
        $this->assertSame(20, xp_budget::compute_suggested_max_levels('long'));
    }

    /**
     * An unrecognised size string falls back to the short suggestion, matching the size
     * parameter's own PARAM_ALPHA default of 'short'.
     */
    public function test_compute_suggested_max_levels_falls_back_to_short(): void {
        $this->assertSame(10, xp_budget::compute_suggested_max_levels('unknown'));
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
     * Each journey size maps to its own total chapter count (including the fixed Chapter 1) —
     * short's floor of 5 is a design requirement, not an arbitrary minimum: 1 star per completed
     * chapter up to 5 means the wizard's own story arc must reach the max RPG tier on its own.
     */
    public function test_compute_chapter_count_maps_each_size(): void {
        $this->assertSame(5, xp_budget::compute_chapter_count('short'));
        $this->assertSame(6, xp_budget::compute_chapter_count('medium'));
        $this->assertSame(7, xp_budget::compute_chapter_count('long'));
    }

    /**
     * An unrecognised size string falls back to the short chapter count, matching the size
     * parameter's own PARAM_ALPHA default of 'short'.
     */
    public function test_compute_chapter_count_falls_back_to_short(): void {
        $this->assertSame(5, xp_budget::compute_chapter_count('unknown'));
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
