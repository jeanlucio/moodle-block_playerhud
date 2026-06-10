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
 * Web service to generate an RPG class via AI (Class Oracle).
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
 * External API to generate an RPG class via AI (Class Oracle) and save it.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_class_oracle extends external_api {
    /**
     * Parameters for generate_class_oracle.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'theme'      => new external_value(PARAM_TEXT, 'Theme or description for the class'),
        ]);
    }

    /**
     * Generate an RPG class via AI (Class Oracle) and save it.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid   Course ID.
     * @param string $theme   Theme or description for the class.
     * @return array Result structure.
     */
    public static function execute(int $instanceid, int $courseid, string $theme): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'theme'      => $theme,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        try {
            $generator = new \block_playerhud\ai\generator($params['instanceid']);
            $result    = $generator->generate_class($params['theme']);

            $log                  = new \stdClass();
            $log->blockinstanceid = $params['instanceid'];
            $log->userid          = $USER->id;
            $log->action_type     = 'class';
            $log->object_name     = $result['class_name'];
            $log->ai_provider     = $result['provider'];
            $log->timecreated     = time();
            $DB->insert_record('block_playerhud_ai_logs', $log);

            return [
                'success'    => true,
                'class_name' => $result['class_name'],
                'provider'   => $result['provider'],
                'message'    => '',
            ];
        } catch (\Exception $e) {
            return [
                'success'    => false,
                'class_name' => '',
                'provider'   => '',
                'message'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Return structure for generate_class_oracle.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Success status'),
            'class_name' => new external_value(PARAM_TEXT, 'Name of the generated class', VALUE_DEFAULT, ''),
            'provider'   => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'    => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
