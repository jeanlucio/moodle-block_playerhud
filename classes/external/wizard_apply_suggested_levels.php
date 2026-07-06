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
 * Web service to apply the wizard's suggested level settings for a journey size.
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
 * External API that applies the wizard's suggested max_levels for a journey size.
 *
 * An opt-in convenience, gated by the wizard's own "auto-adjust levels" checkbox rather than a
 * server-side "still untouched" heuristic: the checkbox can be re-toggled and the journey size
 * re-picked any number of times in one session, so every call applies unconditionally — the
 * checkbox being checked at all is the teacher's explicit consent, not an accident to guard
 * against. Writes through the block's own `instance_config_save()` (not a raw DB update), which
 * merges into the existing config object rather than replacing it, so every other setting the
 * teacher already configured — items, quests, RPG, ranking, mascot... — is left untouched.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_apply_suggested_levels extends external_api {
    /**
     * Parameters for wizard_apply_suggested_levels.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'size' => new external_value(PARAM_ALPHA, 'Journey size: short, medium or long'),
        ]);
    }

    /**
     * Applies the suggested max_levels for the given journey size, unconditionally: the wizard's
     * "auto-adjust levels" checkbox is the consent gate, not this method.
     *
     * @param int $instanceid Block instance ID.
     * @param string $size Journey size: short, medium or long.
     * @return array{applied: bool, xp_per_level: int, max_levels: int}
     */
    public static function execute(int $instanceid, string $size): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'size' => $size,
        ]);
        $instanceid = $params['instanceid'];
        $size = $params['size'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $blockinstance = \block_instance_by_id($instanceid);
        $config = $blockinstance->config ?: new \stdClass();

        $suggestedlevels = \block_playerhud\local\xp_budget::compute_suggested_max_levels($size);
        $config->xp_per_level = 100;
        $config->max_levels = $suggestedlevels;
        $blockinstance->instance_config_save($config);

        return [
            'applied' => true,
            'xp_per_level' => 100,
            'max_levels' => $suggestedlevels,
        ];
    }

    /**
     * Return structure for wizard_apply_suggested_levels.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'applied' => new external_value(PARAM_BOOL, 'Always true; kept for API stability'),
            'xp_per_level' => new external_value(PARAM_INT, 'The instance\'s XP per level after this call'),
            'max_levels' => new external_value(PARAM_INT, 'The instance\'s max levels after this call'),
        ]);
    }
}
