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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;

/**
 * Header view for PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class header implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var \stdClass User object. */
    protected $user;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player object.
     * @param \stdClass $user User object.
     */
    public function __construct($config, $player, $user) {
        $this->config = $config;
        $this->player = $player;
        $this->user = $user;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template(renderer_base $output) {
        // 1. Calculate Stats.
        $stats = \block_playerhud\game::get_game_stats(
            $this->config,
            $this->player->blockinstanceid,
            $this->player->currentxp
        );

        // 2. Prepare XP Display.
        $xptotalgame = isset($stats['total_game_xp']) ? $stats['total_game_xp'] : 0;
        $xpdisplay = $this->player->currentxp . ' / ' . $xptotalgame . ' XP';

        if ($this->player->currentxp >= $xptotalgame && $xptotalgame > 0) {
            $xpdisplay .= ' 🏆';
        }

        // 3. Return Data.
        return [
            'userpicture' => $output->user_picture($this->user, ['size' => 100, 'class' => 'rounded-circle shadow-sm']),
            'fullname' => fullname($this->user),
            'level_display' => $stats['level'] . ' / ' . $stats['max_levels'],
            'xp_display' => $xpdisplay,
            'progress' => $stats['progress'],
            'level_class' => !empty($stats['level_class']) ? $stats['level_class'] : 'bg-primary',
        ];
    }
}
