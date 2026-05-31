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

namespace block_playerhud;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use context_block;

/**
 * External API for PlayerHUD AI generation.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Define parameters for generate_ai_content.
     *
     * @return external_function_parameters
     */
    public static function generate_ai_content_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'theme' => new external_value(PARAM_TEXT, 'Theme for generation'),
            'xp' => new external_value(PARAM_INT, 'XP value', VALUE_DEFAULT, -1),
            'amount' => new external_value(PARAM_INT, 'Amount of items', VALUE_DEFAULT, 1),
            'create_drop' => new external_value(PARAM_BOOL, 'Create drop location?', VALUE_DEFAULT, false),
            'drop_location' => new external_value(PARAM_TEXT, 'Drop location name', VALUE_DEFAULT, ''),
            'drop_max' => new external_value(PARAM_INT, 'Max usage count', VALUE_DEFAULT, 0),
            'drop_time' => new external_value(PARAM_INT, 'Respawn time in minutes', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Define parameters for collect_item.
     */
    public static function collect_item_parameters() {
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
    public static function collect_item($instanceid, $dropid, $courseid) {
        global $USER;

        // Validation.
        $params = self::validate_parameters(self::collect_item_parameters(), [
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
     */
    public static function collect_item_returns() {
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

    /**
     * Execute AI generation logic.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $theme Theme text.
     * @param int $xp XP value.
     * @param int $amount Amount of items.
     * @param bool $createdrop Create drop flag.
     * @param string $droplocation Drop location name.
     * @param int $dropmax Max usage.
     * @param int $droptime Respawn time.
     * @return array Result structure.
     */
    public static function generate_ai_content(
        $instanceid,
        $courseid,
        $theme,
        $xp = -1,
        $amount = 1,
        $createdrop = false,
        $droplocation = '',
        $dropmax = 0,
        $droptime = 0
    ) {
        global $DB, $USER;

        // 1. Validation.
        $params = self::validate_parameters(self::generate_ai_content_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'theme' => $theme,
            'xp' => $xp,
            'amount' => $amount,
            'create_drop' => $createdrop,
            'drop_location' => $droplocation,
            'drop_max' => $dropmax,
            'drop_time' => $droptime,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // 2. Logic (Balance Context).
        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $xpceiling = $xpperlevel * $maxlevels;

        $currenttotalxp = 0;
        $items = $DB->get_records('block_playerhud_items', [
            'blockinstanceid' => $params['instanceid'],
            'enabled' => 1,
        ]);

        if ($items) {
            // Preload all drops for this instance to avoid N+1 query problem.
            $sql = "SELECT d.id, d.itemid, d.maxusage
                      FROM {block_playerhud_drops} d
                      JOIN {block_playerhud_items} i ON d.itemid = i.id
                     WHERE i.blockinstanceid = :instanceid AND i.enabled = 1";
            $alldrops = $DB->get_records_sql($sql, ['instanceid' => $params['instanceid']]);

            // Group drops by itemid in memory.
            $dropsbyitem = [];
            foreach ($alldrops as $drop) {
                $dropsbyitem[$drop->itemid][] = $drop;
            }

            foreach ($items as $it) {
                if (!empty($dropsbyitem[$it->id])) {
                    foreach ($dropsbyitem[$it->id] as $drop) {
                        if ($drop->maxusage > 0) {
                            $currenttotalxp += ($it->xp * $drop->maxusage);
                        }
                    }
                } else {
                    $currenttotalxp += $it->xp;
                }
            }
        }

        $balancecontext = [
            'current_xp' => $currenttotalxp,
            'target_xp' => $xpceiling,
            'gap' => $xpceiling - $currenttotalxp,
            'qty' => $params['amount'],
        ];

        $extraoptions = [
            'drop_location' => $params['drop_location'],
            'drop_max' => $params['drop_max'],
            'drop_time' => $params['drop_time'],
            'balance_context' => $balancecontext,
        ];

        // 3. Generation.
        $generator = new \block_playerhud\ai\generator($params['instanceid']);

        try {
            $result = $generator->generate(
                'item',
                $params['theme'],
                $params['xp'],
                $params['create_drop'],
                $extraoptions,
                $params['amount']
            );

            if (!empty($result['created_items'])) {
                $logs = [];
                $now = time();
                foreach ($result['created_items'] as $itemname) {
                    $log = new \stdClass();
                    $log->blockinstanceid = $params['instanceid'];
                    $log->userid          = $USER->id;
                    $log->action_type     = 'item';
                    $log->object_name     = $itemname;
                    $log->ai_provider     = $result['provider'] ?? 'Unknown';
                    $log->timecreated     = $now;
                    $logs[] = $log;
                }
                $DB->insert_records('block_playerhud_ai_logs', $logs);
            }

            return [
                'success' => true,
                'message' => $result['message'] ?? '',
                'created_items' => $result['created_items'] ?? [],
                'item_name' => $result['item_name'] ?? '',
                'drop_code' => $result['drop_code'] ?? null,
                'warning_msg' => $result['warning_msg'] ?? null,
                'info_msg' => $result['info_msg'] ?? null,
                'provider' => $result['provider'] ?? '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'created_items' => [],
                'item_name' => '',
                'drop_code' => null,
                'warning_msg' => null,
                'info_msg' => null,
                'provider' => '',
            ];
        }
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function generate_ai_content_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message or error', VALUE_OPTIONAL),
            'created_items' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Item name'),
                'List of created items',
                VALUE_OPTIONAL
            ),
            'item_name' => new external_value(PARAM_TEXT, 'Name of first item', VALUE_OPTIONAL),
            'drop_code' => new external_value(PARAM_TEXT, 'Drop code if created', VALUE_OPTIONAL),
            'warning_msg' => new external_value(PARAM_RAW, 'Warning message', VALUE_OPTIONAL),
            'info_msg' => new external_value(PARAM_RAW, 'Info message', VALUE_OPTIONAL),
            'provider' => new external_value(PARAM_TEXT, 'AI Provider used', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Define parameters for insert_drop_shortcode.
     *
     * @return external_function_parameters
     */
    public static function insert_drop_shortcode_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'dropid'     => new external_value(PARAM_INT, 'Drop ID'),
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'field'      => new external_value(PARAM_ALPHANUMEXT, 'Field name (intro or content)'),
            'position'   => new external_value(PARAM_ALPHA, 'Position: top or bottom'),
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
     * @return array Result structure.
     */
    public static function insert_drop_shortcode($instanceid, $courseid, $dropid, $cmid, $field, $position) {
        global $DB;

        $params = self::validate_parameters(self::insert_drop_shortcode_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'dropid'     => $dropid,
            'cmid'       => $cmid,
            'field'      => $field,
            'position'   => $position,
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
        $shortcode = '[PLAYERHUD_DROP code=' . $drop->code . ']';
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

        return ['success' => true, 'message' => get_string('distribute_inserted', 'block_playerhud')];
    }

    /**
     * Define return structure for insert_drop_shortcode.
     *
     * @return external_single_structure
     */
    public static function insert_drop_shortcode_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message'),
        ]);
    }

    /**
     * Define parameters for remove_drop_shortcode.
     *
     * @return external_function_parameters
     */
    public static function remove_drop_shortcode_parameters() {
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
    public static function remove_drop_shortcode($instanceid, $courseid, $dropid, $cmid, $field) {
        global $DB;

        $params = self::validate_parameters(self::remove_drop_shortcode_parameters(), [
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
    public static function remove_drop_shortcode_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'message' => new external_value(PARAM_RAW, 'Message', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Shared return structure for node data (used by load_scene, make_choice and previews).
     *
     * @return external_single_structure
     */
    private static function node_returns(): external_single_structure {
        return new external_single_structure([
            'node' => new external_single_structure([
                'content' => new external_value(PARAM_RAW, 'Scene HTML content'),
                'choices' => new external_multiple_structure(
                    new external_single_structure([
                        'id'               => new external_value(PARAM_INT, 'Choice ID'),
                        'text'             => new external_value(PARAM_TEXT, 'Choice label text'),
                        'btnclass'         => new external_value(PARAM_TEXT, 'Bootstrap button class'),
                        'disabled'         => new external_value(PARAM_BOOL, 'Whether the button is disabled'),
                        'req_class_name'   => new external_value(PARAM_TEXT, 'Required class name, empty if none'),
                        'req_class_met'    => new external_value(PARAM_BOOL, 'Whether class requirement is met'),
                        'req_karma_min'    => new external_value(PARAM_INT, 'Minimum karma required, 0 if none'),
                        'req_karma_met'    => new external_value(PARAM_BOOL, 'Whether karma requirement is met'),
                        'cost_item_name'   => new external_value(PARAM_TEXT, 'Cost item name, empty if none'),
                        'cost_item_qty'    => new external_value(PARAM_INT, 'Cost item quantity'),
                        'cost_item_met'    => new external_value(PARAM_BOOL, 'Whether item cost is met'),
                        'str_req_class'    => new external_value(PARAM_TEXT, 'Localised class requirement label'),
                        'str_req_karma'    => new external_value(PARAM_TEXT, 'Localised karma requirement label'),
                        'str_low_karma'    => new external_value(PARAM_TEXT, 'Localised low karma warning'),
                        'str_cost_item'    => new external_value(PARAM_TEXT, 'Localised item cost label'),
                        'str_missing_item' => new external_value(PARAM_TEXT, 'Localised missing item message'),
                        'is_preview'       => new external_value(PARAM_BOOL, 'Preview mode flag'),
                    ])
                ),
            ], 'Node data', VALUE_OPTIONAL),
            'finished'  => new external_value(PARAM_BOOL, 'Chapter finished flag', VALUE_OPTIONAL),
            'chapterid' => new external_value(PARAM_INT, 'Chapter ID', VALUE_OPTIONAL),
            'message'   => new external_value(PARAM_TEXT, 'Completion message', VALUE_OPTIONAL),
            'events'    => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_TEXT, 'Event type'),
                    'msg'  => new external_value(PARAM_TEXT, 'Event message'),
                ]),
                'Game events (karma, class change, item loss)',
                VALUE_OPTIONAL
            ),
        ]);
    }

    /**
     * Parameters for load_scene.
     *
     * @return external_function_parameters
     */
    public static function load_scene_parameters(): external_function_parameters {
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
    public static function load_scene(int $instanceid, int $courseid, int $chapterid, bool $preview = false): array {
        global $USER;

        $params = self::validate_parameters(self::load_scene_parameters(), [
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
    public static function load_scene_returns(): external_single_structure {
        return self::node_returns();
    }

    /**
     * Parameters for make_choice.
     *
     * @return external_function_parameters
     */
    public static function make_choice_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'choiceid'   => new external_value(PARAM_INT, 'Choice ID'),
            'preview'    => new external_value(PARAM_BOOL, 'Preview mode (no progress saved)', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Process a player's choice and return the next scene.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $choiceid Choice ID.
     * @param bool $preview Preview mode flag.
     * @return array Next scene data.
     */
    public static function make_choice(int $instanceid, int $courseid, int $choiceid, bool $preview = false): array {
        global $USER;

        $params = self::validate_parameters(self::make_choice_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'choiceid'   => $choiceid,
            'preview'    => $preview,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:view', $context);

        if ($params['preview']) {
            require_capability('block/playerhud:manage', $context);
            return \block_playerhud\story_manager::preview_nav(
                $params['instanceid'],
                $USER->id,
                $params['choiceid']
            );
        }

        return \block_playerhud\story_manager::make_choice(
            $params['instanceid'],
            $USER->id,
            $params['choiceid']
        );
    }

    /**
     * Return structure for make_choice.
     *
     * @return external_single_structure
     */
    public static function make_choice_returns(): external_single_structure {
        return self::node_returns();
    }

    /**
     * Parameters for load_recap.
     *
     * @return external_function_parameters
     */
    public static function load_recap_parameters(): external_function_parameters {
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
    public static function load_recap(int $instanceid, int $courseid, int $chapterid): array {
        global $USER;

        $params = self::validate_parameters(self::load_recap_parameters(), [
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
    public static function load_recap_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Full story recap HTML'),
        ]);
    }

    /**
     * Parameters for generate_class_oracle.
     *
     * @return external_function_parameters
     */
    public static function generate_class_oracle_parameters(): external_function_parameters {
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
    public static function generate_class_oracle(int $instanceid, int $courseid, string $theme): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::generate_class_oracle_parameters(), [
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
    public static function generate_class_oracle_returns(): external_single_structure {
        return new external_single_structure([
            'success'    => new external_value(PARAM_BOOL, 'Success status'),
            'class_name' => new external_value(PARAM_TEXT, 'Name of the generated class', VALUE_DEFAULT, ''),
            'provider'   => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'    => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Parameters for generate_story.
     *
     * @return external_function_parameters
     */
    public static function generate_story_parameters(): external_function_parameters {
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
    public static function generate_story(
        int $instanceid,
        int $courseid,
        string $theme,
        int $karmagain = 0,
        int $karmaloss = 0,
        int $itemid = 0,
        int $itemqty = 0
    ): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::generate_story_parameters(), [
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
    public static function generate_story_returns(): external_single_structure {
        return new external_single_structure([
            'success'       => new external_value(PARAM_BOOL, 'Success status'),
            'chapter_title' => new external_value(PARAM_TEXT, 'Title of the generated chapter', VALUE_DEFAULT, ''),
            'provider'      => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'       => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Parameters for chat_message.
     *
     * @return external_function_parameters
     */
    public static function chat_message_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'history'    => new external_multiple_structure(
                new external_single_structure([
                    'role'    => new external_value(PARAM_ALPHA, 'Message role: user or assistant'),
                    'content' => new external_value(PARAM_TEXT, 'Message content'),
                ]),
                'Conversation history',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Sends a chat message to the Game Master AI and returns its reply.
     *
     * The full conversation history is received from the client (session-based,
     * never stored in the DB) so the AI has multi-turn context.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $history Conversation history [{role, content}].
     * @return array {reply, action, provider}
     */
    public static function chat_message(int $instanceid, int $courseid, array $history): array {
        global $USER;

        $params = self::validate_parameters(self::chat_message_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'history'    => $history,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // Sanitise history: only allow known roles, strip excessive messages.
        $cleanhistory = [];
        foreach ($params['history'] as $msg) {
            if (!in_array($msg['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $cleanhistory[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        // Limit history depth to avoid very large prompts.
        if (count($cleanhistory) > 30) {
            $cleanhistory = array_slice($cleanhistory, -30);
        }

        $builder      = new \block_playerhud\ai\context_builder(
            $params['instanceid'],
            $params['courseid']
        );
        $systemprompt = $builder->build();

        $chat   = new \block_playerhud\ai\chat($params['instanceid']);
        $result = $chat->send($systemprompt, $cleanhistory);

        $actionjson = '';
        if (!empty($result['action']) && is_array($result['action'])) {
            $actionjson = json_encode($result['action']);
        }

        return [
            'reply'    => $result['reply'],
            'action'   => $actionjson,
            'provider' => $result['provider'],
            'message'  => '',
        ];
    }

    /**
     * Return structure for chat_message.
     *
     * @return external_single_structure
     */
    public static function chat_message_returns(): external_single_structure {
        return new external_single_structure([
            'reply'    => new external_value(PARAM_RAW, 'AI reply text', VALUE_DEFAULT, ''),
            'action'   => new external_value(PARAM_RAW, 'JSON-encoded action object or empty', VALUE_DEFAULT, ''),
            'provider' => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'  => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Parameters for execute_chat_action.
     *
     * @return external_function_parameters
     */
    public static function execute_chat_action_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid'   => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'     => new external_value(PARAM_INT, 'Course ID'),
            'actiontype'   => new external_value(PARAM_ALPHANUMEXT, 'Action type identifier'),
            'actionparams' => new external_value(PARAM_RAW, 'JSON-encoded action parameters'),
        ]);
    }

    /**
     * Executes a game action proposed by the AI after teacher confirmation.
     *
     * Supported action types: create_item, create_quest, open_tab.
     * Each type is validated against an explicit allow-list before execution.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $actiontype Action type identifier.
     * @param string $actionparams JSON-encoded parameters for the action.
     * @return array {success, message, redirect_url}
     */
    public static function execute_chat_action(
        int $instanceid,
        int $courseid,
        string $actiontype,
        string $actionparams
    ): array {
        $params = self::validate_parameters(self::execute_chat_action_parameters(), [
            'instanceid'   => $instanceid,
            'courseid'     => $courseid,
            'actiontype'   => $actiontype,
            'actionparams' => $actionparams,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // Validate action type against explicit allow-list.
        $allowedtypes = ['create_item', 'create_quest', 'create_chapter', 'open_tab'];
        if (!in_array($params['actiontype'], $allowedtypes, true)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_unknown_action', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $aparams = json_decode($params['actionparams'], true);
        if (!is_array($aparams)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        try {
            if ($params['actiontype'] === 'create_item') {
                return self::action_create_item(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'create_quest') {
                return self::action_create_quest(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'create_chapter') {
                return self::action_create_chapter(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'open_tab') {
                return self::action_open_tab(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }
        } catch (\Exception $e) {
            return [
                'success'      => false,
                'message'      => $e->getMessage(),
                'redirect_url' => '',
            ];
        }

        return [
            'success'      => false,
            'message'      => get_string('assistant_error_unknown_action', 'block_playerhud'),
            'redirect_url' => '',
        ];
    }

    /**
     * Executes the create_item action.
     *
     * Reuses the existing generator to create an item with an optional drop.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: theme, xp, create_drop.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_item(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $theme      = isset($p['theme']) ? clean_param($p['theme'], PARAM_TEXT) : '';
        $xp         = isset($p['xp']) ? max(1, (int)$p['xp']) : 10;
        $createdrop = !empty($p['create_drop']);

        if ($theme === '') {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate('item', $theme, $xp, $createdrop);

        $itemname = $result['item_name'] ?? '';

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'item';
        $log->object_name     = substr($itemname, 0, 255);
        $log->ai_provider     = substr($result['provider'] ?? '', 0, 50);
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_item_created', 'block_playerhud', $itemname);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'items',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the create_quest action.
     *
     * Validates and inserts a quest record with the AI-provided parameters.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: name, description, type, target_value, reward_xp.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_quest(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $allowedtypes = [
            \block_playerhud\quest::TYPE_LEVEL,
            \block_playerhud\quest::TYPE_XP_TOTAL,
            \block_playerhud\quest::TYPE_UNIQUE_ITEMS,
            \block_playerhud\quest::TYPE_TRADES,
        ];

        $name        = isset($p['name']) ? clean_param($p['name'], PARAM_TEXT) : '';
        $description = isset($p['description']) ? clean_param($p['description'], PARAM_TEXT) : '';
        $type        = isset($p['type']) ? (int)$p['type'] : 0;
        $targetvalue = isset($p['target_value']) ? max(1, (int)$p['target_value']) : 1;
        $rewardxp    = isset($p['reward_xp']) ? max(0, (int)$p['reward_xp']) : 0;

        if ($name === '' || !in_array($type, $allowedtypes, true)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $now = time();
        $quest = new \stdClass();
        $quest->blockinstanceid  = $instanceid;
        $quest->name             = $name;
        $quest->description      = $description;
        $quest->type             = $type;
        $quest->requirement      = (string)$targetvalue;
        $quest->req_itemid       = 0;
        $quest->reward_xp        = $rewardxp;
        $quest->reward_itemid    = 0;
        $quest->required_class_id = '0';
        $quest->image_todo       = '';
        $quest->image_done       = '';
        $quest->enabled          = 1;
        $quest->timecreated      = $now;
        $quest->timemodified     = $now;
        $DB->insert_record('block_playerhud_quests', $quest);

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'quest';
        $log->object_name     = substr($name, 0, 255);
        $log->ai_provider     = 'assistant';
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_quest_created', 'block_playerhud', $name);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'quests',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the create_chapter action.
     *
     * Delegates to the existing generate_story generator, which creates the
     * chapter record, all story nodes, and branching choices in a single
     * DB transaction.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: theme, karma_gain, karma_loss, item_qty.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_chapter(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $theme = isset($p['theme']) ? clean_param($p['theme'], PARAM_TEXT) : '';

        if ($theme === '') {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $options = [
            'karma_gain' => max(0, (int)($p['karma_gain'] ?? 0)),
            'karma_loss' => max(0, (int)($p['karma_loss'] ?? 0)),
            'item_qty'   => max(0, (int)($p['item_qty'] ?? 0)),
        ];

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate_story($theme, $options);

        $chaptertitle = $result['chapter_title'] ?? '';

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'chapter';
        $log->object_name     = substr($chaptertitle, 0, 255);
        $log->ai_provider     = substr($result['provider'] ?? '', 0, 50);
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_chapter_created', 'block_playerhud', $chaptertitle);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'chapters',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the open_tab action.
     *
     * Returns a redirect URL to the requested management tab.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: tab.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_open_tab(int $instanceid, int $courseid, array $p): array {
        $allowedtabs = ['items', 'quests', 'classes', 'chapters', 'reports', 'config'];
        $tab = isset($p['tab']) ? clean_param($p['tab'], PARAM_ALPHA) : '';

        if (!in_array($tab, $allowedtabs, true)) {
            $tab = 'items';
        }

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => $tab,
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => get_string('assistant_action_opening_tab', 'block_playerhud', $tab),
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Return structure for execute_chat_action.
     *
     * @return external_single_structure
     */
    public static function execute_chat_action_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
            'message'      => new external_value(PARAM_RAW, 'Result or error message', VALUE_DEFAULT, ''),
            'redirect_url' => new external_value(PARAM_URL, 'URL to redirect to', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Parameters for create_playercoin.
     *
     * @return external_function_parameters
     */
    public static function create_playercoin_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Create or return the existing PlayerCoin item for this block instance.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @return array
     */
    public static function create_playercoin(int $instanceid, int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::create_playercoin_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
        ]);
        $instanceid = $params['instanceid'];
        $courseid   = $params['courseid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $existing = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'name'            => 'PlayerCoin',
        ]);

        if ($existing) {
            $itemid = (int) $existing->id;
            $created = false;
        } else {
            $now = time();
            $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
                'blockinstanceid' => $instanceid,
                'name'            => 'PlayerCoin',
                'image'           => '🪙',
                'description'     => get_string('playercoin_description', 'block_playerhud'),
                'xp'              => 0,
                'enabled'         => 1,
                'tradable'        => 1,
                'secret'          => 0,
                'required_class_id' => '0',
                'action_type'     => '',
                'action_value'    => '',
                'timecreated'     => $now,
                'timemodified'    => $now,
            ]);
            $created = true;
        }

        $editurl = new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'items',
            'action'     => 'edit',
            'itemid'     => $itemid,
        ]);

        return [
            'itemid'   => $itemid,
            'created'  => $created,
            'edit_url' => $editurl->out(false),
        ];
    }

    /**
     * Return structure for create_playercoin.
     *
     * @return external_single_structure
     */
    public static function create_playercoin_returns(): external_single_structure {
        return new external_single_structure([
            'itemid'   => new external_value(PARAM_INT, 'ID of the PlayerCoin item'),
            'created'  => new external_value(PARAM_BOOL, 'True if a new item was created'),
            'edit_url' => new external_value(PARAM_URL, 'URL to the item edit form'),
        ]);
    }

    /**
     * Parameters for create_avatar_pack.
     *
     * @return external_function_parameters
     */
    public static function create_avatar_pack_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Create the pre-defined avatar item pack for this block instance.
     *
     * Items are created with action_type = avatar_profile so they can be equipped
     * as profile avatars. Items whose name already exists are skipped.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @return array
     */
    public static function create_avatar_pack(int $instanceid, int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::create_avatar_pack_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
        ]);
        $instanceid = $params['instanceid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $avatars = [
            // Light skin tone.
            ['emoji' => '🧛🏻‍♂️', 'name_key' => 'avatar_name_vampire_m', 'desc_key' => 'avatar_desc_vampire'],
            ['emoji' => '🧙🏻‍♀️', 'name_key' => 'avatar_name_mage_f', 'desc_key' => 'avatar_desc_mage'],
            ['emoji' => '🕵🏻‍♀️', 'name_key' => 'avatar_name_detective', 'desc_key' => 'avatar_desc_detective'],
            // Medium-light skin tone.
            ['emoji' => '🧝🏼‍♂️', 'name_key' => 'avatar_name_elf_m', 'desc_key' => 'avatar_desc_elf'],
            ['emoji' => '🦸🏼‍♀️', 'name_key' => 'avatar_name_superhero_f', 'desc_key' => 'avatar_desc_superhero'],
            ['emoji' => '🧚🏼‍♀️', 'name_key' => 'avatar_name_fairy', 'desc_key' => 'avatar_desc_fairy'],
            // Medium skin tone.
            ['emoji' => '🕵🏽‍♂️', 'name_key' => 'avatar_name_detective', 'desc_key' => 'avatar_desc_detective'],
            ['emoji' => '🧛🏽‍♀️', 'name_key' => 'avatar_name_vampire_f', 'desc_key' => 'avatar_desc_vampire'],
            ['emoji' => '🦹🏽‍♀️', 'name_key' => 'avatar_name_supervillain_f', 'desc_key' => 'avatar_desc_supervillain'],
            // Medium-dark skin tone.
            ['emoji' => '🧙🏾‍♂️', 'name_key' => 'avatar_name_mage_m', 'desc_key' => 'avatar_desc_mage'],
            ['emoji' => '🧝🏾‍♀️', 'name_key' => 'avatar_name_elf_f', 'desc_key' => 'avatar_desc_elf'],
            ['emoji' => '🦸🏾‍♂️', 'name_key' => 'avatar_name_superhero_m', 'desc_key' => 'avatar_desc_superhero'],
            // Dark skin tone.
            ['emoji' => '🤺', 'name_key' => 'avatar_name_fencer', 'desc_key' => 'avatar_desc_fencer'],
            ['emoji' => '🧜🏿‍♀️', 'name_key' => 'avatar_name_mermaid', 'desc_key' => 'avatar_desc_mermaid'],
            ['emoji' => '🦹🏿‍♂️', 'name_key' => 'avatar_name_supervillain_m', 'desc_key' => 'avatar_desc_supervillain'],
            // Gender-neutral.
            ['emoji' => '🤖', 'name_key' => 'avatar_name_robot', 'desc_key' => 'avatar_desc_robot'],
            ['emoji' => '👾', 'name_key' => 'avatar_name_alien', 'desc_key' => 'avatar_desc_alien'],
        ];

        $existingimages = $DB->get_fieldset_select(
            'block_playerhud_items',
            'image',
            'blockinstanceid = :id',
            ['id' => $instanceid]
        );
        $existingimages = array_flip($existingimages);

        $created = 0;
        $now = time();

        foreach ($avatars as $avatar) {
            if (isset($existingimages[$avatar['emoji']])) {
                continue;
            }
            $DB->insert_record('block_playerhud_items', (object) [
                'blockinstanceid' => $instanceid,
                'name'            => get_string($avatar['name_key'], 'block_playerhud'),
                'image'           => $avatar['emoji'],
                'description'     => get_string($avatar['desc_key'], 'block_playerhud'),
                'xp'              => 0,
                'enabled'         => 1,
                'tradable'        => 0,
                'secret'          => 0,
                'required_class_id' => '0',
                'action_type'     => 'avatar_profile',
                'action_value'    => '',
                'timecreated'     => $now,
                'timemodified'    => $now,
            ]);
            $created++;
        }

        return ['created' => $created];
    }

    /**
     * Return structure for create_avatar_pack.
     *
     * @return external_single_structure
     */
    public static function create_avatar_pack_returns(): external_single_structure {
        return new external_single_structure([
            'created' => new external_value(PARAM_INT, 'Number of avatar items created'),
        ]);
    }

    /**
     * Parameters for setup_playercoin_drop.
     *
     * @return external_function_parameters
     */
    public static function setup_playercoin_drop_parameters(): external_function_parameters {
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
    public static function setup_playercoin_drop(int $instanceid, int $courseid, int $itemid): array {
        global $DB;

        $params = self::validate_parameters(self::setup_playercoin_drop_parameters(), [
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

        $code = substr(md5(uniqid('ph_drop_', true)), 0, 12);

        $DB->insert_record('block_playerhud_drops', (object) [
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
        ];
    }

    /**
     * Return structure for setup_playercoin_drop.
     *
     * @return external_single_structure
     */
    public static function setup_playercoin_drop_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the drop was created'),
            'message' => new external_value(PARAM_RAW, 'Result or error message'),
        ]);
    }

    /**
     * Parameters for use_item.
     *
     * @return external_function_parameters
     */
    public static function use_item_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'itemid'     => new external_value(PARAM_INT, 'Item ID'),
            'targetcmid' => new external_value(PARAM_INT, 'Target course module ID (deadline_extension only)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Equip/unequip an avatar item or consume a deadline extension item.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $itemid Item ID.
     * @param int $targetcmid Target CM (for deadline_extension when cmid=0 on the item).
     * @return array
     */
    public static function use_item(int $instanceid, int $courseid, int $itemid, int $targetcmid = 0): array {
        global $DB, $USER, $OUTPUT;

        $params = self::validate_parameters(self::use_item_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'itemid'     => $itemid,
            'targetcmid' => $targetcmid,
        ]);
        $instanceid = $params['instanceid'];
        $courseid   = $params['courseid'];
        $itemid     = $params['itemid'];
        $targetcmid = $params['targetcmid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_login();

        $item = $DB->get_record(
            'block_playerhud_items',
            ['id' => $itemid, 'blockinstanceid' => $instanceid, 'enabled' => 1],
            '*',
            MUST_EXIST
        );

        $hasinv = $DB->record_exists_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source NOT IN ('revoked','consumed')",
            ['uid' => $USER->id, 'iid' => $itemid]
        );
        if (!$hasinv) {
            throw new \moodle_exception('itemnotfound', 'block_playerhud');
        }

        if ($item->action_type === 'avatar_profile') {
            $prefkey  = 'block_playerhud_avatar_' . $instanceid;
            $current  = (int) get_user_preferences($prefkey, 0);
            $equipped = ($current !== $itemid);

            if ($equipped) {
                set_user_preference($prefkey, $itemid);
                $avatarhtml = \block_playerhud\utils::get_avatar_html($item, $context, $OUTPUT);
            } else {
                unset_user_preference($prefkey);
                $avatarhtml = $OUTPUT->user_picture($USER, ['size' => 100, 'class' => 'rounded-circle shadow-sm']);
            }

            return [
                'action'      => 'avatar_profile',
                'equipped'    => $equipped,
                'avatar_html' => $avatarhtml,
                'success'     => true,
                'message'     => $equipped
                    ? get_string('item_use_success_avatar', 'block_playerhud')
                    : get_string('item_unequip_success', 'block_playerhud'),
                'new_deadline' => '',
            ];
        }

        if ($item->action_type === 'deadline_extension') {
            if (!class_exists('\local_latepenalty\recalculator')) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_lp_not_installed', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $av   = !empty($item->action_value) ? json_decode($item->action_value, true) : [];
            $days = max(1, (int)($av['days'] ?? 1));
            $cmid = !empty($av['cmid']) ? (int)$av['cmid'] : $targetcmid;

            if ($cmid <= 0) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_use_pick_activity', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $rule = $DB->get_record('local_latepenalty_rules', ['cmid' => $cmid, 'enabled' => 1]);
            if (!$rule) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_lp_warning', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $override = $DB->get_record('local_latepenalty_overrides', ['cmid' => $cmid, 'userid' => $USER->id]);
            if ($override && $override->deadline !== null) {
                $base = (int)$override->deadline;
            } else {
                $modinfo = get_fast_modinfo($courseid);
                $cm      = $modinfo->get_cm($cmid);
                $base    = (int)(\local_latepenalty\penalty_helper::get_deadline($cm->get_course_module_record()) ?? time());
            }

            $newdeadline = $base + ($days * DAYSECS);

            if ($override) {
                $override->deadline      = $newdeadline;
                $override->timemodified  = time();
                $DB->update_record('local_latepenalty_overrides', $override);
            } else {
                $DB->insert_record('local_latepenalty_overrides', (object)[
                    'cmid'          => $cmid,
                    'userid'        => $USER->id,
                    'deadline'      => $newdeadline,
                    'daily_penalty' => null,
                    'max_penalty'   => null,
                    'timecreated'   => time(),
                    'timemodified'  => time(),
                ]);
            }

            \local_latepenalty\recalculator::recalculate_for_student(
                $cmid,
                $USER->id,
                (float)$rule->daily_penalty,
                (float)$rule->max_penalty
            );

            $consumable = $DB->get_records_select(
                'block_playerhud_inventory',
                "userid = :uid AND itemid = :iid AND source NOT IN ('revoked','consumed')",
                ['uid' => $USER->id, 'iid' => $itemid],
                'id ASC',
                'id',
                0,
                1
            );
            if ($consumable) {
                $DB->set_field('block_playerhud_inventory', 'source', 'consumed', ['id' => reset($consumable)->id]);
            }

            $formatted = userdate($newdeadline, get_string('strftimedatetime', 'langconfig'));
            return [
                'action'       => 'deadline_extension',
                'equipped'     => false,
                'avatar_html'  => '',
                'success'      => true,
                'message'      => get_string('item_use_success_deadline', 'block_playerhud', $days),
                'new_deadline' => $formatted,
            ];
        }

        throw new \moodle_exception('errorgeneral', 'error');
    }


    /**
     * Return structure for use_item.
     *
     * @return external_single_structure
     */
    public static function use_item_returns(): external_single_structure {
        return new external_single_structure([
            'action'       => new external_value(PARAM_ALPHANUMEXT, 'Action type performed'),
            'equipped'     => new external_value(PARAM_BOOL, 'Whether avatar is now equipped'),
            'avatar_html'  => new external_value(PARAM_RAW, 'Avatar HTML for DOM replacement'),
            'success'      => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
            'message'      => new external_value(PARAM_RAW, 'Feedback message'),
            'new_deadline' => new external_value(PARAM_TEXT, 'Formatted new deadline (deadline_extension only)'),
        ]);
    }
}
