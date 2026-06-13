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
 * Web service to generate a story chapter via AI.
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
use context_block;

/**
 * External API to generate a story chapter with nodes and choices via AI and save it.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_story extends external_api {
    /**
     * Parameters for generate_story.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'theme'      => new external_value(PARAM_TEXT, 'Theme or setting for the story'),
            'karmagain' => new external_value(PARAM_INT, 'Max reputation gain to distribute', VALUE_DEFAULT, 0),
            'karmaloss' => new external_value(PARAM_INT, 'Max reputation loss to distribute', VALUE_DEFAULT, 0),
            'itemid'    => new external_value(PARAM_INT, 'Item ID for cost distribution', VALUE_DEFAULT, 0),
            'itemqty'   => new external_value(PARAM_INT, 'Total item quantity to distribute', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Generate a story chapter with nodes and choices via AI and save it.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid   Course ID.
     * @param string $theme   Theme or setting for the story.
     * @param int $karmagain  Max reputation gain to distribute across choices.
     * @param int $karmaloss  Max reputation loss to distribute across choices.
     * @param int $itemid     Item ID for choice cost distribution.
     * @param int $itemqty    Total item quantity to distribute across choices.
     * @return array Result structure.
     */
    public static function execute(
        int $instanceid,
        int $courseid,
        string $theme,
        int $karmagain = 0,
        int $karmaloss = 0,
        int $itemid = 0,
        int $itemqty = 0
    ): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'theme'      => $theme,
            'karmagain' => $karmagain,
            'karmaloss' => $karmaloss,
            'itemid'    => $itemid,
            'itemqty'   => $itemqty,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        try {
            $generator = new \block_playerhud\ai\generator($params['instanceid']);
            $options   = [
                'karma_gain' => $params['karmagain'],
                'karma_loss' => $params['karmaloss'],
                'item_id'    => $params['itemid'],
                'item_qty'   => $params['itemqty'],
            ];
            $result = $generator->generate_story($params['theme'], $options);

            $log                  = new \stdClass();
            $log->blockinstanceid = $params['instanceid'];
            $log->userid          = $USER->id;
            $log->action_type     = 'story';
            $log->object_name     = $result['chapter_title'];
            $log->ai_provider     = $result['provider'];
            $log->timecreated     = time();
            $DB->insert_record('block_playerhud_ai_logs', $log);

            return [
                'success'       => true,
                'chapter_title' => $result['chapter_title'],
                'provider'      => $result['provider'],
                'message'       => '',
            ];
        } catch (\Exception $e) {
            return [
                'success'       => false,
                'chapter_title' => '',
                'provider'      => '',
                'message'       => $e->getMessage(),
            ];
        }
    }

    /**
     * Return structure for generate_story.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'       => new external_value(PARAM_BOOL, 'Success status'),
            'chapter_title' => new external_value(PARAM_TEXT, 'Title of the generated chapter', VALUE_DEFAULT, ''),
            'provider'      => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'       => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
