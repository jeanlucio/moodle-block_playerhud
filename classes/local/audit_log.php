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
 * Shared audit log query for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

use moodle_url;

/**
 * Builds the combined item/trade/quest audit log query.
 *
 * Extracted from the teacher's Reports tab and the student's own History tab, which used to
 * keep two independent copies of this query. Both renderers call get_logs() for the raw,
 * paginated data, then keep their own presentation loop (badge classes, icons, and the
 * teacher-only revoke link), since that part genuinely differs between the two views.
 *
 * @package block_playerhud
 */
class audit_log {
    /**
     * Get the paginated, filtered and sorted audit log for a single student.
     *
     * @param int $instanceid The block instance ID.
     * @param int $userid The student whose log is being read.
     * @param int $page Current page.
     * @param string $sort Sort column.
     * @param string $dir Sort direction.
     * @param string $filtertype Type filter.
     * @param string $filtertext Text filter.
     * @param int $showall Show all records flag.
     * @param moodle_url $baseurl Base URL for pagination.
     * @param \core\output\core_renderer|\core\output\bootstrap_renderer $output The renderer used
     *        to build the paging bar. May still be the bootstrap_renderer stand-in when called
     *        from the teacher's Reports tab, since manage.php builds tab content before calling
     *        $OUTPUT->header().
     * @return array {logs: \stdClass[], paging_bar: string, total: int, limitfrom: int}.
     */
    public static function get_logs(
        int $instanceid,
        int $userid,
        int $page,
        string $sort,
        string $dir,
        string $filtertype,
        string $filtertext,
        int $showall,
        moodle_url $baseurl,
        \core\output\core_renderer|\core\output\bootstrap_renderer $output
    ): array {
        global $DB;

        $concatitem  = $DB->sql_concat("'item_'", "inv.id");
        $concattrade = $DB->sql_concat("'trade_'", "tl.id");
        $concatquest = $DB->sql_concat("'quest_'", "ql.id");

        $innersql = "
            SELECT {$concatitem} AS uniqueid,
                   CASE WHEN inv.source = 'revoked' THEN 'item_revoked'
                        WHEN inv.source = 'consumed' THEN 'item_consumed'
                        ELSE 'item' END AS event_type,
                   i.name AS object_name, inv.timecreated,
                   inv.source AS details, i.image AS icon,
                   CASE
                       WHEN inv.source = 'revoked' THEN -inv.xpawarded
                       ELSE inv.xpawarded
                   END AS xp_gained,
                   i.id AS itemid, inv.id AS inventory_id, 0 AS trade_id
              FROM {block_playerhud_inventory} inv
              JOIN {block_playerhud_items} i ON inv.itemid = i.id
             WHERE inv.userid = :u1 AND i.blockinstanceid = :p1
            UNION ALL
            SELECT {$concattrade} AS uniqueid, 'trade' AS event_type, t.name AS object_name, tl.timecreated,
                   'trade_completed' AS details, '⚖️' AS icon, 0 AS xp_gained, 0 AS itemid,
                   0 AS inventory_id, t.id AS trade_id
              FROM {block_playerhud_trade_log} tl
              JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
             WHERE tl.userid = :u2 AND t.blockinstanceid = :p2
            UNION ALL
            SELECT {$concatquest} AS uniqueid, 'quest' AS event_type, q.name AS object_name, ql.timecreated,
                   'quest_claim' AS details, q.image_done AS icon, q.reward_xp AS xp_gained,
                   0 AS itemid, 0 AS inventory_id, 0 AS trade_id
              FROM {block_playerhud_quest_log} ql
              JOIN {block_playerhud_quests} q ON ql.questid = q.id
             WHERE ql.userid = :u3 AND q.blockinstanceid = :p3";

        $params = [
            'u1' => $userid,
            'p1' => $instanceid,
            'u2' => $userid,
            'p2' => $instanceid,
            'u3' => $userid,
            'p3' => $instanceid,
        ];

        $where = "1=1";
        if (!empty($filtertype)) {
            $where .= " AND event_type = :ftype";
            $params['ftype'] = $filtertype;
        }
        if (!empty($filtertext)) {
            $likeobj = $DB->sql_like('object_name', ':ftext1', false, false);
            $likedet = $DB->sql_like('details', ':ftext2', false, false);
            $where .= " AND ({$likeobj} OR {$likedet})";
            $params['ftext1'] = '%' . $DB->sql_like_escape($filtertext) . '%';
            $params['ftext2'] = '%' . $DB->sql_like_escape($filtertext) . '%';
        }

        $allowedsorts = ['timecreated', 'event_type', 'object_name', 'xp_gained', 'details'];
        if (!in_array($sort, $allowedsorts)) {
            $sort = 'timecreated';
        }
        $dir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

        $sqlcount = "SELECT COUNT(1) FROM ($innersql) combined_log WHERE $where";
        $totalrecords = $DB->count_records_sql($sqlcount, $params);

        $perpage = 30;
        if ($showall) {
            $limitfrom = 0;
            $limitnum = 0;
            $perpage = ($totalrecords > 0) ? $totalrecords : 30;
        } else {
            $limitfrom = $page * $perpage;
            $limitnum = $perpage;
        }

        $sql = "SELECT * FROM ($innersql) combined_log WHERE $where ORDER BY {$sort} {$dir}";
        $logs = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        $pagingbar = $output->paging_bar($totalrecords, $page, $perpage, $baseurl);

        return [
            'logs' => $logs,
            'paging_bar' => $pagingbar,
            'total' => $totalrecords,
            'limitfrom' => $limitfrom,
        ];
    }
}
