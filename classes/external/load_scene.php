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
 * Web service to load the current or starting scene for a chapter.
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
 * External API to load the current or starting scene for a chapter.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class load_scene extends external_api {
    /**
     * Parameters for load_scene.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'chapterid'  => new external_value(PARAM_INT, 'Chapter ID'),
            'preview'    => new external_value(PARAM_BOOL, 'Preview mode (no progress saved)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Load the current or starting scene for a chapter.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $chapterid Chapter ID.
     * @param bool $preview Preview mode flag.
     * @return array Scene data.
     */
    public static function execute(int $instanceid, int $courseid, int $chapterid, bool $preview = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'chapterid'  => $chapterid,
            'preview'    => $preview,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:view', $context);

        if ($params['preview']) {
            require_capability('block/playerhud:manage', $context);
            return \block_playerhud\story_manager::load_preview_start(
                $params['instanceid'],
                $USER->id,
                $params['chapterid']
            );
        }

        return \block_playerhud\story_manager::load_scene(
            $params['instanceid'],
            $USER->id,
            $params['chapterid']
        );
    }

    /**
     * Return structure for load_scene.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return scene_helper::node_returns();
    }
}
