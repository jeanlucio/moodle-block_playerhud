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
 * Web service to list recent gamification wizard runs available for rollback.
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
use core_external\external_multiple_structure;
use context_block;

/**
 * External API that lists the wizard's recent still-undoable runs.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_list_runs extends external_api {
    /** @var int Maximum number of runs returned. */
    private const LIMIT = 10;

    /**
     * Define parameters for wizard_list_runs.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Lists the wizard's recent still-undoable runs for this instance.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @return array Result structure.
     */
    public static function execute(int $instanceid, int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $runs = \block_playerhud\local\wizard::get_active_runs($params['instanceid'], self::LIMIT);

        $labels = [
            'block_playerhud_items' => get_string('wizard_history_items', 'block_playerhud'),
            'block_playerhud_quests' => get_string('wizard_history_quests', 'block_playerhud'),
            'block_playerhud_classes' => get_string('wizard_history_classes', 'block_playerhud'),
            'block_playerhud_chapters' => get_string('wizard_history_chapters', 'block_playerhud'),
        ];

        $result = [];
        foreach ($runs as $run) {
            $parts = [];
            foreach ($labels as $table => $label) {
                if (!empty($run->counts[$table])) {
                    $parts[] = $run->counts[$table] . ' ' . $label;
                }
            }

            $result[] = [
                'runid' => $run->id,
                'timecreated' => userdate($run->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'summary' => implode(', ', $parts),
            ];
        }

        return ['runs' => $result];
    }

    /**
     * Return structure for wizard_list_runs.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runs' => new external_multiple_structure(
                new external_single_structure([
                    'runid' => new external_value(PARAM_INT, 'Wizard run ID'),
                    'timecreated' => new external_value(PARAM_TEXT, 'Formatted creation date'),
                    'summary' => new external_value(PARAM_TEXT, 'Human-readable summary of what was created'),
                ])
            ),
        ]);
    }
}
