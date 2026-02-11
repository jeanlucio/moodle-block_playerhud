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
 * Config tab management for Block PlayerHUD.
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
 * Config tab management for Block PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_config implements renderable, templatable {
    /** @var int Block Instance ID */
    protected $instanceid;

    /** @var int Course ID */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param int $instanceid
     * @param int $courseid
     */
    public function __construct($instanceid, $courseid) {
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param \renderer_base $output The renderer instance.
     * @return array Data for template.
     */
    public function export_for_template($output) {
        global $DB;

        // 1. Load Configuration.
        $bi = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
        $config = unserialize(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        // 2. Balance Logic (Health Check).
        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels   = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $xpceiling   = $xpperlevel * $maxlevels;

        // Calculate Total Game XP.
        $totalitemsxp = 0;
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid, 'enabled' => 1]);

        if ($items) {
            foreach ($items as $item) {
                $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $item->id]);
                if ($drops) {
                    foreach ($drops as $drop) {
                        // Infinite drops (0) do not count towards the economy.
                        if ($drop->maxusage > 0) {
                            $totalitemsxp += ($item->xp * $drop->maxusage);
                        }
                    }
                } else {
                    $totalitemsxp += $item->xp;
                }
            }
        }

        // Coverage Ratio.
        $ratio = ($xpceiling > 0) ? ($totalitemsxp / $xpceiling) * 100 : 0;

        // Visual Definition.
        $alertclass = 'alert-info';
        $borderclass = 'border-info';
        $icon = 'fa-info-circle';
        $strmessage = '';

        $a = new \stdClass();
        $a->total = $totalitemsxp;
        $a->req = $xpceiling;
        $a->ratio = number_format($ratio, 1);

        if ($totalitemsxp == 0) {
            $strmessage = get_string('bal_msg_empty', 'block_playerhud');
            $alertclass = 'alert-secondary';
            $borderclass = 'border-secondary';
        } else if ($ratio < 80) {
            $strmessage = get_string('bal_msg_hard', 'block_playerhud', $a);
            $alertclass = 'alert-warning';
            $borderclass = 'border-warning';
            $icon = 'fa-exclamation-triangle';
        } else if ($ratio > 100) {
            $strmessage = get_string('bal_msg_easy', 'block_playerhud', $a);
            $alertclass = 'alert-danger';
            $borderclass = 'border-danger';
            $icon = 'fa-exclamation-triangle';
        } else {
            $strmessage = get_string('bal_msg_perfect', 'block_playerhud', $a);
            $alertclass = 'alert-success';
            $borderclass = 'border-success';
            $icon = 'fa-check-circle';
        }

        $actionurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'action' => 'save_keys',
            'tab' => 'config',
        ]);

        return [
            'total_items_xp' => $totalitemsxp,
            'xp_ceiling' => $xpceiling,
            'alert_class' => $alertclass,
            'alert_border_class' => $borderclass,
            'alert_icon' => $icon,
            'balance_message' => $strmessage,
            'widget_code_tip_html' => get_string('widget_code_tip', 'block_playerhud'),
            'action_url' => $actionurl->out(false),
            'sesskey' => sesskey(),
            'val_gemini' => $config->apikey_gemini ?? '',
            'val_groq' => $config->apikey_groq ?? '',
        ];
    }
}
