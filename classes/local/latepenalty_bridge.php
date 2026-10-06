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
 * Compatibility bridge to the optional local_latepenalty plugin.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Reads data from local_latepenalty regardless of which release of it is installed.
 *
 * local_latepenalty is a soft dependency, and the PHP API it exposes changed in 1.2.0: the
 * static penalty_helper::get_deadline() was replaced by the deadline_resolver class. Calling
 * either one directly would fatal on the other generation, so every call goes through here and
 * the generation is detected by the API actually present, never by a version number.
 *
 * @package block_playerhud
 */
class latepenalty_bridge {
    /**
     * The deadline a student currently has in an activity.
     *
     * With local_latepenalty 1.2.0 or later this is the plugin's own chain (the student's
     * override, the group's, the activity's own extension or override, the due date, the
     * "Set reminder in Timeline" date). Older releases only know the activity-level deadline,
     * so the student's own override is not part of the answer there and the caller reads it.
     *
     * @param \stdClass $cm Course module record, with the module name in modname.
     * @param int $userid Student ID.
     * @return int|null Deadline timestamp, or null when no deadline applies.
     */
    public static function get_deadline(\stdClass $cm, int $userid): ?int {
        if (class_exists('\local_latepenalty\local\deadline_resolver')) {
            $deadline = \local_latepenalty\local\deadline_resolver::for_user($cm, $userid);
            return $deadline->exists() ? $deadline->time : null;
        }

        if (method_exists('\local_latepenalty\penalty_helper', 'get_deadline')) {
            return \local_latepenalty\penalty_helper::get_deadline($cm);
        }

        return null;
    }
}
