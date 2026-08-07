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

use block_playerhud\local\wizard;
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
        int $instanceid,
        int $courseid,
        int $dropid,
        int $cmid,
        string $field,
        string $position,
        string $mode = 'card',
        string $customtext = ''
    ): array {
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

        self::require_manage_and_course_match($params['instanceid'], $params['courseid']);

        $columnscache = [];
        return self::insert_one(
            $params['instanceid'],
            $params['courseid'],
            $params['dropid'],
            $params['cmid'],
            $params['field'],
            $params['position'],
            $params['mode'],
            $params['customtext'],
            $columnscache,
            false
        );
    }

    /**
     * Insert shortcodes for several drops in one call.
     *
     * Same authorisation and per-drop logic as {@see execute()}, but checked once for the
     * whole batch instead of once per drop, {@see \core_courseformat\modinfo::get_columns()}
     * results are reused across drops targeting the same activity type, and
     * rebuild_course_cache() — the dominant cost of the loop — runs once at the end instead of
     * once per drop. Used by the wizard's auto-distribute and Knowledge Pill steps
     * (wizard_generate.php), which each insert many drops in a single run.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $items Each entry: ['dropid', 'cmid', 'field', 'position', 'mode', 'customtext'].
     *                      'mode' and 'customtext' are optional per entry (same defaults as execute()).
     * @return array Result structure per entry, keyed by the same numeric index as $items.
     */
    public static function execute_batch(int $instanceid, int $courseid, array $items): array {
        self::require_manage_and_course_match($instanceid, $courseid);

        $columnscache = [];
        $results = [];
        $anysucceeded = false;

        foreach ($items as $key => $item) {
            $result = self::insert_one(
                $instanceid,
                $courseid,
                (int) $item['dropid'],
                (int) $item['cmid'],
                (string) $item['field'],
                (string) $item['position'],
                (string) ($item['mode'] ?? 'card'),
                (string) ($item['customtext'] ?? ''),
                $columnscache,
                true
            );
            $results[$key] = $result;
            if ($result['success']) {
                $anysucceeded = true;
            }
        }

        if ($anysucceeded) {
            rebuild_course_cache($courseid, true);
        }

        return $results;
    }

    /**
     * Validates the caller can manage this block instance and edit activities in its course,
     * and that the instance genuinely belongs to that course. Shared by execute() and
     * execute_batch() so a batch call checks this once instead of once per drop.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @return void
     */
    protected static function require_manage_and_course_match(int $instanceid, int $courseid): void {
        $context = context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $coursecontext = \context_course::instance($courseid);
        require_capability('moodle/course:manageactivities', $coursecontext);

        wizard::require_course_matches_instance($context, $courseid);
    }

    /**
     * Core single-drop insertion logic, shared by execute() and execute_batch().
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $dropid Drop ID.
     * @param int $cmid Course module ID.
     * @param string $field Field name (intro or content).
     * @param string $position Position: top or bottom.
     * @param string $mode Rendering mode: card, text or image.
     * @param string $customtext Custom label for mode=text.
     * @param array $columnscache Column list per modname, reused across calls in the same batch.
     * @param bool $deferrebuild When true, the caller is responsible for calling
     *                           rebuild_course_cache() itself once, after every drop is inserted.
     * @return array Result structure.
     */
    protected static function insert_one(
        int $instanceid,
        int $courseid,
        int $dropid,
        int $cmid,
        string $field,
        string $position,
        string $mode,
        string $customtext,
        array &$columnscache,
        bool $deferrebuild
    ): array {
        global $DB;

        // Validate position.
        if (!in_array($position, ['top', 'bottom'])) {
            $position = 'top';
        }

        // Validate mode.
        if (!in_array($mode, ['card', 'text', 'image'], true)) {
            $mode = 'card';
        }

        // Validate field name (only allow safe identifiers).
        if (!in_array($field, ['intro', 'content'])) {
            return ['success' => false, 'message' => get_string('distribute_err_field', 'block_playerhud')];
        }

        // Load the drop and verify it belongs to this block instance.
        $drop = $DB->get_record_sql(
            "SELECT d.id, d.code, d.itemid
               FROM {block_playerhud_drops} d
               JOIN {block_playerhud_items} i ON d.itemid = i.id
              WHERE d.id = :dropid AND i.blockinstanceid = :instanceid",
            ['dropid' => $dropid, 'instanceid' => $instanceid]
        );

        if (!$drop) {
            return ['success' => false, 'message' => get_string('invalidrecord', 'error')];
        }

        // Load the course module.
        $modinfo = get_fast_modinfo($courseid);
        try {
            $cm = $modinfo->get_cm($cmid);
        } catch (\moodle_exception $e) {
            return ['success' => false, 'message' => get_string('invalidrecord', 'error')];
        }

        $modname = $cm->modname;

        // Verify the field exists in the module table. Cached per modname so a batch of many
        // drops targeting the same activity type (e.g. several 'page' modules) only looks
        // this up once.
        if (!isset($columnscache[$modname])) {
            $columnscache[$modname] = $DB->get_columns($modname);
        }
        $columns = $columnscache[$modname];
        if (!isset($columns[$field])) {
            return ['success' => false, 'message' => get_string('distribute_err_field', 'block_playerhud')];
        }

        // Read current field value.
        $currentval = $DB->get_field($modname, $field, ['id' => $cm->instance]);
        if ($currentval === false) {
            $currentval = '';
        }

        // Prevent duplicate insertion.
        $shortcode = '[PLAYERHUD_DROP code=' . $drop->code;
        if ($mode !== 'card') {
            $shortcode .= ' mode=' . $mode;
            // The filter's own text= regex has no escaping, so quote characters would prematurely
            // truncate the match — stripped rather than escaped, since they add nothing to a
            // custom label anyway (typically a short word or a single emoji).
            $customtext = str_replace(['"', "'"], '', $customtext);
            if ($mode === 'text' && $customtext !== '') {
                $shortcode .= ' text="' . $customtext . '"';
            }
        }
        $shortcode .= ']';
        if (strpos($currentval, 'code=' . $drop->code) !== false) {
            return ['success' => false, 'message' => get_string('distribute_already_inserted', 'block_playerhud')];
        }

        // Insert at chosen position.
        if ($position === 'top') {
            $newval = $shortcode . "\n" . $currentval;
        } else {
            $newval = rtrim($currentval) . "\n" . $shortcode;
        }

        // Save the new value.
        $DB->set_field($modname, $field, $newval, ['id' => $cm->instance]);

        // Update timemodified if the column exists.
        if (isset($columns['timemodified'])) {
            $DB->set_field($modname, 'timemodified', time(), ['id' => $cm->instance]);
        }

        // Rebuild course cache so the change is visible immediately. Deferred to a single
        // call after the whole batch when called from execute_batch().
        if (!$deferrebuild) {
            rebuild_course_cache($courseid, true);
        }

        // The drop's own name is its "Location / Name" in the drops management table — renaming
        // it to the activity it just landed in (instead of leaving whatever it was created with)
        // is what makes that table useful for finding a drop later. Never shown to students: the
        // shortcode renders the item's own name, not this. Applies here rather than in each
        // caller (the wizard's auto-distribute step and the manual "Distribute Drops" screen both
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
