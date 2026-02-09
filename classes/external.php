<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_playerhud;

defined('MOODLE_INTERNAL') || die();

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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            'xp' => new external_value(PARAM_INT, 'XP value', VALUE_DEFAULT, 0),
            'amount' => new external_value(PARAM_INT, 'Amount of items', VALUE_DEFAULT, 1),
            'create_drop' => new external_value(PARAM_BOOL, 'Create drop location?', VALUE_DEFAULT, false),
            'drop_location' => new external_value(PARAM_TEXT, 'Drop location name', VALUE_DEFAULT, ''),
            'drop_max' => new external_value(PARAM_INT, 'Max usage count', VALUE_DEFAULT, 0),
            'drop_time' => new external_value(PARAM_INT, 'Respawn time in minutes', VALUE_DEFAULT, 0)
        ]);
    }

    /**
     * Execute AI generation logic.
     *
     * @param int $instanceid
     * @param int $courseid
     * @param string $theme
     * @param int $xp
     * @param int $amount
     * @param bool $create_drop
     * @param string $drop_location
     * @param int $drop_max
     * @param int $drop_time
     * @return array
     */
    public static function generate_ai_content($instanceid, $courseid, $theme, $xp = 0, $amount = 1,
                                               $create_drop = false, $drop_location = '', $drop_max = 0, $drop_time = 0) {
        global $DB;

        // 1. Validation.
        $params = self::validate_parameters(self::generate_ai_content_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'theme' => $theme,
            'xp' => $xp,
            'amount' => $amount,
            'create_drop' => $create_drop,
            'drop_location' => $drop_location,
            'drop_max' => $drop_max,
            'drop_time' => $drop_time
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // 2. Logic (Balance Context).
        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $xpceiling = $xpperlevel * $maxlevels;

        $currenttotalxp = 0;
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $params['instanceid'], 'enabled' => 1]);
        if ($items) {
            foreach ($items as $it) {
                $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $it->id]);
                if ($drops) {
                    foreach ($drops as $d) {
                        if ($d->maxusage > 0) {
                            $currenttotalxp += ($it->xp * $d->maxusage);
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
            'qty' => $params['amount']
        ];

        $extraoptions = [
            'drop_location' => $params['drop_location'],
            'drop_max' => $params['drop_max'],
            'drop_time' => $params['drop_time'],
            'balance_context' => $balancecontext
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

            return [
                'success' => true,
                'message' => $result['message'] ?? '',
                'created_items' => $result['created_items'] ?? [],
                'item_name' => $result['item_name'] ?? '',
                'drop_code' => $result['drop_code'] ?? null,
                'warning_msg' => $result['warning_msg'] ?? null,
                'info_msg' => $result['info_msg'] ?? null,
                'provider' => $result['provider'] ?? ''
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
                'provider' => ''
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
            'provider' => new external_value(PARAM_TEXT, 'AI Provider used', VALUE_OPTIONAL)
        ]);
    }
}
