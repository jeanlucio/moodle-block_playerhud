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
 * Library of functions for the PlayerHUD block.
 *
 * This file mainly handles file serving via the pluginfile API.
 * Most logic should be placed in classes within the /classes directory.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves module files (images for items, classes, etc).
 *
 * @param stdClass $course The course object.
 * @param stdClass $birecord The block instance record.
 * @param context $context The block context.
 * @param string $filearea The file area (e.g., 'item_image').
 * @param array $args Extra arguments (usually item ID).
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool|void False if file not found, otherwise serves file.
 */
function block_playerhud_pluginfile($course, $birecord, $context, $filearea, $args, $forcedownload, array $options = []) {
    // 1. Context Check: Ensure we are in a block context.
    if ($context->contextlevel != CONTEXT_BLOCK) {
        return false;
    }

    // 2. Permission Check: User must be logged in to the course to see assets.
    require_login($course);

    // 3. Validate File Areas.
    // Allow 'item_image' and 'class_image_1' through '5'.
    $validareas = ['item_image'];
    for ($i = 1; $i <= 5; $i++) {
        $validareas[] = 'class_image_' . $i;
    }

    if (!in_array($filearea, $validareas)) {
        return false;
    }

    // 4. Retrieve File.
    // The first argument is the Item ID or Class ID.
    $itemid = (int)array_shift($args);

    $fs = get_file_storage();
    
    // Get files from the block_playerhud component in the specific block instance context.
    $files = $fs->get_area_files(
        $context->id,
        'block_playerhud',
        $filearea,
        $itemid,
        'sortorder DESC, id DESC',
        false // Exclude directories
    );

    // Get the first valid file found.
    $filetoserve = null;
    foreach ($files as $f) {
        $filetoserve = $f;
        break;
    }

    // If no file found, return false (Moodle will show broken image or 404).
    if (!$filetoserve) {
        return false;
    }

    // 5. Serve the file.
    send_stored_file($filetoserve, 0, 0, true, $options);
}

/**
 * Standard upgrade function to support Moodle upgrades.
 *
 * @param int $oldversion
 * @param object $block
 */
function block_playerhud_upgrade($oldversion, $block) {
    // Upgrade logic goes here if needed in the future.
    return true;
}
