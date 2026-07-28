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
 * Event fired when a user claims a quest reward.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Fired whenever a user claims a completed quest's reward.
 *
 * The 'other' payload carries the 'questid' claimed and the 'xp' actually
 * awarded by the quest's own reward_xp field.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quest_collected extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'block_playerhud_quest_log';
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_quest_collected', 'block_playerhud');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $questid = $this->other['questid'] ?? 0;
        return "The user with id '{$this->relateduserid}' claimed the quest with id '{$questid}' " .
            "in the block instance with context id '{$this->contextid}'.";
    }
}
