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
 * Tests for the item-deletion confirmation context builder.
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
 * @coversDefaultClass \block_playerhud\output\manage\item_delete_confirm
 */
final class item_delete_confirm_test extends advanced_testcase {
    /**
     * Returns the standard URL map used by the builder.
     *
     * @return array
     */
    protected function urls(): array {
        return [
            'form'   => 'https://example.com/manage.php',
            'cancel' => 'https://example.com/manage.php?tab=items',
            'edit'   => 'https://example.com/manage.php?tab=trades',
        ];
    }

    /**
     * A single deletion produces the delete_force action and a single item id.
     *
     * @covers ::build_context
     */
    public function test_build_context_single(): void {
        $this->resetAfterTest();
        $orphaned = [(object) ['name' => 'Keys for Scroll']];

        $ctx = item_delete_confirm::build_context('Bronze Key', $orphaned, [], false, [5], $this->urls(), 'id', 'DESC');

        $this->assertSame('delete_force', $ctx['action']);
        $this->assertFalse($ctx['is_bulk']);
        $this->assertSame(5, $ctx['item_id']);
        $this->assertSame([], $ctx['bulk_ids']);
        $this->assertTrue($ctx['has_orphaned']);
        $this->assertSame([['name' => 'Keys for Scroll']], $ctx['orphaned_trades']);
        $this->assertFalse($ctx['has_surviving']);
        $this->assertSame('Bronze Key', $ctx['heading']);
        $this->assertSame('id', $ctx['sort']);
        $this->assertSame('DESC', $ctx['dir']);
    }

    /**
     * A bulk deletion produces the bulk_delete_force action and the id list.
     *
     * @covers ::build_context
     */
    public function test_build_context_bulk(): void {
        $this->resetAfterTest();
        $orphaned = [(object) ['name' => 'Trade A'], (object) ['name' => 'Trade B']];

        $ctx = item_delete_confirm::build_context('Selected', $orphaned, [], true, [3, 7, 9], $this->urls(), 'name', 'ASC');

        $this->assertSame('bulk_delete_force', $ctx['action']);
        $this->assertTrue($ctx['is_bulk']);
        $this->assertSame(0, $ctx['item_id']);
        $this->assertSame([['id' => 3], ['id' => 7], ['id' => 9]], $ctx['bulk_ids']);
    }

    /**
     * One orphaned trade selects the singular confirm label and warning.
     *
     * @covers ::build_context
     */
    public function test_build_context_singular_label(): void {
        $this->resetAfterTest();
        $orphaned = [(object) ['name' => 'Only one']];

        $ctx = item_delete_confirm::build_context('X', $orphaned, [], false, [1], $this->urls(), 'id', 'DESC');

        $this->assertSame(get_string('item_delete_confirm_trade', 'block_playerhud'), $ctx['confirm_label']);
        $this->assertSame(get_string('item_delete_trade_impact_single', 'block_playerhud'), $ctx['orphaned_warning']);
    }

    /**
     * Several orphaned trades select the plural confirm label and warning.
     *
     * @covers ::build_context
     */
    public function test_build_context_plural_label(): void {
        $this->resetAfterTest();
        $orphaned = [(object) ['name' => 'A'], (object) ['name' => 'B'], (object) ['name' => 'C']];

        $ctx = item_delete_confirm::build_context('X', $orphaned, [], false, [1], $this->urls(), 'id', 'DESC');

        $this->assertSame(get_string('item_delete_confirm_trades', 'block_playerhud', 3), $ctx['confirm_label']);
        $this->assertSame(get_string('item_delete_trade_impact', 'block_playerhud'), $ctx['orphaned_warning']);
    }

    /**
     * Surviving trades are listed and, with no orphaned trade, the simple
     * confirm label is used.
     *
     * @covers ::build_context
     */
    public function test_build_context_only_surviving(): void {
        $this->resetAfterTest();
        $surviving = [(object) ['name' => 'Avatar pack']];

        $ctx = item_delete_confirm::build_context('Fada', [], $surviving, false, [4], $this->urls(), 'id', 'DESC');

        $this->assertFalse($ctx['has_orphaned']);
        $this->assertSame([], $ctx['orphaned_trades']);
        $this->assertTrue($ctx['has_surviving']);
        $this->assertSame([['name' => 'Avatar pack']], $ctx['surviving_trades']);
        $this->assertSame(get_string('item_delete_confirm_simple', 'block_playerhud'), $ctx['confirm_label']);
    }

    /**
     * Both sections are populated when a deletion orphans one trade and trims
     * another.
     *
     * @covers ::build_context
     */
    public function test_build_context_orphaned_and_surviving(): void {
        $this->resetAfterTest();
        $orphaned = [(object) ['name' => 'Fada']];
        $surviving = [(object) ['name' => 'Avatar pack']];

        $ctx = item_delete_confirm::build_context('Fada', $orphaned, $surviving, false, [4], $this->urls(), 'id', 'DESC');

        $this->assertTrue($ctx['has_orphaned']);
        $this->assertTrue($ctx['has_surviving']);
        $this->assertSame([['name' => 'Fada']], $ctx['orphaned_trades']);
        $this->assertSame([['name' => 'Avatar pack']], $ctx['surviving_trades']);
        $this->assertSame(get_string('item_delete_confirm_trade', 'block_playerhud'), $ctx['confirm_label']);
    }
}
