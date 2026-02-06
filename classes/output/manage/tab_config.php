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

defined('MOODLE_INTERNAL') || die();

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
     * * @param mixed $output The renderer instance.
     */
    public function export_for_template($output) {
        global $DB;

        // 1. Carregar Configurações
        $bi = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
        $config = unserialize(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        // 2. Lógica de Balanceamento (Health Check)
        $xp_per_level = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $max_levels   = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $xp_ceiling   = $xp_per_level * $max_levels;

        // Calcular XP Total do Jogo
        $total_items_xp = 0;
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid, 'enabled' => 1]);
        
        if ($items) {
            foreach ($items as $item) {
                $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $item->id]);
                if ($drops) {
                    foreach ($drops as $drop) {
                        // Drops infinitos (0) não somam na economia
                        if ($drop->maxusage > 0) {
                            $total_items_xp += ($item->xp * $drop->maxusage);
                        }
                    }
                } else {
                    $total_items_xp += $item->xp;
                }
            }
        }

        // Razão de Cobertura
        $ratio = ($xp_ceiling > 0) ? ($total_items_xp / $xp_ceiling) * 100 : 0;
        
        // Definição Visual
        $alert_class = 'alert-info';
        $border_class = 'border-info';
        $icon = 'fa-info-circle';
        $str_message = '';
        
        $a = new \stdClass();
        $a->total = $total_items_xp;
        $a->req = $xp_ceiling;
        $a->ratio = number_format($ratio, 1);

        if ($total_items_xp == 0) {
            $str_message = get_string('bal_msg_empty', 'block_playerhud');
            $alert_class = 'alert-secondary';
            $border_class = 'border-secondary';
        } elseif ($ratio < 80) {
            $str_message = get_string('bal_msg_hard', 'block_playerhud', $a);
            $alert_class = 'alert-warning';
            $border_class = 'border-warning';
            $icon = 'fa-exclamation-triangle';
        } elseif ($ratio > 100) {
            $str_message = get_string('bal_msg_easy', 'block_playerhud', $a);
            $alert_class = 'alert-danger';
            $border_class = 'border-danger';
            $icon = 'fa-exclamation-triangle';
        } else {
            $str_message = get_string('bal_msg_perfect', 'block_playerhud', $a);
            $alert_class = 'alert-success';
            $border_class = 'border-success';
            $icon = 'fa-check-circle';
        }

        $actionurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id' => $this->courseid, 
            'instanceid' => $this->instanceid,
            'action' => 'save_keys', 
            'tab' => 'config'
        ]);

        return [
            'total_items_xp' => $total_items_xp,
            'xp_ceiling' => $xp_ceiling,
            'alert_class' => $alert_class,
            'alert_border_class' => $border_class,
            'alert_icon' => $icon,
            'balance_message' => $str_message,
            'action_url' => $actionurl->out(false),
            'sesskey' => sesskey(),
            'val_gemini' => $config->apikey_gemini ?? '',
            'val_groq' => $config->apikey_groq ?? ''
        ];
    }
}
