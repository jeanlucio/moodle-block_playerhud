<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Reports tab management.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

    /** @var string Sort column. */
    protected $sort;

    /** @var string Sort direction. */
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
        $this->dir  = empty($dir) ? 'DESC' : $dir;
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
        $config = unserialize(base64_decode($bi->configdata));
        $xpperlevel = $config->xp_per_level ?? 100;
        $maxlevels = $config->max_levels ?? 20;

        $contextdata = [
            'is_audit'      => ($this->selecteduserid > 0),
            'kpis'          => $this->get_kpi_data(),
            'charts'        => $this->get_charts_data($xpperlevel, $maxlevels),
            'user_selector' => $this->get_user_selector_data(),
        ];

        if ($this->selecteduserid > 0) {
            $contextdata['url_back']       = $baseurl->out(false);
            $contextdata['str_back']       = get_string('back', 'block_playerhud');
            $contextdata['audit_logs']     = $this->get_audit_logs($this->selecteduserid);
            $contextdata['has_audit_logs'] = !empty($contextdata['audit_logs']);

            // Data for granting items to user.
            $contextdata['r_userid'] = $this->selecteduserid;
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
        } else {
            $contextdata['headers'] = [
                'student' => $this->get_sort_data('student', get_string('student', 'block_playerhud'), $baseurl),
                'xp'      => $this->get_sort_data('xp', get_string('report_status_level', 'block_playerhud'), $baseurl),
                'items'   => $this->get_sort_data('items', get_string('items', 'block_playerhud'), $baseurl),
            ];
            $contextdata['students']     = $this->get_students_data($xpperlevel);
            $contextdata['has_students'] = !empty($contextdata['students']);
        }

        $contextdata['ai_logs']     = $this->get_ai_logs();
        $contextdata['has_ai_logs'] = !empty($contextdata['ai_logs']);

        $contextdata['str'] = [
            'leaderboard' => get_string('leaderboard_title', 'block_playerhud'),
            'level'       => get_string('level', 'block_playerhud'),
            'xp'          => get_string('xp', 'block_playerhud'),
            'action'      => get_string('report_action', 'block_playerhud'),
            'audit'       => get_string('report_audit', 'block_playerhud'),
            'no_logs'     => get_string('report_no_logs', 'block_playerhud'),
            'ai_title'    => get_string('report_ai_title', 'block_playerhud'),
            'ai_sub'      => get_string('report_ai_subtitle', 'block_playerhud'),
            'col_date'    => get_string('report_col_date', 'block_playerhud'),
            'col_type'    => get_string('report_col_type', 'block_playerhud'),
            'col_desc'    => get_string('report_col_desc', 'block_playerhud'),
            'col_details' => get_string('report_col_details', 'block_playerhud'),
            'col_object'  => get_string('report_col_object', 'block_playerhud'),
            'col_ai'      => get_string('report_col_ai', 'block_playerhud'),
            'revoke_item'    => get_string('revoke_item', 'block_playerhud'),
            'confirm_revoke' => get_string('confirm_revoke', 'block_playerhud'),
            'btn_more'    => get_string('report_show_more', 'block_playerhud'),
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
     * Helper for sort links.
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
                'value'    => number_format($totalxp ?: 0, 0, ',', '.'),
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

        // Levels Stats.
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
            'str_levels'  => get_string('level', 'block_playerhud'),
            'str_no_logs' => get_string('report_no_logs', 'block_playerhud'),
            'has_levels'  => !empty($levelsdata),
            'levels'      => $levelsdata,
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
     * @return array
     */
    private function get_students_data(int $xpperlevel): array {
        global $DB;
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
                   pu.currentxp, pu.enable_gamification,
                   (SELECT COUNT(inv.id) FROM {block_playerhud_inventory} inv
                    JOIN {block_playerhud_items} it ON inv.itemid = it.id
                    WHERE inv.userid = u.id AND it.blockinstanceid = :p1) as total_items
              FROM {user} u
              JOIN {block_playerhud_user} pu ON pu.userid = u.id
             WHERE pu.blockinstanceid = :p2
          ORDER BY $sortsql";

        $params = [
            'p1' => $this->instanceid,
            'p2' => $this->instanceid,
        ];
        $users = $DB->get_records_sql($sql, $params);

        $results = [];
        if ($users) {
            foreach ($users as $row) {
                $isactive = ($row->enable_gamification == 1);
                $level = floor($row->currentxp / $xpperlevel) + 1;

                $urlaudit = new moodle_url('/blocks/playerhud/manage.php', [
                    'id'         => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'tab'        => 'reports',
                    'r_userid'   => $row->id,
                ]);

                $results[] = [
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
     * Get user audit logs.
     *
     * @param int $userid
     * @return array
     */
    private function get_audit_logs(int $userid): array {
        global $DB;
        $concatitem = $DB->sql_concat("'item_'", "inv.id");
        $concattrade = $DB->sql_concat("'trade_'", "tl.id");

        // Combining item acquisition logs and trade logs into a single query with a union, ordered by time.
        $sql = "
        SELECT uniqueid, event_type, object_name, timecreated, details, icon, xp_gained, itemid, inventory_id
        FROM (
            SELECT $concatitem AS uniqueid, 'item' AS event_type, i.name AS object_name, inv.timecreated,
                   inv.source AS details, i.image AS icon,
                   CASE
                       WHEN inv.source = 'map' AND COALESCE(d.maxusage, 1) > 0 THEN i.xp
                       ELSE 0
                   END AS xp_gained,
                   i.id AS itemid, inv.id AS inventory_id
              FROM {block_playerhud_inventory} inv
              JOIN {block_playerhud_items} i ON inv.itemid = i.id
         LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
             WHERE inv.userid = :u1 AND i.blockinstanceid = :p1
            UNION ALL
            SELECT $concattrade AS uniqueid, 'trade' AS event_type, t.name AS object_name, tl.timecreated,
                   'trade_completed' AS details, '⚖️' AS icon, 0 AS xp_gained, 0 AS itemid, 0 AS inventory_id
              FROM {block_playerhud_trade_log} tl
              JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
             WHERE tl.userid = :u2 AND t.blockinstanceid = :p2
        ) combined_log
        ORDER BY timecreated DESC LIMIT 100";

        $params = [
            'u1' => $userid, 'p1' => $this->instanceid,
            'u2' => $userid, 'p2' => $this->instanceid,
        ];
        $logs = $DB->get_records_sql($sql, $params);

        $results = [];
        if ($logs) {
            $fakeitems = [];
            foreach ($logs as $log) {
                if ($log->itemid > 0) {
                    $fakeitems[$log->itemid] = (object)['id' => $log->itemid, 'image' => $log->icon];
                }
            }
            $context = \context_block::instance($this->instanceid);
            $allmedia = \block_playerhud\utils::get_items_display_data($fakeitems, $context);

            foreach ($logs as $log) {
                $srckey = 'report_src_' . $log->details;
                $detailtext = get_string_manager()->string_exists($srckey, 'block_playerhud') ?
                    get_string($srckey, 'block_playerhud') : $log->details;

                $badgeclass = 'bg-secondary';
                $badgetext  = get_string('report_type_other', 'block_playerhud');
                $isimageicon = false;
                $iconurl = '';
                $iconemoji = $log->icon;

                if ($log->event_type === 'item') {
                    $badgeclass = 'bg-primary';
                    $badgetext  = get_string('report_type_item', 'block_playerhud');

                    if (isset($allmedia[$log->itemid])) {
                        $media = $allmedia[$log->itemid];
                        $isimageicon = $media['is_image'];
                        $iconurl = $media['is_image'] ? $media['url'] : '';
                        $iconemoji = $media['is_image'] ? '' : strip_tags($media['content']);
                    }
                } else if ($log->event_type === 'trade') {
                    $badgeclass = 'bg-info text-dark';
                    $badgetext  = get_string('report_type_trade', 'block_playerhud');
                    $detailtext = get_string('report_status_transaction', 'block_playerhud');
                }

                $xpbadge = '';
                if ($log->xp_gained > 0) {
                    $xpbadge = '<span class="badge bg-success text-white ph-text-xs">+' .
                        $log->xp_gained . ' XP</span>';
                } else {
                    $xpbadge = '<span class="text-muted small">-</span>';
                }

                $urldelete = '';
                if ($log->inventory_id > 0) {
                    $urldelete = new moodle_url('/blocks/playerhud/manage.php', [
                        'id' => $this->courseid,
                        'instanceid' => $this->instanceid,
                        'action' => 'revoke_item',
                        'invid' => $log->inventory_id,
                        'r_userid' => $userid,
                        'sesskey' => sesskey(),
                    ]);
                }

                $results[] = [
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
        return $results;
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
        if ($logs) {
            $counter = 0;
            $typeicons = [
                'item'          => '🎁',
                'ai_suggestion' => '💭',
            ];

            foreach ($logs as $log) {
                $counter++;
                $logdate = userdate($log->timecreated, get_string('strftimedatetime', 'langconfig'));
                $aiclass = ($log->ai_provider === 'Gemini') ? 'bg-primary' : 'bg-info text-dark';

                $results[] = [
                    'is_hidden'    => ($counter > 5),
                    'date'         => $logdate,
                    'action_badge' => $log->action_type,
                    'type_icon'    => $typeicons[$log->action_type] ?? '⚙️',
                    'object_name'  => format_string($log->object_name ?? ''),
                    'ai_class'     => $aiclass,
                    'ai_provider'  => $log->ai_provider,
                ];
            }
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
