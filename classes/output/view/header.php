<?php
namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

class header implements renderable, templatable {

    protected $config;
    protected $player;
    protected $user;

    public function __construct($config, $player, $user) {
        $this->config = $config;
        $this->player = $player;
        $this->user = $user;
    }

    public function export_for_template(renderer_base $output) {
        // 1. Calculate Stats
        $stats = \block_playerhud\game::get_game_stats(
            $this->config, 
            $this->player->blockinstanceid, 
            $this->player->currentxp
        );

        // 2. Prepare XP Display
        $xp_total_game = isset($stats['total_game_xp']) ? $stats['total_game_xp'] : 0;
        $xp_display = $this->player->currentxp . ' / ' . $xp_total_game . ' XP';

        if ($this->player->currentxp >= $xp_total_game && $xp_total_game > 0) {
            $xp_display .= ' 🏆';
        }

        // 3. Return Data
        return [
            'userpicture' => $output->user_picture($this->user, ['size' => 100, 'class' => 'rounded-circle shadow-sm']),
            'fullname' => fullname($this->user),
            'level_display' => $stats['level'] . ' / ' . $stats['max_levels'],
            'xp_display' => $xp_display,
            'progress' => $stats['progress'],
            'level_class' => !empty($stats['level_class']) ? $stats['level_class'] : 'bg-primary'
        ];
    }
}
