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
 * Config tab management for Block PlayerHUD.
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
 * Config tab management for Block PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            // Preload all drops for this instance to avoid N+1 query problem.
            $sql = "SELECT d.id, d.itemid, d.maxusage
                      FROM {block_playerhud_drops} d
                      JOIN {block_playerhud_items} i ON d.itemid = i.id
                     WHERE i.blockinstanceid = :instanceid AND i.enabled = 1";
            $alldrops = $DB->get_records_sql($sql, ['instanceid' => $this->instanceid]);

            // Group drops by itemid in memory.
            $dropsbyitem = [];
            foreach ($alldrops as $drop) {
                $dropsbyitem[$drop->itemid][] = $drop;
            }

            foreach ($items as $item) {
                if (!empty($dropsbyitem[$item->id])) {
                    foreach ($dropsbyitem[$item->id] as $drop) {
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

        // Add XP from enabled quest rewards.
        $questxp = $DB->get_field_sql(
            "SELECT COALESCE(SUM(reward_xp), 0) FROM {block_playerhud_quests}
              WHERE blockinstanceid = :instanceid AND enabled = 1",
            ['instanceid' => $this->instanceid]
        );
        $totalitemsxp += (int)$questxp;

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

        // Default URL and model come from admin config; used to pre-fill teacher fields.
        $defaulturl = get_config('block_playerhud', 'openai_baseurl')
            ?: 'https://api.openai.com/v1/chat/completions';
        $defaultmodel = get_config('block_playerhud', 'openai_model') ?: 'gpt-4o-mini';

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
            'val_gemini' => get_user_preferences('block_playerhud_gemini_key', ''),
            'val_groq' => get_user_preferences('block_playerhud_groq_key', ''),
            'val_openai' => get_user_preferences('block_playerhud_openai_key', ''),
            'val_openai_url' => get_user_preferences('block_playerhud_openai_url', ''),
            'val_openai_model' => get_user_preferences('block_playerhud_openai_model', ''),
            'placeholder_openai_url' => $defaulturl,
            'placeholder_openai_model' => $defaultmodel,
        ];
    }
}
