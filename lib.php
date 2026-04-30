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
 * Library of functions for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    $validareas = ['item_image'];
    for ($i = 1; $i <= 5; $i++) {
        $validareas[] = 'class_image_' . $i;
    }

    if (!in_array($filearea, $validareas)) {
        return false;
    }

    // 4. Retrieve File.
    $itemid = (int)array_shift($args);

    $fs = get_file_storage();

    $files = $fs->get_area_files(
        $context->id,
        'block_playerhud',
        $filearea,
        $itemid,
        'sortorder DESC, id DESC',
        false // Exclude directories.
    );

    $filetoserve = null;
    foreach ($files as $f) {
        $filetoserve = $f;
        break;
    }

    if (!$filetoserve) {
        return false;
    }

    // 5. Serve the file.
    send_stored_file($filetoserve, 0, 0, true, $options);
}

/**
 * Fetches Drop details using the hash CODE and instance ID.
 *
 * @param string $code The alphanumeric code (e.g. 3C815F).
 * @param int $blockinstanceid The block instance ID to ensure uniqueness.
 * @return stdClass|false The detailed drop object or false.
 */
function block_playerhud_get_drop_details_by_code($code, $blockinstanceid) {
    global $DB;

    // We add AND d.blockinstanceid = :bi to avoid collision with restored backups.
    $sql = "SELECT d.id as dropid, d.maxusage, d.respawntime, d.blockinstanceid,
                   i.id as itemid, i.name as itemname, i.image, i.xp, i.description,
                   i.secret, i.required_class_id
              FROM {block_playerhud_drops} d
              JOIN {block_playerhud_items} i ON d.itemid = i.id
             WHERE d.code = :code
               AND d.blockinstanceid = :bi
               AND i.enabled = 1";

    return $DB->get_record_sql($sql, ['code' => $code, 'bi' => $blockinstanceid]);
}

/**
 * Checks if content (item or quest) is visible for the user's class.
 *
 * @param string $requiredclassids IDs of allowed classes (e.g. "1,2" or "0").
 * @param int $userclassid Current user class ID.
 * @return bool True if visible.
 */
function block_playerhud_is_visible_for_class($requiredclassids, $userclassid) {
    return \block_playerhud\utils::is_visible_for_class((string) $requiredclassids, (int) $userclassid);
}
