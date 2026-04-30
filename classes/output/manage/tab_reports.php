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
 * Reports tab management.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use templatable;
use moodle_url;

/**
 * Class tab_reports
 *
 * @package block_playerhud
 */
class tab_reports implements renderable, templatable {
    /** @var int The block instance ID. */
    protected $instanceid;

    /** @var int The course ID. */
    protected $courseid;

    /** @var int Selected user ID for audit. */
    protected $selecteduserid;

    /** @var string Sort column for main table. */
    protected $sort;

    /** @var string Sort direction for main table. */
    protected $dir;

    /**
     * Constructor.
     *
     * @param int $instanceid
     * @param int $courseid
     * @param string $sort
     * @param string $dir
     */
    public function __construct(int $instanceid, int $courseid, string $sort = 'xp', string $dir = 'DESC') {
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
        $this->selecteduserid = optional_param('r_userid', 0, PARAM_INT);
        $this->sort = empty($sort) ? 'xp' : $sort;
        $this->dir  = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * Export data for template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template($output) {
        global $DB, $PAGE;

        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'reports',
        ]);

        $bi = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        $xpperlevel = $config->xp_per_level ?? 100;
        $maxlevels = $config->max_levels ?? 20;

        $contextdata = [
            'is_audit'      => ($this->selecteduserid > 0),
            'kpis'          => $this->get_kpi_data(),
            'charts'        => $this->get_charts_data($xpperlevel, $maxlevels),
            'quest_stats'   => $this->get_quest_stats_data(),
            'user_selector' => $this->get_user_selector_data(),
        ];

        if ($this->selecteduserid > 0) {
            $page = optional_param('page', 0, PARAM_INT);
            $auditsort = optional_param('audit_sort', 'timecreated', PARAM_ALPHAEXT);
            $auditdir  = optional_param('audit_dir', 'DESC', PARAM_ALPHA);
            $filtertype = optional_param('f_type', '', PARAM_ALPHAEXT);
            $filtertext = optional_param('f_text', '', PARAM_TEXT);
            $showall = optional_param('showall', 0, PARAM_INT);

            $auditbaseurl = new moodle_url($baseurl, ['r_userid' => $this->selecteduserid]);

            if (!empty($filtertype)) {
                $auditbaseurl->param('f_type', $filtertype);
            }
            if (!empty($filtertext)) {
                $auditbaseurl->param('f_text', $filtertext);
            }
            if ($showall) {
                $auditbaseurl->param('showall', 1);
            }

            $logdata = $this->get_audit_logs(
                $this->selecteduserid,
                $page,
                $auditsort,
                $auditdir,
                $filtertype,
                $filtertext,
                $showall,
                $auditbaseurl,
                $output
            );

            $contextdata['url_back']       = $baseurl->out(false);
            $contextdata['str_back']       = get_string('back', 'block_playerhud');
            $contextdata['audit_logs']     = $logdata['logs'];
            $contextdata['has_audit_logs'] = !empty($logdata['logs']);
            $contextdata['paging_bar']     = $logdata['paging_bar'];

            $contextdata['r_userid'] = $this->selecteduserid;
            $contextdata['courseid'] = $this->courseid;
            $contextdata['instanceid'] = $this->instanceid;
            $contextdata['sesskey'] = sesskey();
            $contextdata['grant_action_url'] = (new moodle_url('/blocks/playerhud/manage.php'))->out(false);

            $allitems = $DB->get_records_menu(
                'block_playerhud_items',
                ['blockinstanceid' => $this->instanceid, 'enabled' => 1],
                'name ASC',
                'id, name'
            );

            $itemoptions = [];
            if ($allitems) {
                foreach ($allitems as $iid => $iname) {
                    $itemoptions[] = ['value' => $iid, 'label' => format_string($iname)];
                }
            }
            $contextdata['available_items'] = $itemoptions;
            $contextdata['has_available_items'] = !empty($itemoptions);

            $contextdata['audit_headers'] = [
                'date'    => $this->get_audit_sort_data(
                    'timecreated',
                    get_string('report_col_date', 'block_playerhud'),
                    $auditsort,
                    $auditdir,
                    $auditbaseurl
                ),
                'type'    => $this->get_audit_sort_data(
                    'event_type',
                    get_string('report_col_type', 'block_playerhud'),
                    $auditsort,
                    $auditdir,
                    $auditbaseurl
                ),
                'element' => $this->get_audit_sort_data(
                    'object_name',
                    get_string('report_col_desc', 'block_playerhud'),
                    $auditsort,
                    $auditdir,
                    $auditbaseurl
                ),
                'xp'      => $this->get_audit_sort_data(
                    'xp_gained',
                    get_string('xp', 'block_playerhud'),
                    $auditsort,
                    $auditdir,
                    $auditbaseurl
                ),
                'details' => $this->get_audit_sort_data(
                    'details',
                    get_string('report_col_details', 'block_playerhud'),
                    $auditsort,
                    $auditdir,
                    $auditbaseurl
                ),
            ];

            $contextdata['filters'] = [
                'types' => [
                    [
                        'value' => '',
                        'label' => get_string('all'),
                        'selected' => ($filtertype === ''),
                    ],
                    [
                        'value' => 'item',
                        'label' => get_string('items', 'block_playerhud'),
                        'selected' => ($filtertype === 'item'),
                    ],
                    [
                        'value' => 'trade',
                        'label' => get_string('tab_trades', 'block_playerhud'),
                        'selected' => ($filtertype === 'trade'),
                    ],
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
                ],
                'text' => $filtertext,
                'action_url' => (new moodle_url('/blocks/playerhud/manage.php'))->out(false),
            ];

            $toggleurl = new moodle_url($baseurl, [
                'r_userid' => $this->selecteduserid,
                'showall'  => $showall ? 0 : 1,
                'page'     => 0,
            ]);
            $contextdata['showall'] = $showall;
            $contextdata['url_toggle_showall'] = $toggleurl->out(false);
        } else {
            $contextdata['headers'] = [
                'student' => $this->get_sort_data('student', get_string('student', 'block_playerhud'), $baseurl),
                'xp'      => $this->get_sort_data('xp', get_string('report_status_level', 'block_playerhud'), $baseurl),
                'items'   => $this->get_sort_data('items', get_string('items', 'block_playerhud'), $baseurl),
            ];
            $contextdata['students']     = $this->get_students_data($xpperlevel, $maxlevels);
            $contextdata['has_students'] = !empty($contextdata['students']);

            $contextdata['url_export'] = (new moodle_url('/blocks/playerhud/export.php', [
                'id' => $this->courseid,
                'instanceid' => $this->instanceid,
            ]))->out(false);
        }

