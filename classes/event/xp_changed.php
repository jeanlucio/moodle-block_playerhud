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
 * Event fired when a user's PlayerHUD XP changes.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Fired whenever a user's XP changes (gain or loss) in a block instance.
 *
 * The 'other' payload carries the signed 'delta' actually applied, the
 * 'courseid' where it happened and the resulting 'newxp'. Loss deltas are
 * negative. Consumers (e.g. the PlayerGames hub) can mirror the change.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xp_changed extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'block_playerhud_user';
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_xp_changed', 'block_playerhud');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $delta = $this->other['delta'] ?? 0;
        return "The user with id '{$this->relateduserid}' had their PlayerHUD XP changed by " .
            "{$delta} in the block instance with context id '{$this->contextid}'.";
    }
}
