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
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_config
 */
class tab_config implements renderable {

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
     * Render the tab.
     *
     * @return string
     */
    public function display() {
        global $DB, $OUTPUT;

        // 1. Strings Gerais
        $titlewidget = get_string('widget_code_title', 'block_playerhud');
        $descwidget = get_string('widget_code_desc', 'block_playerhud');
        $tipwidget = get_string('widget_code_tip', 'block_playerhud');
        $copy = get_string('gen_copy', 'block_playerhud');

        $titleapi = get_string('api_settings_title', 'block_playerhud');
        $descapi = get_string('api_settings_desc', 'block_playerhud');
        $strgemini = get_string('gemini_apikey', 'block_playerhud');
        $strgroq = get_string('groq_apikey', 'block_playerhud');
        $strsave = get_string('save_keys', 'block_playerhud');
        $strplaceholder = get_string('api_key_placeholder', 'block_playerhud');

        // 2. Load Block Config & Setup
        $bi = $DB->get_record('block_instances', ['id' => $this->instanceid], '*', MUST_EXIST);
        $config = unserialize(base64_decode($bi->configdata));
        
        if (!$config) {
            $config = new \stdClass();
        }

        // --- LÓGICA DE BALANCEAMENTO (HEALTH CHECK) ---
        
        // Valores configurados (com defaults de segurança)
        $xp_per_level = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $max_levels   = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $xp_ceiling   = $xp_per_level * $max_levels; // O "Zerar o jogo"

        // Calcular XP Total disponível no Jogo
        $total_items_xp = 0;
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid, 'enabled' => 1]);
        
        if ($items) {
            foreach ($items as $item) {
                // Se o item tem drops, verificamos os limites
                $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $item->id]);
                if ($drops) {
                    foreach ($drops as $drop) {
                        // MUDANÇA: Se for infinito (0), não soma na economia do jogo.
                        // Só contamos drops que acabam (finitos).
                        if ($drop->maxusage > 0) {
                            $total_items_xp += ($item->xp * $drop->maxusage);
                        }
                    }
                } else {
                    // Item sem drop no mapa (talvez recompensa de quest ou loja), conta 1 vez
                    $total_items_xp += $item->xp;
                }
            }
        }

        // Razão de Cobertura
        $ratio = ($xp_ceiling > 0) ? ($total_items_xp / $xp_ceiling) * 100 : 0;
        $ratio_display = number_format($ratio, 1);

        // Definição da Mensagem e Cor
        $alert_class = 'alert-info';
        $icon = 'fa-info-circle';
        $str_message = '';
        
        $a = new \stdClass();
        $a->total = $total_items_xp;
        $a->req = $xp_ceiling;
        $a->ratio = $ratio_display;

        if ($total_items_xp == 0) {
            $str_message = get_string('bal_msg_empty', 'block_playerhud');
            $alert_class = 'alert-secondary';
        } elseif ($ratio < 80) {
            $str_message = get_string('bal_msg_hard', 'block_playerhud', $a);
            $alert_class = 'alert-warning'; // Amarelo
            $icon = 'fa-exclamation-triangle';
        } elseif ($ratio > 100) {
            $str_message = get_string('bal_msg_easy', 'block_playerhud', $a);
            $alert_class = 'alert-danger'; // Vermelho
            $icon = 'fa-exclamation-triangle';
        } else {
            $str_message = get_string('bal_msg_perfect', 'block_playerhud', $a);
            $alert_class = 'alert-success'; // Verde
            $icon = 'fa-check-circle';
        }

        // --- INÍCIO DO RENDER ---

        // Configurações de API (Valores)
        $valgemini = isset($config->apikey_gemini) ? $config->apikey_gemini : '';
        $valgroq   = isset($config->apikey_groq) ? $config->apikey_groq : '';
        
        $actionurl = new moodle_url(
            '/blocks/playerhud/manage.php',
            [
                'id' => $this->courseid, 
                'instanceid' => $this->instanceid,
                'action' => 'save_keys', 
                'tab' => 'config'
            ]
        );

        $html = '<div class="row">';

        // 1. CARD DE BALANCEAMENTO (Novo)
        // Definimos a cor da borda com base no alerta
        $borderClass = (strpos($alert_class, 'success') !== false) ? 'border-success' : 
                      ((strpos($alert_class, 'warning') !== false) ? 'border-warning' : 'border-secondary');

        $html .= '
        <div class="col-12 mb-4">
            <div class="card shadow-sm ' . $borderClass . '">
                <div class="card-header bg-white fw-bold">
                    <i class="fa fa-balance-scale"></i> ' . get_string('game_balance', 'block_playerhud') . '
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center border-end">
                            <h3 class="m-0 text-primary">' . $total_items_xp . '</h3>
                            <small class="text-muted text-uppercase" style="font-size:0.7rem;">' . get_string('total_items_xp', 'block_playerhud') . '</small>
                        </div>
                        <div class="col-md-3 text-center border-end">
                            <h3 class="m-0 text-dark">' . $xp_ceiling . '</h3>
                            <small class="text-muted text-uppercase" style="font-size:0.7rem;">' . get_string('xp_required_max', 'block_playerhud') . '</small>
                        </div>
                        <div class="col-md-6">
                            <div class="alert ' . $alert_class . ' m-0 d-flex align-items-center">
                                <i class="fa ' . $icon . ' fa-2x me-3" aria-hidden="true"></i>
                                <div>' . $str_message . '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        // 2. CARD DO WIDGET
        $html .= '
        <div class="col-md-6">
            <div class="card border-primary mb-4 h-100 shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fa fa-code"></i> ' . $titlewidget . '
                </div>
                <div class="card-body">
                    <p>' . $descwidget . '</p>

                    <div class="input-group input-group-lg mb-3">
                        <input type="text" class="form-control font-monospace text-center"
                               value="[PLAYERHUD_WIDGET]" readonly id="widgetCode"
                               style="font-weight:bold; color:#0f6cbf; background:#f8f9fa;">
                        <button class="btn btn-dark copy-btn" data-target="widgetCode" type="button">
                            <i class="fa fa-copy"></i> ' . $copy . '
                        </button>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fa fa-lightbulb-o"></i> ' . $tipwidget . '
                    </div>
                </div>
            </div>
        </div>';

        // 3. CARD DAS APIs
        $html .= '
        <div class="col-md-6">
            <div class="card border-dark mb-4 h-100 shadow-sm">
                <div class="card-header bg-dark text-white fw-bold">
                    <i class="fa fa-key"></i> ' . $titleapi . '
                </div>
                <div class="card-body">
                    <p class="text-muted small">' . $descapi . '</p>

                    <form action="' . $actionurl->out() . '" method="post">
                        <input type="hidden" name="sesskey" value="' . sesskey() . '">

                        <div class="mb-3">
                            <label class="fw-bold form-label" for="gemini_key">' . $strgemini . '</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-google"></i></span>
                                <input type="password" class="form-control" name="gemini_key" id="gemini_key"
                                       value="' . s($valgemini) . '" placeholder="' . $strplaceholder . '">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold form-label" for="groq_key">' . $strgroq . '</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-bolt"></i></span>
                                <input type="password" class="form-control" name="groq_key" id="groq_key"
                                       value="' . s($valgroq) . '" placeholder="' . $strplaceholder . '">
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success w-100 shadow-sm">
                            <i class="fa fa-save"></i> ' . $strsave . '
                        </button>
                    </form>
                </div>
            </div>
        </div>';

        $html .= '</div>'; // End row.
        return $html;
    }
}
