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
 * Web service to run the gamification wizard.
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
 * External API that runs the gamification wizard for the Items & Trade module.
 *
 * Other modules (Missions, Story, RPG Classes...) will be added to this same
 * entry point in later iterations, each recording its own objects into the
 * same wizard run for a single combined rollback.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_generate extends external_api {
    /** @var int Number of items generated for the "short journey" size. */
    private const SIZE_SHORT_AMOUNT = 5;

    /** @var int Number of items generated for the "long journey" size. */
    private const SIZE_LONG_AMOUNT = 15;

    /**
     * Define parameters for wizard_generate.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'theme' => new external_value(PARAM_TEXT, 'Subject theme'),
            'tone' => new external_value(PARAM_TEXT, 'Narrative tone', VALUE_DEFAULT, ''),
            'size' => new external_value(PARAM_ALPHA, 'Journey size: short or long', VALUE_DEFAULT, 'short'),
        ]);
    }

    /**
     * Runs the gamification wizard.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $theme Subject theme.
     * @param string $tone Narrative tone.
     * @param string $size Journey size: short or long.
     * @return array Result structure.
     */
    public static function execute(
        int $instanceid,
        int $courseid,
        string $theme,
        string $tone = '',
        string $size = 'short'
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'theme' => $theme,
            'tone' => $tone,
            'size' => $size,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $amount = ($params['size'] === 'long') ? self::SIZE_LONG_AMOUNT : self::SIZE_SHORT_AMOUNT;

        $balancecontext = \block_playerhud\local\analytics::balance_context(
            $params['instanceid'],
            $xpperlevel,
            $maxlevels,
            $amount
        );

        $runid = \block_playerhud\local\wizard::start_run($params['instanceid'], (int) $USER->id, ['items']);

        $generator = new \block_playerhud\ai\generator($params['instanceid']);

        try {
            $result = $generator->generate(
                'item',
                $params['theme'],
                -1,
                true,
                [
                    'tone' => $params['tone'],
                    'balance_context' => $balancecontext,
                ],
                $amount
            );

            if (!empty($result['created_item_ids'])) {
                \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', $result['created_item_ids']);
            }
            if (!empty($result['created_drop_ids'])) {
                \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_drops', $result['created_drop_ids']);
            }

            \block_playerhud\local\wizard::finish_run($runid, 'done');

            return [
                'success' => true,
                'runid' => $runid,
                'message' => '',
                'created_items' => $result['created_items'] ?? [],
            ];
        } catch (\Exception $e) {
            // Generation failures happen before any object is saved, so the run leaves
            // nothing to roll back; 'rolledback' accurately reflects that end state.
            \block_playerhud\local\wizard::finish_run($runid, 'rolledback');

            return [
                'success' => false,
                'runid' => $runid,
                'message' => $e->getMessage(),
                'created_items' => [],
            ];
        }
    }

    /**
     * Return structure for wizard_generate.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'runid' => new external_value(PARAM_INT, 'Wizard run ID, for rollback'),
            'message' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
            'created_items' => new \core_external\external_multiple_structure(
                new external_value(PARAM_TEXT, 'Item name'),
                'List of created items',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
