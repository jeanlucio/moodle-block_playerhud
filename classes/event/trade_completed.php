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
 * Event fired when a user completes a trade in the shop.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Fired whenever a user successfully executes a trade.
 *
 * The 'other' payload carries the 'tradeid' completed.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trade_completed extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['objecttable'] = 'block_playerhud_trade_log';
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_trade_completed', 'block_playerhud');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $tradeid = $this->other['tradeid'] ?? 0;
        return "The user with id '{$this->relateduserid}' completed the trade with id '{$tradeid}' " .
            "in the block instance with context id '{$this->contextid}'.";
    }
}
