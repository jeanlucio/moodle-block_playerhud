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
 * Header renderable for student view.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use moodle_url;
use html_writer;

defined('MOODLE_INTERNAL') || die();

class header implements renderable {

    protected $config;
    protected $player;
    protected $user;

    public function __construct($config, $player, $user) {
        $this->config = $config;
        $this->player = $player;
        $this->user = $user;
    }

    public function display() {
        global $OUTPUT, $PAGE;

        // 1. Calculate Stats
        // Usamos a função estática que já existe no game.php
        $stats = \block_playerhud\game::get_game_stats(
            $this->config, 
            $this->player->blockinstanceid, 
            $this->player->currentxp
        );

        // 2. Prepare Data
        $userpic = $OUTPUT->user_picture($this->user, ['size' => 100, 'class' => 'rounded-circle shadow-sm']);
        $fullname = fullname($this->user);
        
        $levelLabel = get_string('level', 'block_playerhud') . ' ' . $stats['level'] . ' / ' . $stats['max_levels'];
        $nextXpLabel = get_string('next', 'block_playerhud') . ': ' . $stats['xp_next'] . ' XP'; 
        
        // Se estiver no nível máximo
        if ($stats['is_max']) {
            $nextXpLabel = 'MAX LEVEL';
        }

        $currentXpLabel = get_string('currentxp', 'block_playerhud') . ': ' . $this->player->currentxp;

        // 3. Render HTML
        // Vamos usar html_writer para manter limpo, ou string direta para facilitar o layout customizado
        
        $html = '
        <div class="card shadow-sm mb-4 border-0" style="border-radius: 15px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-4">
                        ' . $userpic . '
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <h2 class="m-0 fw-bold text-dark me-3">' . $fullname . '</h2>
                            <span class="badge bg-light text-dark border shadow-sm px-3 py-2" style="font-size: 0.9rem;">
                                ' . $levelLabel . '
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between small text-muted mb-1 mt-3">
                            <span>' . $currentXpLabel . '</span>
                            <span>' . $nextXpLabel . '</span>
                        </div>

                        <div class="progress" style="height: 12px; border-radius: 6px; background-color: #e9ecef;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: ' . $stats['progress'] . '%; border-radius: 6px;" 
                                 aria-valuenow="' . $stats['progress'] . '" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <div class="ms-4 text-end">
                         ' . $this->get_admin_button() . '
                    </div>
                </div>
            </div>
        </div>';

        return $html;
    }

    /**
     * Helper to show admin button only if needed inside the header area.
     * (Currently unused as button is outside, but kept for future layout flexibility)
     */
    private function get_admin_button() {
        return ''; 
    }
}
