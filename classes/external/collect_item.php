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
 * Web service to collect an item via a drop.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External API to collect an item via a drop.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_item extends external_api {
    /**
     * Define parameters for collect_item.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block Instance ID'),
            'dropid' => new external_value(PARAM_INT, 'Drop ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Collect an item via AJAX.
     *
     * @param int $instanceid Block Instance ID.
     * @param int $dropid Drop ID.
     * @param int $courseid Course ID.
     * @return array Result data structure.
     */
    public static function execute($instanceid, $dropid, $courseid): array {
        global $USER;

        // Validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'dropid' => $dropid,
            'courseid' => $courseid,
        ]);

        $context = \context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:view', $context);

        try {
            // Call the centralized logic in Game class.
            $result = \block_playerhud\game::process_collection(
                $params['instanceid'],
                $params['dropid'],
                $USER->id
            );
            return $result;
        } catch (\Exception $e) {
            // Return failure structure but valid according to returns definition.
            return [
                'success' => false,
                'message' => $e->getMessage(),
                // Default values for optional fields to avoid warnings.
                'cooldown_deadline' => 0,
                'limit_reached' => false,
            ];
        }
    }

    /**
     * Define return structure for collect_item.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message'),
            'cooldown_deadline' => new external_value(PARAM_INT, 'Timestamp for cooldown', VALUE_OPTIONAL),
            'limit_reached' => new external_value(PARAM_BOOL, 'If drop limit reached', VALUE_OPTIONAL),
            'game_data' => new external_single_structure([
                'currentxp' => new external_value(PARAM_INT, 'Current XP', VALUE_OPTIONAL),
                'level' => new external_value(PARAM_INT, 'Level', VALUE_OPTIONAL),
                'max_levels' => new external_value(PARAM_INT, 'Max Levels', VALUE_OPTIONAL),
                'xp_target' => new external_value(PARAM_INT, 'XP Target', VALUE_OPTIONAL),
                'progress' => new external_value(PARAM_INT, 'Progress Percent', VALUE_OPTIONAL),
                'level_class' => new external_value(PARAM_TEXT, 'CSS Class', VALUE_OPTIONAL),
                'is_win' => new external_value(PARAM_BOOL, 'Is Win', VALUE_OPTIONAL),
                'leveled_up' => new external_value(PARAM_BOOL, 'If this collection raised the level', VALUE_OPTIONAL),
            ], 'Game Stats', VALUE_OPTIONAL),
            'item_data' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Item Name', VALUE_OPTIONAL),
                'xp' => new external_value(PARAM_INT, 'XP Value', VALUE_OPTIONAL),
                'image' => new external_value(PARAM_RAW, 'Image URL or Emoji', VALUE_OPTIONAL),
                'isimage' => new external_value(PARAM_INT, 'Is Image Flag', VALUE_OPTIONAL),
                'description' => new external_value(PARAM_RAW, 'Description', VALUE_OPTIONAL),
                'date' => new external_value(PARAM_TEXT, 'Date formatted', VALUE_OPTIONAL),
                'timestamp' => new external_value(PARAM_INT, 'Timestamp', VALUE_OPTIONAL),
            ], 'Item Details', VALUE_OPTIONAL),
        ]);
    }
}
