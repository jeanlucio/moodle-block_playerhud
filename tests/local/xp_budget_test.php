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
    public function test_compute_item_xp_divides_gap_evenly(): void {
        $this->assertSame(60, xp_budget::compute_item_xp(300, 5));
        $this->assertSame(20, xp_budget::compute_item_xp(300, 15));
    }

    /**
     * A remainder that does not divide evenly is floored, not rounded up or redistributed.
     */
    public function test_compute_item_xp_floors_the_remainder(): void {
        $this->assertSame(3, xp_budget::compute_item_xp(17, 5));
    }

    /**
     * A generated item is never left worth 0 XP while the economy still has any positive room,
     * even when the floor division would otherwise round down to 0.
     */
    public function test_compute_item_xp_never_zero_when_gap_is_positive(): void {
        $this->assertSame(1, xp_budget::compute_item_xp(3, 10));
    }

    /**
     * A non-positive gap or a non-positive item count yields 0: no room left, or nothing to
     * divide XP across.
     */
    public function test_compute_item_xp_zero_on_non_positive_input(): void {
        $this->assertSame(0, xp_budget::compute_item_xp(0, 5));
        $this->assertSame(0, xp_budget::compute_item_xp(-10, 5));
        $this->assertSame(0, xp_budget::compute_item_xp(300, 0));
        $this->assertSame(0, xp_budget::compute_item_xp(300, -1));
    }
}
