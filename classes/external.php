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
}
