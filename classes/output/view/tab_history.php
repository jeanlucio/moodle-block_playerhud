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
 * History tab output renderer for students.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use templatable;
use moodle_url;

/**
 * Class tab_history
 *
 * @package block_playerhud
 */
class tab_history implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var int Block instance ID. */
    protected $instanceid;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player object.
     * @param int $instanceid Block instance ID.
     */
    public function __construct($config, $player, $instanceid) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
    }

    /**
     * Helper to generate sort data for headers.
     *
     * @param string $colname Column name.
     * @param string $label Label text.
     * @param string $currentsort Current sort column.
     * @param string $currentdir Current direction.
     * @param moodle_url $baseurl Base URL.
     * @return array Sort data structure.
     */
    private function get_sort_data(
        string $colname,
        string $label,
        string $currentsort,
        string $currentdir,
        moodle_url $baseurl
    ): array {
        $icon = 'fa-sort text-muted ph-opacity-low ms-1';
        $nextdir = 'ASC';
        if ($currentsort === $colname) {
            $nextdir = ($currentdir === 'ASC') ? 'DESC' : 'ASC';
            $icon = ($currentdir === 'ASC') ? 'fa-sort-asc text-primary ms-1' : 'fa-sort-desc text-primary ms-1';
        }
        $url = new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]);

        return [
            'url'        => $url->out(false),
            'label'      => $label,
            'icon_class' => $icon,
        ];
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template($output) {
        $page = optional_param('page', 0, PARAM_INT);
        $sort = optional_param('sort', 'timecreated', PARAM_ALPHAEXT);
        $dir  = optional_param('dir', 'DESC', PARAM_ALPHA);
        $filtertype = optional_param('f_type', '', PARAM_ALPHAEXT);
        $filtertext = optional_param('f_text', '', PARAM_TEXT);
        $showall = optional_param('showall', 0, PARAM_INT);

        $courseid = required_param('id', PARAM_INT);
        $baseurl = new moodle_url('/blocks/playerhud/view.php', [
            'id' => $courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'history',
        ]);

        if (!empty($filtertype)) {
            $baseurl->param('f_type', $filtertype);
        }
        if (!empty($filtertext)) {
            $baseurl->param('f_text', $filtertext);
        }
        if ($showall) {
            $baseurl->param('showall', 1);
        }

        $logdata = $this->get_audit_logs(
            $this->player->userid,
            $page,
            $sort,
            $dir,
            $filtertype,
            $filtertext,
            $showall,
            $baseurl,
            $output
        );

        $headers = [
            'date'    => $this->get_sort_data(
                'timecreated',
                get_string('report_col_date', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'type'    => $this->get_sort_data(
                'event_type',
                get_string('report_col_type', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'element' => $this->get_sort_data(
                'object_name',
                get_string('report_col_desc', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'xp'      => $this->get_sort_data(
                'xp_gained',
                get_string('xp', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
            'details' => $this->get_sort_data(
                'details',
                get_string('report_col_details', 'block_playerhud'),
                $sort,
                $dir,
                $baseurl
            ),
        ];

        $typeoptions = [
            ['value' => '', 'label' => get_string('all'), 'selected' => ($filtertype === '')],
            ['value' => 'item', 'label' => get_string('items', 'block_playerhud'), 'selected' => ($filtertype === 'item')],
            ['value' => 'trade', 'label' => get_string('tab_trades', 'block_playerhud'), 'selected' => ($filtertype === 'trade')],
            [
                'value' => 'item_revoked',
                'label' => get_string('report_type_revoked', 'block_playerhud'),
                'selected' => ($filtertype === 'item_revoked'),
            ],
            [
                'value' => 'quest',
                'label' => get_string('tab_quests', 'block_playerhud'),
                'selected' => ($filtertype === 'quest'),
            ],
        ];

        $toggleurl = new moodle_url($baseurl, ['showall' => $showall ? 0 : 1, 'page' => 0]);

        return [
            'has_logs'   => !empty($logdata['logs']),
            'logs'       => $logdata['logs'],
            'paging_bar' => $logdata['paging_bar'],
            'headers'    => $headers,
            'showall'    => $showall,
            'url_toggle_showall' => $toggleurl->out(false),
            'filters'    => [
                'types'      => $typeoptions,
                'text'       => $filtertext,
                'action_url' => (new moodle_url('/blocks/playerhud/view.php'))->out(false),
                'courseid'   => $courseid,
                'instanceid' => $this->instanceid,
                'sesskey'    => sesskey(),
            ],
            'str'        => [
                'empty'         => get_string('history_empty', 'block_playerhud'),
                'title'         => get_string('tab_history', 'block_playerhud'),
                'desc'          => get_string('history_desc', 'block_playerhud'),
                'filter_btn'    => get_string('filter'),
                'filter_clr'    => get_string('clear'),
                'btn_showall'   => get_string('showall', 'moodle', $logdata['total']),
                'btn_showpaged' => get_string('showperpage', 'moodle', 30),
                'search_any'    => get_string('search_any_term', 'block_playerhud'),
                'col_num'       => get_string('col_number', 'block_playerhud'),
            ],
        ];
    }

    /**
     * Get paginated and filtered user audit logs.
     *
     * @param int $userid User ID.
     * @param int $page Current page.
     * @param string $sort Sort column.
     * @param string $dir Sort direction.
     * @param string $filtertype Type filter.
     * @param string $filtertext Text filter.
     * @param int $showall Show all records flag.
     * @param moodle_url $baseurl Base URL for pagination.
     * @param \renderer_base $output Renderer base.
     * @return array Array containing logs and paging bar HTML.
     */
    private function get_audit_logs(
        int $userid,
        int $page,
        string $sort,
        string $dir,
        string $filtertype,
        string $filtertext,
        int $showall,
        moodle_url $baseurl,
        $output
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
                       WHEN inv.source = 'revoked' AND COALESCE(d.maxusage, 1) > 0 THEN -i.xp
                       WHEN inv.source IN ('map', 'teacher') AND COALESCE(d.maxusage, 1) > 0 THEN i.xp
                       ELSE 0
                   END AS xp_gained,
                   i.id AS itemid, inv.id AS inventory_id, 0 AS trade_id
              FROM {block_playerhud_inventory} inv
              JOIN {block_playerhud_items} i ON inv.itemid = i.id
         LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
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
            'p1' => $this->instanceid,
            'u2' => $userid,
            'p2' => $this->instanceid,
            'u3' => $userid,
            'p3' => $this->instanceid,
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

        // New Show All Logic.
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

        $results = [];
        if ($logs) {
            $fakeitems = [];
            $tradeids = [];

            foreach ($logs as $log) {
                if ($log->itemid > 0) {
                    $fakeitems[$log->itemid] = (object)['id' => $log->itemid, 'image' => $log->icon];
                }
                if ($log->event_type === 'trade' && $log->trade_id > 0) {
                    $tradeids[$log->trade_id] = $log->trade_id;
                }
            }

            $tradecosts = [];
            if (!empty($tradeids)) {
                [$tinsql, $tinparams] = $DB->get_in_or_equal($tradeids, SQL_PARAMS_NAMED, 'trd');
                $sqlreq = "SELECT req.id, req.tradeid, req.qty, i.name
                             FROM {block_playerhud_trade_reqs} req
                             JOIN {block_playerhud_items} i ON req.itemid = i.id
                            WHERE req.tradeid $tinsql";
                $reqs = $DB->get_records_sql($sqlreq, $tinparams);
                foreach ($reqs as $req) {
                    $tradecosts[$req->tradeid][] = $req->qty . 'x ' . format_string($req->name);
                }
            }

            $context = \context_block::instance($this->instanceid);
            $allmedia = \block_playerhud\utils::get_items_display_data($fakeitems, $context);
            $counter = $limitfrom + 1;

            foreach ($logs as $log) {
                $srckey = 'report_src_' . $log->details;
                $detailtext = get_string_manager()->string_exists($srckey, 'block_playerhud') ?
                    get_string($srckey, 'block_playerhud') : $log->details;

                $badgeclass = 'bg-secondary text-white';
                $badgetext  = get_string('report_type_other', 'block_playerhud');
                $isimageicon = false;
                $iconurl = '';
                $iconemoji = $log->icon;

                if ($log->event_type === 'item') {
                    $badgeclass = 'bg-primary text-white';
                    $badgetext  = get_string('report_type_item', 'block_playerhud');
                    if (isset($allmedia[$log->itemid])) {
                        $media = $allmedia[$log->itemid];
                        $isimageicon = $media['is_image'];
                        $iconurl = $media['is_image'] ? $media['url'] : '';
                        $iconemoji = $media['is_image'] ? '' : strip_tags($media['content']);
                    }
                } else if ($log->event_type === 'item_revoked') {
                    $badgeclass = 'bg-danger text-white';
                    $badgetext  = get_string('report_type_revoked', 'block_playerhud');
                    if (isset($allmedia[$log->itemid])) {
                        $media = $allmedia[$log->itemid];
                        $isimageicon = $media['is_image'];
                        $iconurl = $media['is_image'] ? $media['url'] : '';
                        $iconemoji = $media['is_image'] ? '' : strip_tags($media['content']);
                    }
                    $log->inventory_id = 0;
                } else if ($log->event_type === 'item_consumed') {
                    $badgeclass = 'bg-warning text-dark';
                    $badgetext  = get_string('report_type_consumed', 'block_playerhud');
                    if (isset($allmedia[$log->itemid])) {
                        $media = $allmedia[$log->itemid];
                        $isimageicon = $media['is_image'];
                        $iconurl = $media['is_image'] ? $media['url'] : '';
                        $iconemoji = $media['is_image'] ? '' : strip_tags($media['content']);
                    }
                    $log->inventory_id = 0;
                } else if ($log->event_type === 'trade') {
                    $badgeclass = 'bg-success text-white';
                    $badgetext  = get_string('report_type_trade', 'block_playerhud');
                    $detailtext = get_string('report_status_transaction', 'block_playerhud');

                    if (isset($tradecosts[$log->trade_id])) {
                        $coststr = implode(', ', $tradecosts[$log->trade_id]);
                        $strcost = get_string('trade_cost', 'block_playerhud');
                        $iconminus = '<i class="fa fa-minus-circle" aria-hidden="true"></i>';
                        $detailtext .= "<small class=\"text-danger d-block mt-1 text-wrap\">" .
                            "{$iconminus} {$strcost} {$coststr}</small>";
                    }
                } else if ($log->event_type === 'quest') {
                    $badgeclass = 'bg-warning text-dark';
                    $badgetext  = get_string('report_type_quest', 'block_playerhud');
                    $iconemoji  = !empty($log->icon) ? $log->icon : '🏅';
                    $detailtext = get_string('quest_status_completed', 'block_playerhud');
                }

                $xpbadge = '';
                if ($log->xp_gained > 0) {
                    $xpbadge = '<span class="badge bg-success text-white ph-text-xs">+' . $log->xp_gained . ' XP</span>';
                } else if ($log->xp_gained < 0) {
                    $xpbadge = '<span class="badge bg-danger text-white ph-text-xs">' . $log->xp_gained . ' XP</span>';
                } else {
                    $xpbadge = '<span class="text-muted small">-</span>';
                }

                $results[] = [
                    'counter'       => $counter++,
                    'date'          => userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')),
                    'badge_class'   => $badgeclass,
                    'badge_text'    => $badgetext,
                    'is_image_icon' => $isimageicon,
                    'icon_url'      => $iconurl,
                    'icon_emoji'    => $iconemoji,
                    'object_name'   => format_string($log->object_name),
                    'xp_badge'      => $xpbadge,
                    'details_html'  => $detailtext,
                ];
            }
        }
        return ['logs' => $results, 'paging_bar' => $pagingbar, 'total' => $totalrecords];
    }

    /**
     * Display method required by view.php controller pattern.
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template(
            'block_playerhud/tab_history',
            $this->export_for_template($OUTPUT)
        );
    }
}
