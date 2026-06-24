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

namespace block_playerhud\controller;

/**
 * Controller for handling data export (Grades and XP).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export {
    /**
     * Executes the export process using Moodle core dataformat.
     *
     * @param int $courseid The course ID.
     * @param int $instanceid The block instance ID.
     * @param string $format The export format (csv, excel, etc).
     * @param string $courseshortname The course shortname for the filename.
     * @return void
     */
    public function execute(int $courseid, int $instanceid, string $format, string $courseshortname): void {
        [$columns, $exportdata] = $this->build_export($courseid, $instanceid);

        $filename = 'playerhud_grades_' . format_string($courseshortname) . '_' . date('Ymd');

        // Trigger download using Moodle Native API.
        // This handles all HTTP headers, buffering, and encoding automatically.
        \core\dataformat::download_data($filename, $format, $columns, $exportdata);
        die();
    }

    /**
     * Builds the localized columns and the per-student rows for the export.
     *
     * Holders of block/playerhud:manage (teachers/managers) are excluded so the
     * export reflects students only. The level is derived from the stored XP and
     * capped at the configured maximum.
     *
     * @param int $courseid The course ID.
     * @param int $instanceid The block instance ID.
     * @return array A two-element list with the columns and the student rows.
     */
    public function build_export(int $courseid, int $instanceid): array {
        global $DB;

        // 1. Load block configuration.
        $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels  = isset($config->max_levels) ? (int)$config->max_levels : 20;

        // 2. Identify managers/teachers to exclude them from the export.
        $coursecontext = \context_course::instance($courseid);
        $managers = get_users_by_capability($coursecontext, 'block/playerhud:manage', 'u.id');
        $managerids = array_keys($managers);

        $excludeclause = '';
        $excludeparams = [];
        if (!empty($managerids)) {
            [$insql, $excludeparams] = $DB->get_in_or_equal($managerids, SQL_PARAMS_NAMED, 'exm', false);
            $excludeclause = "AND pu.userid $insql";
        }

        $userfieldsapi = \core_user\fields::for_name();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        // 3. Bulk Query (Zero N+1 Queries) to fetch all relevant student data.
        $sql = "SELECT u.id, $userfields, u.email, pu.currentxp, pu.timemodified,
                       (SELECT COUNT(inv.id)
                          FROM {block_playerhud_inventory} inv
                          JOIN {block_playerhud_items} it ON inv.itemid = it.id
                         WHERE inv.userid = u.id
                           AND it.blockinstanceid = :p1
                           AND inv.source NOT IN ('revoked', 'consumed')) AS total_items
                  FROM {user} u
                  JOIN {block_playerhud_user} pu ON pu.userid = u.id
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :enrolcourseid AND e.status = 0
                 WHERE pu.blockinstanceid = :p2
                   $excludeclause
              ORDER BY pu.currentxp DESC, pu.timemodified ASC, u.lastname ASC";

        $params = ['p1' => $instanceid, 'p2' => $instanceid, 'enrolcourseid' => $courseid];
        if (!empty($excludeparams)) {
            $params = array_merge($params, $excludeparams);
        }

        $users = $DB->get_records_sql($sql, $params);

        // 4. Format data array for the exporter.
        $exportdata = [];
        if ($users) {
            foreach ($users as $user) {
                $rawlevel = floor($user->currentxp / $xpperlevel) + 1;
                $level = ($rawlevel > $maxlevels) ? $maxlevels : (int)$rawlevel;

                $exportdata[] = [
                    $user->firstname,
                    $user->lastname,
                    $user->email,
                    $level,
                    (int)$user->currentxp,
                    (int)$user->total_items,
                    userdate($user->timemodified, get_string('strftimedatetime', 'langconfig')),
                ];
            }
        }

        // 5. Define localized headers.
        $columns = [
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('level', 'block_playerhud'),
            get_string('xp', 'block_playerhud'),
            get_string('items', 'block_playerhud'),
            get_string('report_last_action', 'block_playerhud'),
        ];

        return [$columns, $exportdata];
    }
}
