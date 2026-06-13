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
 * Shared return structure for story scene web services.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Provides the node return structure shared by load_scene and make_choice.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scene_helper {
    /**
     * Shared return structure for node data (used by load_scene, make_choice and previews).
     *
     * @return external_single_structure
     */
    public static function node_returns(): external_single_structure {
        return new external_single_structure([
            'node' => new external_single_structure([
                'content' => new external_value(PARAM_RAW, 'Scene HTML content'),
                'choices' => new external_multiple_structure(
                    new external_single_structure([
                        'id'               => new external_value(PARAM_INT, 'Choice ID'),
                        'text'             => new external_value(PARAM_TEXT, 'Choice label text'),
                        'btnclass'         => new external_value(PARAM_TEXT, 'Bootstrap button class'),
                        'disabled'         => new external_value(PARAM_BOOL, 'Whether the button is disabled'),
                        'req_class_name'   => new external_value(PARAM_TEXT, 'Required class name, empty if none'),
                        'req_class_met'    => new external_value(PARAM_BOOL, 'Whether class requirement is met'),
                        'req_karma_min'    => new external_value(PARAM_INT, 'Minimum karma required, 0 if none'),
                        'req_karma_met'    => new external_value(PARAM_BOOL, 'Whether karma requirement is met'),
                        'cost_item_name'   => new external_value(PARAM_TEXT, 'Cost item name, empty if none'),
                        'cost_item_qty'    => new external_value(PARAM_INT, 'Cost item quantity'),
                        'cost_item_met'    => new external_value(PARAM_BOOL, 'Whether item cost is met'),
                        'str_req_class'    => new external_value(PARAM_TEXT, 'Localised class requirement label'),
                        'str_req_karma'    => new external_value(PARAM_TEXT, 'Localised karma requirement label'),
                        'str_low_karma'    => new external_value(PARAM_TEXT, 'Localised low karma warning'),
                        'str_cost_item'    => new external_value(PARAM_TEXT, 'Localised item cost label'),
                        'str_missing_item' => new external_value(PARAM_TEXT, 'Localised missing item message'),
                        'is_preview'       => new external_value(PARAM_BOOL, 'Preview mode flag'),
                    ])
                ),
            ], 'Node data', VALUE_OPTIONAL),
            'finished'  => new external_value(PARAM_BOOL, 'Chapter finished flag', VALUE_OPTIONAL),
            'chapterid' => new external_value(PARAM_INT, 'Chapter ID', VALUE_OPTIONAL),
            'message'   => new external_value(PARAM_TEXT, 'Completion message', VALUE_OPTIONAL),
            'events'    => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_TEXT, 'Event type'),
                    'msg'  => new external_value(PARAM_TEXT, 'Event message'),
                ]),
                'Game events (karma, class change, item loss)',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
