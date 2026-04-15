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

/**
 * External service definitions for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_playerhud_generate_ai_content' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'generate_ai_content',
        'description' => 'Generates game items using AI',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'block_playerhud_collect_item' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'collect_item',
        'description' => 'Collect an item via Drop',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'block_playerhud_insert_drop_shortcode' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'insert_drop_shortcode',
        'description' => 'Insert a drop shortcode into a course module field',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_remove_drop_shortcode' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'remove_drop_shortcode',
        'description' => 'Remove a drop shortcode from a course module field',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_load_scene' => [
        'classname'     => 'block_playerhud\external',
        'methodname'    => 'load_scene',
        'description'   => 'Load the current or starting scene for a story chapter',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_make_choice' => [
        'classname'     => 'block_playerhud\external',
        'methodname'    => 'make_choice',
        'description'   => 'Process a story choice and return the next scene',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_load_recap' => [
        'classname'     => 'block_playerhud\external',
        'methodname'    => 'load_recap',
        'description'   => 'Return the full story recap HTML for a completed chapter',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],

    'block_playerhud_generate_class_oracle' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'generate_class_oracle',
        'description' => 'Generate an RPG class via AI (Class Oracle) and save it',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],

    'block_playerhud_generate_story' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'generate_story',
        'description' => 'Generate a branching story chapter via AI and save it',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
