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
 * Web service to create an infinite PlayerCoin drop in the course news forum.
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
 * External API to create an infinite drop for the PlayerCoin in the course news forum.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup_playercoin_drop extends external_api {
    /**
     * Parameters for setup_playercoin_drop.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'itemid'     => new external_value(PARAM_INT, 'PlayerCoin item ID'),
        ]);
    }

    /**
     * Create an infinite drop for the PlayerCoin in the course news forum.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $itemid PlayerCoin item ID.
     * @return array
     */
    public static function execute(int $instanceid, int $courseid, int $itemid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'itemid'     => $itemid,
        ]);
        $instanceid = $params['instanceid'];
        $courseid   = $params['courseid'];
        $itemid     = $params['itemid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $coursecontext = \context_course::instance($courseid);
        require_capability('moodle/course:manageactivities', $coursecontext);

        // Verify the block instance actually belongs to the supplied course.
        $blockcoursectx = $context->get_course_context(false);
        if (!$blockcoursectx || (int) $blockcoursectx->instanceid !== $courseid) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $DB->get_record(
            'block_playerhud_items',
            ['id' => $itemid, 'blockinstanceid' => $instanceid],
            'id',
            MUST_EXIST
        );

        // Find the news forum (Avisos) in this course.
        $sql = "SELECT f.id, f.intro, cm.id AS cmid
                  FROM {forum} f
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = 'forum'
                   AND f.course = :courseid
                   AND f.type = 'news'";
        $forums = $DB->get_records_sql($sql, ['courseid' => $courseid], 0, 1);
        $forum = $forums ? reset($forums) : null;

        if (!$forum) {
            return [
                'success' => false,
                'message' => get_string('playercoin_drop_noforum', 'block_playerhud'),
            ];
        }

        $code = \block_playerhud\utils::generate_drop_code($instanceid);

        $dropid = $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid'          => $itemid,
            'name'            => get_string('playercoin_drop_name', 'block_playerhud'),
            'maxusage'        => 0,
            'respawntime'     => 3600,
            'code'            => $code,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $shortcode = '[PLAYERHUD_DROP code=' . $code . ']';
        $newintro  = $shortcode . ($forum->intro ? '<br>' . $forum->intro : '');
        $DB->set_field('forum', 'intro', $newintro, ['id' => $forum->id]);

        return [
            'success' => true,
            'message' => get_string('playercoin_drop_created', 'block_playerhud'),
            'dropid'  => (int) $dropid,
            'cmid'    => (int) $forum->cmid,
        ];
    }

    /**
     * Return structure for setup_playercoin_drop.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the drop was created'),
            'message' => new external_value(PARAM_RAW, 'Result or error message'),
            'dropid'  => new external_value(PARAM_INT, 'The new drop ID', VALUE_DEFAULT, 0),
            'cmid'    => new external_value(PARAM_INT, 'The news forum course module ID', VALUE_DEFAULT, 0),
        ]);
    }
}
