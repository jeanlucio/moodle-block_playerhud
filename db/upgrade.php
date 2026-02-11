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
 * Upgrade script for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block_playerhud.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_block_playerhud_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add the 'code' field to the block_playerhud_drops table.
    if ($oldversion < 2026020301) {
        $table = new xmldb_table('block_playerhud_drops');
        $field = new xmldb_field('code', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'itemid');

        // Add the field if it does not exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Upgrade savepoint.
        upgrade_block_savepoint(true, 2026020301, 'playerhud');
    }

    return true;
}
