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
 * Builds the template context for the item-disable confirmation screen.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

/**
 * Prepares the confirmation context shown before disabling an item that quests or trades
 * depend on.
 *
 * Pure builder: it receives already-resolved values (no DB, no URL building) and returns the
 * array consumed by the manage_item_disable_confirm template.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_disable_confirm {
    /**
     * Builds the template context for the confirmation screen.
     *
     * @param string $heading Pre-formatted heading (the item name).
     * @param array $questimpact 'requirement' and 'reward' quest lists (each entry with ->name).
     * @param \stdClass[] $trades Trades that use the item (each with ->name).
     * @param int $itemid The item being disabled.
     * @param array $urls URL strings keyed 'form' and 'cancel'.
     * @param string $sort Current sort column, carried through the form.
     * @param string $dir Current sort direction, carried through the form.
     * @return array The template context.
     */
    public static function build_context(
        string $heading,
        array $questimpact,
        array $trades,
        int $itemid,
        array $urls,
        string $sort,
        string $dir
    ): array {
        $names = static fn(array $records): array => array_values(array_map(
            static fn($record) => ['name' => $record->name],
            $records
        ));
        $requirement = $names($questimpact['requirement'] ?? []);
        $reward = $names($questimpact['reward'] ?? []);
        $tradelist = $names($trades);

        return [
            'heading'                   => $heading,
            'has_quest_requirement'     => !empty($requirement),
            'quest_requirement_warning' => get_string('item_disable_quest_requirement', 'block_playerhud'),
            'quest_requirement_list'    => $requirement,
            'has_quest_reward'          => !empty($reward),
            'quest_reward_warning'      => get_string('item_disable_quest_reward', 'block_playerhud'),
            'quest_reward_list'         => $reward,
            'has_trades'                => !empty($tradelist),
            'trades_warning'            => get_string('item_disable_trades', 'block_playerhud'),
            'trades_list'               => $tradelist,
            'form_action'               => $urls['form'],
            'sesskey'                   => sesskey(),
            'action'                    => 'toggle',
            'item_id'                   => $itemid,
            'sort'                      => $sort,
            'dir'                       => $dir,
            'cancel_url'                => $urls['cancel'],
            'str_cancel'                => get_string('cancel'),
            'confirm_label'             => get_string('item_disable_confirm', 'block_playerhud'),
        ];
    }
}
