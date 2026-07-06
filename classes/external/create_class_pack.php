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
 * Web service to create the pre-defined RPG class pack for a given narrative tone.
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
 * External API to create the pre-defined RPG class pack (3 archetypes) for a block instance.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_class_pack extends external_api {
    /**
     * Parameters for create_class_pack.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'tone'       => new external_value(
                PARAM_ALPHA,
                'Narrative tone key: fantasy, scifi, mystery or academic',
                VALUE_DEFAULT,
                'fantasy'
            ),
        ]);
    }

    /**
     * Create the pre-defined 3-archetype RPG class pack for this block instance.
     *
     * Classes already present with a matching name are skipped, so calling this twice with
     * the same tone never creates duplicates.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $tone Narrative tone key.
     * @return array
     */
    public static function execute(int $instanceid, int $courseid, string $tone = 'fantasy'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'tone'       => $tone,
        ]);
        $instanceid = $params['instanceid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $archetypes = \block_playerhud\local\rpg_archetypes::get_pack($params['tone'])['classes'];

        $existingnames = $DB->get_fieldset_select(
            'block_playerhud_classes',
            'name',
            'blockinstanceid = :id',
            ['id' => $instanceid]
        );
        $existingnames = array_flip($existingnames);

        $createdids = [];
        $creatednames = [];
        $now = time();

        foreach ($archetypes as $archetype) {
            if (isset($existingnames[$archetype['name']])) {
                continue;
            }
            $classid = (int) $DB->insert_record('block_playerhud_classes', (object) [
                'blockinstanceid' => $instanceid,
                'name'            => $archetype['name'],
                'description'     => $archetype['description'],
                'base_hp'         => $archetype['base_hp'],
                'emoji_tier1'     => $archetype['emoji'],
                'timecreated'     => $now,
                'timemodified'    => $now,
            ]);
            $createdids[] = $classid;
            $creatednames[] = $archetype['name'];
        }

        return [
            'created' => count($createdids),
            'created_class_ids' => $createdids,
            'created_class_names' => $creatednames,
        ];
    }

    /**
     * Return structure for create_class_pack.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'created' => new external_value(PARAM_INT, 'Number of classes created'),
            'created_class_ids' => new \core_external\external_multiple_structure(
                new external_value(PARAM_INT, 'Class ID'),
                'IDs of the created classes',
                VALUE_OPTIONAL
            ),
            'created_class_names' => new \core_external\external_multiple_structure(
                new external_value(PARAM_TEXT, 'Class name'),
                'Names of the created classes',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
