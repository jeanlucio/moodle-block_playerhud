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
 * Web service to create the pre-defined avatar item pack.
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
 * External API to create the pre-defined avatar item pack for a block instance.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_avatar_pack extends external_api {
    /**
     * Parameters for create_avatar_pack.
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
     * Create the pre-defined avatar item pack for this block instance.
     *
     * Items are created with action_type = avatar_profile so they can be equipped
     * as profile avatars. Items whose name already exists are skipped.
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
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'created' => new external_value(PARAM_INT, 'Number of avatar items created'),
        ]);
    }
}
