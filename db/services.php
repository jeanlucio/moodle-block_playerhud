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
 * External service definitions for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_playerhud_generate_ai_content' => [
        'classname'   => 'block_playerhud\external\generate_ai_content',
        'methodname'  => 'execute',
        'description' => 'Generates game items using AI',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'block_playerhud_collect_item' => [
        'classname'   => 'block_playerhud\external\collect_item',
        'methodname'  => 'execute',
        'description' => 'Collect an item via Drop',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'block_playerhud_insert_drop_shortcode' => [
        'classname'   => 'block_playerhud\external\insert_drop_shortcode',
        'methodname'  => 'execute',
        'description' => 'Insert a drop shortcode into a course module field',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_remove_drop_shortcode' => [
        'classname'   => 'block_playerhud\external\remove_drop_shortcode',
        'methodname'  => 'execute',
        'description' => 'Remove a drop shortcode from a course module field',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_load_scene' => [
        'classname'     => 'block_playerhud\external\load_scene',
        'methodname'    => 'execute',
        'description'   => 'Load the current or starting scene for a story chapter',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_make_choice' => [
        'classname'     => 'block_playerhud\external\make_choice',
        'methodname'    => 'execute',
        'description'   => 'Process a story choice and return the next scene',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_load_recap' => [
        'classname'     => 'block_playerhud\external\load_recap',
        'methodname'    => 'execute',
        'description'   => 'Return the full story recap HTML for a completed chapter',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_generate_class_oracle' => [
        'classname'   => 'block_playerhud\external\generate_class_oracle',
        'methodname'  => 'execute',
        'description' => 'Generate an RPG class via AI (Class Oracle) and save it',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_generate_story' => [
        'classname'   => 'block_playerhud\external\generate_story',
        'methodname'  => 'execute',
        'description' => 'Generate a branching story chapter via AI and save it',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_chat_message' => [
        'classname'     => 'block_playerhud\external\chat_message',
        'methodname'    => 'execute',
        'description'   => 'Send a message to the Game Master AI assistant and get a reply',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_execute_chat_action' => [
        'classname'     => 'block_playerhud\external\execute_chat_action',
        'methodname'    => 'execute',
        'description'   => 'Execute a game action proposed by the AI after teacher confirmation',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_create_playercoin' => [
        'classname'     => 'block_playerhud\external\create_playercoin',
        'methodname'    => 'execute',
        'description'   => 'Create or return the existing PlayerCoin item for a block instance',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_create_avatar_pack' => [
        'classname'     => 'block_playerhud\external\create_avatar_pack',
        'methodname'    => 'execute',
        'description'   => 'Create the pre-defined avatar item pack for a block instance',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_create_class_pack' => [
        'classname'     => 'block_playerhud\external\create_class_pack',
        'methodname'    => 'execute',
        'description'   => 'Create the pre-defined RPG class pack for a block instance',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_use_item' => [
        'classname'     => 'block_playerhud\external\use_item',
        'methodname'    => 'execute',
        'description'   => 'Equip/unequip an avatar item or consume a deadline extension item',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_setup_playercoin_drop' => [
        'classname'     => 'block_playerhud\external\setup_playercoin_drop',
        'methodname'    => 'execute',
        'description'   => 'Create an infinite drop for the PlayerCoin in the course news forum',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_generate' => [
        'classname'     => 'block_playerhud\external\wizard_generate',
        'methodname'    => 'execute',
        'description'   => 'Run the gamification wizard for a block instance',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_start' => [
        'classname'     => 'block_playerhud\external\wizard_start',
        'methodname'    => 'execute',
        'description'   => 'Start a live, step-by-step gamification wizard run and return its plan',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_run_step' => [
        'classname'     => 'block_playerhud\external\wizard_run_step',
        'methodname'    => 'execute',
        'description'   => 'Run a single step of a live gamification wizard run',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_apply_suggested_levels' => [
        'classname'     => 'block_playerhud\external\wizard_apply_suggested_levels',
        'methodname'    => 'execute',
        'description'   => "Apply the wizard's suggested max_levels for a journey size",
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_rollback' => [
        'classname'     => 'block_playerhud\external\wizard_rollback',
        'methodname'    => 'execute',
        'description'   => 'Undo a gamification wizard run',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_wizard_list_runs' => [
        'classname'     => 'block_playerhud\external\wizard_list_runs',
        'methodname'    => 'execute',
        'description'   => 'List recent gamification wizard runs available for rollback',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
