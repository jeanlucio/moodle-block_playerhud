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
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
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
        false // Exclude directories
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
 * Helper function used by Text Filters to fetch Drop details efficiently.
 * This avoids the filter needing to do complex JOINs manually.
 *
 * @param int $dropid The ID of the drop.
 * @return stdClass|false The drop object with item details mixed in, or false.
 */
function block_playerhud_get_drop_details_for_filter($dropid) {
    global $DB;

    $sql = "SELECT d.id as dropid, d.maxusage, d.respawntime, d.blockinstanceid,
                   i.id as itemid, i.name as itemname, i.image, i.xp, i.description, 
                   i.secret, i.required_class_id
              FROM {block_playerhud_drops} d
              JOIN {block_playerhud_items} i ON d.itemid = i.id
             WHERE d.id = :dropid 
               AND i.enabled = 1"; // Only enabled items

    return $DB->get_record_sql($sql, ['dropid' => $dropid]);
}

/**
 * Busca detalhes do Drop usando o CÓDIGO hash (para o novo shortcode).
 *
 * @param string $code O código alfanumérico (ex: 3C815F).
 * @return stdClass|false O objeto drop detalhado ou false.
 */
function block_playerhud_get_drop_details_by_code($code) {
    global $DB;

    // Nota: Adicionamos a cláusula WHERE d.code = :code
    $sql = "SELECT d.id as dropid, d.maxusage, d.respawntime, d.blockinstanceid,
                   i.id as itemid, i.name as itemname, i.image, i.xp, i.description, 
                   i.secret, i.required_class_id
              FROM {block_playerhud_drops} d
              JOIN {block_playerhud_items} i ON d.itemid = i.id
             WHERE d.code = :code 
               AND i.enabled = 1";

    return $DB->get_record_sql($sql, ['code' => $code]);
}

/**
 * Checks if content (item or quest) is visible for the user's class.
 *
 * @param string $requiredclassids IDs of allowed classes (e.g. "1,2" or "0").
 * @param int $userclassid Current user class ID.
 * @return bool True if visible.
 */
function block_playerhud_is_visible_for_class($requiredclassids, $userclassid) {
    // Empty or '0' means Public (All Classes)
    if (empty($requiredclassids) || $requiredclassids === '0') {
        return true;
    }

    $allowedarray = explode(',', $requiredclassids);
    
    // Check if '0' is in array (explicit public) or user class is in array
    return (in_array('0', $allowedarray) || in_array((string)$userclassid, $allowedarray));
}

/**
 * Standard upgrade function.
 */
function block_playerhud_upgrade($oldversion, $block) {
    return true;
}
