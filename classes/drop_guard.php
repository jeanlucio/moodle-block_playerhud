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

namespace block_playerhud;

/**
 * Guard class that enforces drop pickup rules (limit and cooldown).
 *
 * Extracted from the collect controller so the logic can be unit-tested
 * independently of the HTTP request lifecycle.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drop_guard {
    /**
     * Checks whether a user is allowed to pick up a drop right now.
     *
     * Counts ALL inventory records for the drop (including source='consumed')
     * so that items traded away do not reset the pickup counter.
     *
     * @param int $dropid The drop ID.
     * @param int $userid The user ID.
     * @param int $maxusage Maximum pickups allowed (0 = unlimited).
     * @param int $respawntime Cooldown in seconds between pickups (0 = none).
     * @throws \moodle_exception If the pickup limit or cooldown is active.
     */
    public static function check_pickup_allowed(
        int $dropid,
        int $userid,
        int $maxusage,
        int $respawntime
    ): void {
        global $DB;

        $inventory = $DB->get_records(
            'block_playerhud_inventory',
            ['userid' => $userid, 'dropid' => $dropid],
            'timecreated DESC'
        );

        $count = count($inventory);
        $lastcollected = reset($inventory);

        if ($maxusage > 0 && $count >= $maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        if ($lastcollected && $respawntime > 0) {
            $readytime = $lastcollected->timecreated + $respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', '', $minutesleft);
            }
        }
    }
}
