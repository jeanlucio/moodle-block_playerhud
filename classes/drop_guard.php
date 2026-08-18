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
     * Returns how many times a user has picked up a given drop.
     *
     * Sums two sources: block_playerhud_inventory (every pickup recorded before item
     * quantity tracking moved to block_playerhud_stack_log, frozen and never touched again)
     * and block_playerhud_stack_log (every pickup since). Counts ALL records regardless of
     * source/sign so that items later traded away, or a stackable balance later spent, do
     * not reset the pickup counter — a drop's own usage limit is about how many times it was
     * collected, not about what the user still holds today.
     *
     * @param int $dropid The drop ID.
     * @param int $userid The user ID.
     * @return int
     */
    public static function get_pickup_count(int $dropid, int $userid): int {
        global $DB;

        $legacy = $DB->count_records('block_playerhud_inventory', ['userid' => $userid, 'dropid' => $dropid]);
        $new = $DB->count_records('block_playerhud_stack_log', ['userid' => $userid, 'dropid' => $dropid]);

        return $legacy + $new;
    }

    /**
     * Returns the timestamp of a user's most recent pickup of a given drop, or 0 if none.
     *
     * Same two-source sum as get_pickup_count(), taking the latest timestamp across both.
     *
     * @param int $dropid The drop ID.
     * @param int $userid The user ID.
     * @return int
     */
    public static function get_last_pickup_time(int $dropid, int $userid): int {
        global $DB;

        $params = ['userid' => $userid, 'dropid' => $dropid];
        $legacy = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {block_playerhud_inventory} WHERE userid = :userid AND dropid = :dropid",
            $params
        );
        $new = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {block_playerhud_stack_log} WHERE userid = :userid AND dropid = :dropid",
            $params
        );

        return max((int)$legacy, (int)$new);
    }

    /**
     * Checks whether a user is allowed to pick up a drop right now.
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
        $count = self::get_pickup_count($dropid, $userid);

        if ($maxusage > 0 && $count >= $maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        if ($count > 0 && $respawntime > 0) {
            $readytime = self::get_last_pickup_time($dropid, $userid) + $respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', '', $minutesleft);
            }
        }
    }
}
