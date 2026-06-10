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
 * Web service to return the full story recap HTML for a completed chapter.
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
 * External API to return the full story recap HTML for a completed chapter.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_recap extends external_api {
    /**
     * Parameters for load_recap.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'chapterid'  => new external_value(PARAM_INT, 'Chapter ID'),
        ]);
    }

    /**
     * Return the full story recap HTML for a completed chapter.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $chapterid Chapter ID.
     * @return array Recap HTML.
     */
    public static function execute(int $instanceid, int $courseid, int $chapterid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'chapterid'  => $chapterid,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:view', $context);

        $html = \block_playerhud\story_manager::load_recap(
            $params['instanceid'],
            $USER->id,
            $params['chapterid']
        );

        return ['html' => $html];
    }

    /**
     * Return structure for load_recap.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Full story recap HTML'),
        ]);
    }
}