        $contextdata['ai_logs']     = $this->get_ai_logs();
        $contextdata['has_ai_logs'] = !empty($contextdata['ai_logs']);

        $contextdata['str'] = [
            'leaderboard'       => get_string('leaderboard_title', 'block_playerhud'),
            'summary'           => get_string('summary', 'block_playerhud'),
            'level'             => get_string('level', 'block_playerhud'),
            'xp'                => get_string('xp', 'block_playerhud'),
            'action'            => get_string('report_action', 'block_playerhud'),
            'audit'             => get_string('report_audit', 'block_playerhud'),
            'no_logs'           => get_string('report_no_logs', 'block_playerhud'),
            'ai_title'          => get_string('report_ai_title', 'block_playerhud'),
            'ai_sub'            => get_string('report_ai_subtitle', 'block_playerhud'),
            'col_date'          => get_string('report_col_date', 'block_playerhud'),
            'last_action'       => get_string('report_last_action', 'block_playerhud'),
            'col_type'          => get_string('report_col_type', 'block_playerhud'),
            'col_desc'          => get_string('report_col_desc', 'block_playerhud'),
            'col_details'       => get_string('report_col_details', 'block_playerhud'),
            'col_object'        => get_string('report_col_object', 'block_playerhud'),
            'col_ai'            => get_string('report_col_ai', 'block_playerhud'),
            'grant_item_select' => get_string('grant_item_select', 'block_playerhud'),
            'revoke_item'       => get_string('revoke_item', 'block_playerhud'),
            'confirm_revoke'    => get_string('confirm_revoke', 'block_playerhud'),
            'delete'            => get_string('delete'),
            'view'              => get_string('view'),
            'btn_more'          => get_string('report_show_more', 'block_playerhud'),
            'filter_btn'        => get_string('filter'),
            'filter_clr'        => get_string('clear'),
            'btn_showall'       => get_string('showall', 'moodle', isset($logdata) ? $logdata['total'] : 0),
            'btn_showpaged'     => get_string('showperpage', 'moodle', 30),
            'search_any'        => get_string('search_any_term', 'block_playerhud'),
            'col_num'           => get_string('col_number', 'block_playerhud'),
            'export_csv'        => get_string('export_csv', 'block_playerhud'),
            'export_excel'      => get_string('export_excel', 'block_playerhud'),
        ];

