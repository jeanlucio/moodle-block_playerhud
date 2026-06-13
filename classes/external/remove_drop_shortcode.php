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
 * Web service to remove a drop shortcode from a course module field.
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
 * External API to remove a drop shortcode from a course module field.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_drop_shortcode extends external_api {
    /**
     * Define parameters for remove_drop_shortcode.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'dropid'     => new external_value(PARAM_INT, 'Drop ID'),
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'field'      => new external_value(PARAM_ALPHA, 'Field name (intro or content)'),
        ]);
    }

    /**
     * Remove a drop shortcode from a course module field.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $dropid Drop ID.
     * @param int $cmid Course module ID.
     * @param string $field Field name (intro or content).
     * @return array Result structure.
     */
    public static function execute($instanceid, $courseid, $dropid, $cmid, $field): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'dropid'     => $dropid,
            'cmid'       => $cmid,
            'field'      => $field,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $coursecontext = \context_course::instance($params['courseid']);
        require_capability('moodle/course:manageactivities', $coursecontext);

        // Verify the block instance actually belongs to the supplied course.
        $blockcoursectx = $context->get_course_context(false);
        if (!$blockcoursectx || (int) $blockcoursectx->instanceid !== (int) $params['courseid']) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        // Validate field name.
        if (!in_array($params['field'], ['intro', 'content'])) {
            return ['success' => false, 'message' => get_string('distribute_err_field', 'block_playerhud')];
        }

        // Load the drop and verify it belongs to this block instance.
        $drop = $DB->get_record_sql(
            "SELECT d.id, d.code, d.itemid
               FROM {block_playerhud_drops} d
               JOIN {block_playerhud_items} i ON d.itemid = i.id
              WHERE d.id = :dropid AND i.blockinstanceid = :instanceid",
            ['dropid' => $params['dropid'], 'instanceid' => $params['instanceid']]
        );

        if (!$drop) {
            return ['success' => false, 'message' => get_string('invalidrecord', 'error')];
        }

        // Load the course module.
        $modinfo = get_fast_modinfo($params['courseid']);
        try {
            $cm = $modinfo->get_cm($params['cmid']);
        } catch (\moodle_exception $e) {
            return ['success' => false, 'message' => get_string('invalidrecord', 'error')];
        }

        $modname = $cm->modname;

        // Verify the field exists in the module table.
        $columns = $DB->get_columns($modname);
        if (!isset($columns[$params['field']])) {
            return ['success' => false, 'message' => get_string('distribute_err_field', 'block_playerhud')];
        }

        // Read current field value.
        $currentval = $DB->get_field($modname, $params['field'], ['id' => $cm->instance]);
        if ($currentval === false) {
            $currentval = '';
        }

        $shortcode = '[PLAYERHUD_DROP code=' . $drop->code . ']';

        // Shortcode not present — nothing to remove.
        if (strpos($currentval, $shortcode) === false) {
            return ['success' => true, 'message' => ''];
        }

        // Remove the shortcode along with any immediately adjacent newlines.
        $newval = str_replace(
            ["\n" . $shortcode . "\n", "\n" . $shortcode, $shortcode . "\n", $shortcode],
            ['', '', '', ''],
            $currentval
        );
        $newval = trim($newval);

        $DB->set_field($modname, $params['field'], $newval, ['id' => $cm->instance]);

        if (isset($columns['timemodified'])) {
            $DB->set_field($modname, 'timemodified', time(), ['id' => $cm->instance]);
        }

        rebuild_course_cache($params['courseid'], true);

        return ['success' => true, 'message' => ''];
    }

    /**
     * Define return structure for remove_drop_shortcode.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message', VALUE_DEFAULT, ''),
        ]);
    }
}
