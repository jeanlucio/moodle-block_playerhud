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
 * History tab output renderer for students.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use templatable;

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
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template($output) {
        $logs = $this->get_audit_logs($this->player->userid);

        return [
            'has_logs' => !empty($logs),
            'logs'     => $logs,
            'str'      => [
                'col_date'    => get_string('report_col_date', 'block_playerhud'),
                'col_type'    => get_string('report_col_type', 'block_playerhud'),
                'col_desc'    => get_string('report_col_desc', 'block_playerhud'),
                'xp'          => get_string('xp', 'block_playerhud'),
                'col_details' => get_string('report_col_details', 'block_playerhud'),
                'empty'       => get_string('history_empty', 'block_playerhud'),
                'title'       => get_string('tab_history', 'block_playerhud'),
                'desc'        => get_string('history_desc', 'block_playerhud'),
            ],
        ];
    }

    /**
     * Get user audit logs (Reused optimized logic).
     *
     * @param int $userid
     * @return array
     */
    private function get_audit_logs(int $userid): array {
        global $DB;
        $concatitem = $DB->sql_concat("'item_'", "inv.id");
        $concattrade = $DB->sql_concat("'trade_'", "tl.id");

        $sql = "
        SELECT uniqueid, event_type, object_name, timecreated, details, icon, xp_gained, itemid
        FROM (
            SELECT $concatitem AS uniqueid, 'item' AS event_type, i.name AS object_name, inv.timecreated,
                   inv.source AS details, i.image AS icon,
                   CASE
                       WHEN inv.source = 'map' AND COALESCE(d.maxusage, 1) > 0 THEN i.xp
                       ELSE 0
                   END AS xp_gained,
                   i.id AS itemid
              FROM {block_playerhud_inventory} inv
              JOIN {block_playerhud_items} i ON inv.itemid = i.id
         LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
             WHERE inv.userid = :u1 AND i.blockinstanceid = :p1
            UNION ALL
            SELECT $concattrade AS uniqueid, 'trade' AS event_type, t.name AS object_name, tl.timecreated,
                   'trade_completed' AS details, '⚖️' AS icon, 0 AS xp_gained, 0 AS itemid
              FROM {block_playerhud_trade_log} tl
              JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
             WHERE tl.userid = :u2 AND t.blockinstanceid = :p2
        ) combined_log
        ORDER BY timecreated DESC LIMIT 100";

        $params = [
            'u1' => $userid,
            'p1' => $this->instanceid,
            'u2' => $userid,
            'p2' => $this->instanceid,
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
                ];
            }
        }
        return $results;
    }

    /**
     * Display method.
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/tab_history', $this->export_for_template($OUTPUT));
    }
}
