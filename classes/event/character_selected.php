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
 * Event fired when a user selects or switches their RPG character.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Fired whenever a user is assigned an RPG class (initial pick or a switch).
 *
 * The 'other' payload carries the 'classid' assigned.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class character_selected extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'block_playerhud_rpg_progress';
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_character_selected', 'block_playerhud');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $classid = $this->other['classid'] ?? 0;
        return "The user with id '{$this->relateduserid}' selected the character with id '{$classid}' " .
            "in the block instance with context id '{$this->contextid}'.";
    }
}
