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
 * Tests for the item-disable confirmation context builder.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use advanced_testcase;

/**
 * Tests for item_disable_confirm::build_context().
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\manage\item_disable_confirm
 */
final class item_disable_confirm_test extends advanced_testcase {
    /**
     * The context lists the affected quests by role and posts the confirmed toggle.
     */
    public function test_build_context_lists_quests_and_posts_confirmed_toggle(): void {
        $this->resetAfterTest();

        $ctx = item_disable_confirm::build_context(
            'Gem',
            ['requirement' => [(object) ['name' => 'Collect gems']], 'reward' => [(object) ['name' => 'Big prize']]],
            [(object) ['name' => 'Gem shop']],
            5,
            ['form' => 'https://example.com/manage.php', 'cancel' => 'https://example.com/manage.php?tab=items'],
            'name',
            'ASC'
        );

        $this->assertSame('Gem', $ctx['heading']);
        $this->assertSame('toggle', $ctx['action']);
        $this->assertSame(5, $ctx['item_id']);
        $this->assertSame('name', $ctx['sort']);
        $this->assertSame('ASC', $ctx['dir']);
        $this->assertTrue($ctx['has_quest_requirement']);
        $this->assertSame([['name' => 'Collect gems']], $ctx['quest_requirement_list']);
        $this->assertSame(get_string('item_disable_quest_requirement', 'block_playerhud'), $ctx['quest_requirement_warning']);
        $this->assertTrue($ctx['has_quest_reward']);
        $this->assertSame([['name' => 'Big prize']], $ctx['quest_reward_list']);
        $this->assertSame(get_string('item_disable_quest_reward', 'block_playerhud'), $ctx['quest_reward_warning']);
        $this->assertTrue($ctx['has_trades']);
        $this->assertSame([['name' => 'Gem shop']], $ctx['trades_list']);
        $this->assertSame(get_string('item_disable_trades', 'block_playerhud'), $ctx['trades_warning']);
        $this->assertSame(get_string('item_disable_confirm', 'block_playerhud'), $ctx['confirm_label']);
    }

    /**
     * A role with no quests leaves its block off.
     */
    public function test_build_context_without_reward_quests(): void {
        $this->resetAfterTest();

        $ctx = item_disable_confirm::build_context(
            'Gem',
            ['requirement' => [(object) ['name' => 'Collect gems']], 'reward' => []],
            [],
            5,
            ['form' => 'f', 'cancel' => 'c'],
            'id',
            'DESC'
        );

        $this->assertTrue($ctx['has_quest_requirement']);
        $this->assertFalse($ctx['has_quest_reward']);
        $this->assertFalse($ctx['has_trades']);
    }

    /**
     * Quest and trade names are plain text with the string filters applied, so the template's own
     * escaping is the only one.
     */
    public function test_build_context_names_are_filtered_plain_text(): void {
        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
        \filter_manager::reset_caches();
        $multilang = '<span lang="en" class="multilang">Key swap</span>'
            . '<span lang="pt_br" class="multilang">Troca de chave</span>';

        // The "strip all tags from strings" site setting changes how format_string() treats "&".
        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            \core\di::reset_container();

            $ctx = item_disable_confirm::build_context(
                'Gem',
                ['requirement' => [(object) ['name' => 'Tom & Jerry']], 'reward' => [(object) ['name' => $multilang]]],
                [(object) ['name' => 'Poção & Elixir']],
                5,
                ['form' => 'f', 'cancel' => 'c'],
                'id',
                'DESC'
            );

            $this->assertSame([['name' => 'Tom & Jerry']], $ctx['quest_requirement_list']);
            $this->assertSame([['name' => 'Key swap']], $ctx['quest_reward_list']);
            $this->assertSame([['name' => 'Poção & Elixir']], $ctx['trades_list']);
        }
    }
}
