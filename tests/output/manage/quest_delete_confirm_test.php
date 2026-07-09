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
 * Tests for the quest-deletion confirmation context builder.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * Tests for the confirmation screen context.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\output\manage\quest_delete_confirm
 */
final class quest_delete_confirm_test extends advanced_testcase {
    /**
     * Returns the standard URL map used by the builder.
     *
     * @param bool $withtoggle Whether to include a toggle (disable-instead) URL.
     * @return array
     */
    protected function urls(bool $withtoggle = false): array {
        $urls = [
            'form'   => 'https://example.com/manage.php',
            'cancel' => 'https://example.com/manage.php?tab=quests',
        ];
        if ($withtoggle) {
            $urls['toggle'] = 'https://example.com/manage.php?action=toggle_quest&questid=5';
        }
        return $urls;
    }

    /**
     * A single deletion produces the delete_quest_force action and a single quest id.
     *
     * @covers ::build_context
     */
    public function test_build_context_single(): void {
        $this->resetAfterTest();
        $xpimpact = (object) ['studentcount' => 2, 'totalxp' => 400];

        $ctx = quest_delete_confirm::build_context('Bonus Quest', $xpimpact, false, [5], $this->urls(true), 'id', 'DESC');

        $this->assertSame('delete_quest_force', $ctx['action']);
        $this->assertFalse($ctx['is_bulk']);
        $this->assertSame(5, $ctx['quest_id']);
        $this->assertSame([], $ctx['bulk_ids']);
        $this->assertSame('Bonus Quest', $ctx['heading']);
        $this->assertSame('id', $ctx['sort']);
        $this->assertSame('DESC', $ctx['dir']);
        $this->assertTrue($ctx['has_xp_impact']);
        $this->assertSame(
            get_string('quest_delete_xp_impact', 'block_playerhud', (object) ['students' => 2, 'xp' => 400]),
            $ctx['xp_impact_warning']
        );
        $this->assertTrue($ctx['has_disable_link']);
        $this->assertSame($this->urls(true)['toggle'], $ctx['disable_url']);
        $this->assertSame(get_string('quest_delete_confirm_simple', 'block_playerhud'), $ctx['confirm_label']);
    }

    /**
     * A bulk deletion produces the bulk_delete_quests_force action and the id list, and never
     * shows the disable-instead link even with a toggle URL supplied by mistake.
     *
     * @covers ::build_context
     */
    public function test_build_context_bulk(): void {
        $this->resetAfterTest();
        $xpimpact = (object) ['studentcount' => 3, 'totalxp' => 600];

        $ctx = quest_delete_confirm::build_context(
            'Selected',
            $xpimpact,
            true,
            [3, 7, 9],
            $this->urls(true),
            'name',
            'ASC'
        );

        $this->assertSame('bulk_delete_quests_force', $ctx['action']);
        $this->assertTrue($ctx['is_bulk']);
        $this->assertSame(0, $ctx['quest_id']);
        $this->assertSame([['id' => 3], ['id' => 7], ['id' => 9]], $ctx['bulk_ids']);
        $this->assertTrue($ctx['has_xp_impact']);
        $this->assertFalse($ctx['has_disable_link']);
    }

    /**
     * No XP impact omits the warning and the disable link entirely.
     *
     * @covers ::build_context
     */
    public function test_build_context_no_xp_impact(): void {
        $this->resetAfterTest();
        $noimpact = (object) ['studentcount' => 0, 'totalxp' => 0];

        $ctx = quest_delete_confirm::build_context('Quiet Quest', $noimpact, false, [5], $this->urls(true), 'id', 'DESC');

        $this->assertFalse($ctx['has_xp_impact']);
        $this->assertSame('', $ctx['xp_impact_warning']);
        $this->assertFalse($ctx['has_disable_link']);
    }
}