        $jsconfig = [
            'baseUrl'         => $baseurl->out(false),
            'strMore'         => get_string('report_show_more', 'block_playerhud'),
            'strLess'         => get_string('report_show_less', 'block_playerhud'),
            'strConfirmTitle' => get_string('confirmation', 'admin'),
            'strYes'          => get_string('yes'),
            'strCancel'       => get_string('cancel'),
        ];

        $PAGE->requires->js_call_amd('block_playerhud/manage_reports', 'init', [$jsconfig]);

        return $contextdata;
    }

    /**
     * Helper for sort links in general table.
     *
     * @param string $colname
     * @param string $label
     * @param moodle_url $baseurl
     * @return array
     */
    private function get_sort_data(string $colname, string $label, moodle_url $baseurl): array {
        $icon = 'fa-sort text-muted ph-opacity-low ms-1';
        $nextdir = 'ASC';
        if ($this->sort === $colname) {
            $nextdir = ($this->dir === 'ASC') ? 'DESC' : 'ASC';
            $icon = ($this->dir === 'ASC') ? 'fa-sort-asc text-primary ms-1' : 'fa-sort-desc text-primary ms-1';
        }
        $url = new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]);
        return [
            'url'        => $url->out(false),
            'label'      => $label,
            'icon_class' => $icon,
        ];
    }

    /**
     * Helper for sort links in audit logs.
     *
     * @param string $colname
     * @param string $label
     * @param string $currentsort
     * @param string $currentdir
     * @param moodle_url $baseurl
     * @return array
     */
    private function get_audit_sort_data(
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
        $url = new moodle_url($baseurl, ['audit_sort' => $colname, 'audit_dir' => $nextdir]);

        return [
            'url'        => $url->out(false),
            'label'      => $label,
            'icon_class' => $icon,
        ];
    }

    /**
     * Get KPI data.
     *
     * @return array
     */
    private function get_kpi_data(): array {
        global $DB;

        $totalxp = $DB->get_field_sql(
            "SELECT SUM(currentxp) FROM {block_playerhud_user} WHERE blockinstanceid = ?",
            [$this->instanceid]
        );

        $userfieldsapi = \core_user\fields::for_name();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $topstudent = $DB->get_record_sql(
            "SELECT u.id, $userfields, p.currentxp
               FROM {block_playerhud_user} p
               JOIN {user} u ON p.userid = u.id
              WHERE p.blockinstanceid = ?
           ORDER BY p.currentxp DESC",
            [$this->instanceid],
            IGNORE_MULTIPLE
        );

        $topitem = $DB->get_record_sql(
            "SELECT i.name, COUNT(inv.id) as qtd
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} i ON inv.itemid = i.id
              WHERE i.blockinstanceid = ?
           GROUP BY i.id, i.name
           ORDER BY qtd DESC",
            [$this->instanceid],
            IGNORE_MULTIPLE
        );

        return [
            [
                'title'    => get_string('report_total_xp', 'block_playerhud'),
                'value'    => number_format((float)$totalxp ?: 0, 0, ',', '.') . ' ' . get_string('xp', 'block_playerhud'),
                'subtitle' => '',
                'bg_class' => 'ph-bg-gradient-primary',
            ],
            [
                'title'    => get_string('report_leader', 'block_playerhud'),
                'value'    => $topstudent ? fullname($topstudent) : '-',
                'subtitle' => $topstudent ? $topstudent->currentxp . ' XP' : '',
                'bg_class' => 'ph-bg-gradient-success',
            ],
            [
                'title'    => get_string('report_most_collected', 'block_playerhud'),
                'value'    => $topitem ? format_string($topitem->name) : '-',
                'subtitle' => $topitem ? get_string('report_collected_times', 'block_playerhud', $topitem->qtd) : '',
                'bg_class' => 'ph-bg-gradient-info',
            ],
        ];
    }

    /**
     * Get chart and graph data.
     *
     * @param int $xpperlevel
     * @param int $maxlevel
     * @return array
     */
    private function get_charts_data(int $xpperlevel, int $maxlevel): array {
        global $DB;

        $userxps = $DB->get_records('block_playerhud_user', ['blockinstanceid' => $this->instanceid], '', 'id, currentxp');
        $levelscount = [];
        $maxbarvalue = 0;

        foreach ($userxps as $u) {
            $lvl = floor($u->currentxp / $xpperlevel) + 1;
            $key = ($lvl > $maxlevel) ? $maxlevel . '+' : (int)$lvl;

            if (!isset($levelscount[$key])) {
                $levelscount[$key] = 0;
            }
            $levelscount[$key]++;

            if ($levelscount[$key] > $maxbarvalue) {
                $maxbarvalue = $levelscount[$key];
            }
        }

        uksort($levelscount, function ($a, $b) {
            $vala = (int)str_replace('+', '', $a);
            $valb = (int)str_replace('+', '', $b);
            if ($vala == $valb) {
                return (strpos((string)$a, '+') !== false) ? 1 : -1;
            }
            return ($vala < $valb) ? -1 : 1;
        });

        $levelsdata = [];
        foreach ($levelscount as $lvllabel => $total) {
            $levelsdata[] = [
                'label'   => $lvllabel,
                'total'   => $total,
                'percent' => ($maxbarvalue > 0) ? ($total / $maxbarvalue) * 100 : 0,
            ];
        }

        return [
            'str_levels'  => get_string('report_chart_title', 'block_playerhud'),
            'str_no_logs' => get_string('report_no_logs', 'block_playerhud'),
            'has_levels'  => !empty($levelsdata),
            'levels'      => $levelsdata,
        ];
    }

    /**
     * Get quest completion statistics for the report chart.
     *
     * Returns per-quest completion counts and aggregate summary totals.
     * Zero N+1: single LEFT JOIN query covers all data needed.
     *
     * @return array
     */
    private function get_quest_stats_data(): array {
        global $DB;

        $sql = "SELECT q.id, q.name, COUNT(ql.id) AS claims
                  FROM {block_playerhud_quests} q
             LEFT JOIN {block_playerhud_quest_log} ql ON ql.questid = q.id
                 WHERE q.blockinstanceid = :pid AND q.enabled = 1
              GROUP BY q.id, q.name
              ORDER BY claims DESC, q.name ASC";

        $rows = $DB->get_records_sql($sql, ['pid' => $this->instanceid]);

        $strnodata = get_string('report_no_logs', 'block_playerhud');

        if (empty($rows)) {
            return [
                'str_title'   => get_string('report_quest_chart_title', 'block_playerhud'),
                'str_no_data' => $strnodata,
                'has_quests'  => false,
            ];
        }

        $total = count($rows);
        $engaged = 0;
        $totalclaims = 0;
        $maxclaims = 0;

        foreach ($rows as $row) {
            if ($row->claims > 0) {
                $engaged++;
            }
            $totalclaims += (int)$row->claims;
            if ($row->claims > $maxclaims) {
                $maxclaims = (int)$row->claims;
            }
        }

        $questsdata = [];
        foreach ($rows as $row) {
            $questsdata[] = [
                'label'     => format_string($row->name),
                'total'     => (int)$row->claims,
                'percent'   => ($maxclaims > 0) ? round(($row->claims / $maxclaims) * 100) : 0,
                'no_claims' => ($row->claims == 0),
            ];
        }

        return [
            'str_title'      => get_string('report_quest_chart_title', 'block_playerhud'),
            'str_no_data'    => $strnodata,
            'str_total'      => get_string('report_quest_total', 'block_playerhud'),
            'str_engaged'    => get_string('report_quest_engaged', 'block_playerhud'),
            'str_no_claims'  => get_string('report_quest_no_claims', 'block_playerhud'),
            'has_quests'     => true,
            'total'          => $total,
            'engaged'        => $engaged,
            'no_claims'      => $total - $engaged,
            'total_claims'   => $totalclaims,
            'quests'         => $questsdata,
        ];
    }

    /**
     * Get user selector dropdown data.
     *
     * @return array
     */
    private function get_user_selector_data(): array {
        global $DB;

        $userfieldsapi = \core_user\fields::for_name();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT u.id, $userfields, u.email
                  FROM {user} u
                  JOIN {block_playerhud_user} p ON p.userid = u.id
                 WHERE p.blockinstanceid = ?
              ORDER BY u.lastname, u.firstname";

        $users = $DB->get_records_sql($sql, [$this->instanceid]);

        $options = [[
            'value'    => 0,
            'label'    => get_string('report_select_user', 'block_playerhud'),
            'selected' => ($this->selecteduserid == 0),
        ]];

        foreach ($users as $u) {
            $options[] = [
                'value'    => $u->id,
                'label'    => fullname($u) . ' (' . $u->email . ')',
                'selected' => ($u->id == $this->selecteduserid),
            ];
        }

        return ['options' => $options];
    }

    /**
     * Get main students table data.
     *
     * @param int $xpperlevel
     * @param int $maxlevels
     * @return array
     */
    private function get_students_data(int $xpperlevel, int $maxlevels): array {
        global $DB;

        $coursecontext = \context_course::instance($this->courseid);
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

        $sortsql = "pu.currentxp DESC";
        switch ($this->sort) {
            case 'student':
                $sortsql = "u.lastname {$this->dir}, u.firstname {$this->dir}";
                break;
            case 'xp':
                $sortsql = "pu.currentxp {$this->dir}";
                break;
            case 'items':
                $sortsql = "total_items {$this->dir}";
                break;
        }

        $sql = "
            SELECT u.id, $userfields, u.email,
                   pu.currentxp, pu.enable_gamification, pu.timemodified,
                   (SELECT COUNT(inv.id) FROM {block_playerhud_inventory} inv
                    JOIN {block_playerhud_items} it ON inv.itemid = it.id
                   WHERE inv.userid = u.id AND it.blockinstanceid = :p1 AND inv.source != 'revoked') as total_items
              FROM {user} u
              JOIN {block_playerhud_user} pu ON pu.userid = u.id
             WHERE pu.blockinstanceid = :p2
               $excludeclause
          ORDER BY $sortsql";

        $params = [
            'p1' => $this->instanceid,
            'p2' => $this->instanceid,
        ];

        if (!empty($excludeparams)) {
            $params = array_merge($params, $excludeparams);
        }

        $users = $DB->get_records_sql($sql, $params);

        $results = [];
        $counter = 1;
        if ($users) {
            foreach ($users as $row) {
                $lastactiondate = userdate($row->timemodified, get_string('strftimedatetime', 'langconfig'));
                $isactive = ($row->enable_gamification == 1);

                $rawlevel = floor($row->currentxp / $xpperlevel) + 1;
                $level = ($rawlevel > $maxlevels) ? $maxlevels : (int)$rawlevel;

                $urlaudit = new moodle_url('/blocks/playerhud/manage.php', [
                    'id'         => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'tab'        => 'reports',
                    'r_userid'   => $row->id,
                ]);

                $results[] = [
                    'counter'     => $counter++,
                    'last_action' => $lastactiondate,
                    'id'          => $row->id,
                    'fullname'    => fullname($row),
                    'is_active'   => $isactive,
                    'row_class'   => $isactive ? '' : 'ph-row-inactive',
                    'name_class'  => $isactive ? 'fw-bold text-dark' : 'text-muted fst-italic',
                    'str_optout'  => get_string('status_off', 'block_playerhud'),
                    'level'       => $level,
                    'xp'          => $row->currentxp,
                    'total_items' => $row->total_items,
                    'url_audit'   => $urlaudit->out(false),
                ];
            }
        }
        return $results;
    }

    /**
     * Get paginated user audit logs for teacher's view.
     *
     * @param int $userid
     * @param int $page
     * @param string $sort
     * @param string $dir
     * @param string $filtertype
     * @param string $filtertext
     * @param int $showall
     * @param moodle_url $baseurl
     * @param \renderer_base $output
     * @return array
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
                   CASE WHEN inv.source = 'revoked' THEN 'item_revoked' ELSE 'item' END AS event_type,
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

                $urldelete = '';
                if ($log->inventory_id > 0 && property_exists($this, 'courseid')) {
                    $urldelete = new \moodle_url('/blocks/playerhud/manage.php', [
                        'id' => $this->courseid,
                        'instanceid' => $this->instanceid,
                        'action' => 'revoke_item',
                        'invid' => $log->inventory_id,
                        'r_userid' => $userid,
                        'sesskey' => sesskey(),
                    ]);
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
                    'url_revoke'    => $urldelete ? $urldelete->out(false) : '',
                    'has_revoke'    => ($log->inventory_id > 0),
                ];
            }
        }
        return ['logs' => $results, 'paging_bar' => $pagingbar, 'total' => $totalrecords];
    }

    /**
     * Get AI audit logs.
     *
     * @return array
     */
    private function get_ai_logs(): array {
        global $DB;

        $logs = $DB->get_records(
            'block_playerhud_ai_logs',
            ['blockinstanceid' => $this->instanceid],
            'timecreated DESC',
            '*',
            0,
            50
        );

        $results = [];
        if (!$logs) {
            return $results;
        }

        // Collect unique item names from item-type logs to bulk-load their icons.
        $itemnames = [];
        foreach ($logs as $log) {
            if ($log->action_type === 'item' && !empty($log->object_name)) {
                $itemnames[$log->object_name] = true;
            }
        }

        // Build a name → display data map without N+1 queries.
        $iconbyname = [];
        if (!empty($itemnames)) {
            [$namesql, $nameparams] = $DB->get_in_or_equal(array_keys($itemnames), SQL_PARAMS_NAMED, 'nm');
            $nameparams['blockinstanceid'] = $this->instanceid;
            $itemrows = $DB->get_records_select(
                'block_playerhud_items',
                "blockinstanceid = :blockinstanceid AND name $namesql",
                $nameparams,
                '',
                'id, name, image'
            );

            if (!empty($itemrows)) {
                $context = \context_block::instance($this->instanceid);
                $mediamap = \block_playerhud\utils::get_items_display_data($itemrows, $context);
                foreach ($itemrows as $item) {
                    if (isset($mediamap[$item->id])) {
                        $iconbyname[$item->name] = $mediamap[$item->id];
                    }
                }
            }
        }

        $counter = 0;
        foreach ($logs as $log) {
            $counter++;
            $logdate = userdate($log->timecreated, get_string('strftimedatetime', 'langconfig'));
            $aiclass = ($log->ai_provider === 'Gemini') ? 'bg-primary text-white' : 'bg-info text-white';

            $isimageicon = false;
            $iconurl = '';
            $iconemoji = '⚙️';

            if ($log->action_type === 'item' && isset($iconbyname[$log->object_name])) {
                $media = $iconbyname[$log->object_name];
                $isimageicon = $media['is_image'];
                $iconurl = $media['is_image'] ? $media['url'] : '';
                $iconemoji = $media['is_image'] ? '' : strip_tags($media['content']);
            } else if ($log->action_type === 'item') {
                $iconemoji = '🎁';
            } else if ($log->action_type === 'ai_suggestion') {
                $iconemoji = '💭';
            }

            $results[] = [
                'is_hidden'     => ($counter > 5),
                'date'          => $logdate,
                'action_badge'  => $log->action_type,
                'is_image_icon' => $isimageicon,
                'icon_url'      => $iconurl,
                'icon_emoji'    => $iconemoji,
                'object_name'   => format_string($log->object_name ?? ''),
                'ai_class'      => $aiclass,
                'ai_provider'   => $log->ai_provider,
            ];
        }
        return $results;
    }

    /**
     * Required by interface.
     *
     * @return string
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/tab_reports', $this->export_for_template($OUTPUT));
    }
}
