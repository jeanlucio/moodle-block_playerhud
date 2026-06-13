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
 * Web service to create or return the existing PlayerCoin item.
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
 * External API to create or return the existing PlayerCoin item for a block instance.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_playercoin extends external_api {
    /**
     * Parameters for create_playercoin.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
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
    public static function execute(int $instanceid, int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
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
            'action_type'     => 'playercoin',
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
                'action_type'     => 'playercoin',
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
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'itemid'   => new external_value(PARAM_INT, 'ID of the PlayerCoin item'),
            'created'  => new external_value(PARAM_BOOL, 'True if a new item was created'),
            'edit_url' => new external_value(PARAM_URL, 'URL to the item edit form'),
        ]);
    }
}
