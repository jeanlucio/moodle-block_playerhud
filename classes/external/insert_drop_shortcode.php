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
 * Web service to insert a drop shortcode into a course module field.
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
 * External API to insert a drop shortcode into a course module field.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class insert_drop_shortcode extends external_api {
    /**
     * Define parameters for insert_drop_shortcode.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'dropid'     => new external_value(PARAM_INT, 'Drop ID'),
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'field'      => new external_value(PARAM_ALPHANUMEXT, 'Field name (intro or content)'),
            'position'   => new external_value(PARAM_ALPHA, 'Position: top or bottom'),
            'mode'       => new external_value(
                PARAM_ALPHA,
                'Rendering mode for the inserted shortcode: card, text or image',
                VALUE_DEFAULT,
                'card'
            ),
            'customtext' => new external_value(
                PARAM_TEXT,
                "Custom label for mode=text (e.g. an emoji, to blend into the field's own content)",
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Insert a drop shortcode into a course module field.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $dropid Drop ID.
     * @param int $cmid Course module ID.
     * @param string $field Field name (intro or content).
     * @param string $position Position: top or bottom.
     * @param string $mode Rendering mode: card, text or image.
     * @param string $customtext Custom label for mode=text.
     * @return array Result structure.
     */
    public static function execute(
        $instanceid,
        $courseid,
        $dropid,
        $cmid,
        $field,
        $position,
        $mode = 'card',
        $customtext = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'dropid'     => $dropid,
            'cmid'       => $cmid,
            'field'      => $field,
            'position'   => $position,
            'mode'       => $mode,
            'customtext' => $customtext,
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

        // Validate position.
        if (!in_array($params['position'], ['top', 'bottom'])) {
            $params['position'] = 'top';
        }

        // Validate mode.
        if (!in_array($params['mode'], ['card', 'text', 'image'], true)) {
            $params['mode'] = 'card';
        }

        // Validate field name (only allow safe identifiers).
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

        // Prevent duplicate insertion.
        $shortcode = '[PLAYERHUD_DROP code=' . $drop->code;
        if ($params['mode'] !== 'card') {
            $shortcode .= ' mode=' . $params['mode'];
            // The filter's own text= regex has no escaping, so quote characters would prematurely
            // truncate the match — stripped rather than escaped, since they add nothing to a
            // custom label anyway (typically a short word or a single emoji).
            $customtext = str_replace(['"', "'"], '', $params['customtext']);
            if ($params['mode'] === 'text' && $customtext !== '') {
                $shortcode .= ' text="' . $customtext . '"';
            }
        }
        $shortcode .= ']';
        if (strpos($currentval, 'code=' . $drop->code) !== false) {
            return ['success' => false, 'message' => get_string('distribute_already_inserted', 'block_playerhud')];
        }

        // Insert at chosen position.
        if ($params['position'] === 'top') {
            $newval = $shortcode . "\n" . $currentval;
        } else {
            $newval = rtrim($currentval) . "\n" . $shortcode;
        }

        // Save the new value.
        $DB->set_field($modname, $params['field'], $newval, ['id' => $cm->instance]);

        // Update timemodified if the column exists.
        if (isset($columns['timemodified'])) {
            $DB->set_field($modname, 'timemodified', time(), ['id' => $cm->instance]);
        }

        // Rebuild course cache so the change is visible immediately.
        rebuild_course_cache($params['courseid'], true);

        // The drop's own name is its "Localização/Nome" in the drops management table — renaming
        // it to the activity it just landed in (instead of leaving whatever it was created with)
        // is what makes that table useful for finding a drop later. Never shown to students: the
        // shortcode renders the item's own name, not this. Applies here rather than in each
        // caller (the wizard's auto-distribute step and the manual "Distribuir Drops" screen both
        // go through this same method) so neither can forget it.
        $DB->update_record('block_playerhud_drops', (object) [
            'id' => $drop->id,
            'name' => format_string($cm->name),
            'timemodified' => time(),
        ]);

        return ['success' => true, 'message' => get_string('distribute_inserted', 'block_playerhud')];
    }

    /**
     * Define return structure for insert_drop_shortcode.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message'),
        ]);
    }
}
