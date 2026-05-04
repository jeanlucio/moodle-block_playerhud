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
 * Upgrade script for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    // Past upgrade steps were merged into install.xml for the current baseline version.

    if ($oldversion < 2026050402) {
        // Remove guest default permission from block/playerhud:view.
        // The capability was never effective (guests are blocked earlier in get_content
        // and require_login), but the archetype declaration was misleading and prevented
        // the Permissions UI from working as expected when an admin restricted the role.
        $guestrole = $DB->get_record('role', ['shortname' => 'guest']);
        if ($guestrole) {
            unassign_capability('block/playerhud:view', $guestrole->id);
        }

        upgrade_block_savepoint(true, 2026050402, 'playerhud');
    }

    return true;
}
