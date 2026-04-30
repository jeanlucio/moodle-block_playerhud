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
 * PlayerHUD Block main class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_playerhud extends block_base {
    /**
     * Initialize block title and properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_playerhud');
    }

    /**
     * Get block content for display.
     *
     * @return stdClass|string
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $USER, $COURSE, $OUTPUT, $DB;

        $this->content = new \stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        try {
            $context = \context_block::instance($this->instance->id);

            // 1. Load Player.
            $player = \block_playerhud\game::get_player($this->instance->id, $USER->id);
            $isteacher = has_capability('block/playerhud:manage', $context);

            // Check if student opted out.
            if (!$isteacher && empty($player->enable_gamification)) {
                $urlreactivate = new \moodle_url('/blocks/playerhud/view.php', [
                    'id' => $COURSE->id,
                    'instanceid' => $this->instance->id,
                    'action' => 'toggle_hud',
                    'state' => 1,
                    'sesskey' => sesskey(),
                ]);

                $data = [
                    'str_paused' => get_string('status_paused', 'block_playerhud'),
                    'str_reactivate' => get_string('optin_yes', 'block_playerhud'),
                    'url_reactivate' => $urlreactivate->out(false),
                ];

                // Render pause template and exit.
                $this->content->text = $OUTPUT->render_from_template('block_playerhud/sidebar_rejoin', $data);
                return $this->content;
            }

            // Continue normal rendering.
            $config = unserialize_object(base64_decode($this->instance->configdata));
            if (!$config) {
                $config = new \stdClass();
            }

            // Default settings.
            $config->enable_ranking = isset($config->enable_ranking) ? $config->enable_ranking : 1;
            $config->enable_rpg     = isset($config->enable_rpg) ? (int) $config->enable_rpg : 1;
            $config->enable_items   = isset($config->enable_items) ? (int) $config->enable_items : 1;
            $config->enable_quests  = isset($config->enable_quests) ? (int) $config->enable_quests : 1;

            $stats = \block_playerhud\game::get_game_stats($config, $this->instance->id, $player->currentxp);

            // Recent Items Logic (Stash) — only when items feature is enabled.
            $recentitems = [];
            if (!empty($config->enable_items)) {
                $rawinventory = \block_playerhud\game::get_inventory($USER->id, $this->instance->id);
                $limit = 6;
                $count = 0;
                $seenitems = [];
                $itemstodisplay = [];

                foreach ($rawinventory as $invitem) {
                    if ($count >= $limit) {
                        break;
                    }
                    if (in_array($invitem->id, $seenitems)) {
                        continue;
                    }
                    $seenitems[] = $invitem->id;
                    $itemstodisplay[$invitem->id] = $invitem;
                    $count++;
                }

                $allmedia = \block_playerhud\utils::get_items_display_data($itemstodisplay, $context);

                foreach ($itemstodisplay as $invitem) {
                    $media = $allmedia[$invitem->id];
                    $recentitems[] = [
                        'name' => format_string($invitem->name),
                        'xp' => $invitem->xp . ' XP',
                        'image' => $media['is_image'] ? $media['url'] : strip_tags($media['content']),
                        'isimage' => $media['is_image'],
                        'description' => !empty($invitem->description) ? format_text($invitem->description, FORMAT_HTML) : '',
                        'date' => userdate($invitem->collecteddate, get_string('strftimedatefullshort', 'langconfig')),
                        'timestamp' => $invitem->collecteddate,
                    ];
                }
            }

            $manageurl = '';
            if ($isteacher) {
                $url = new \moodle_url('/blocks/playerhud/manage.php', ['id' => $COURSE->id, 'instanceid' => $this->instance->id]);
                $manageurl = $url->out(false);
            }

            $xptotalgame = isset($stats['total_game_xp']) ? $stats['total_game_xp'] : 0;
            $xpdisplay = $player->currentxp . ' / ' . $xptotalgame . ' XP';
            if ($player->currentxp >= $xptotalgame && $xptotalgame > 0) {
                $xpdisplay .= ' 🏆';
            }

            // Ranking Data.
            $rankdata = null;
            if (!empty($config->enable_ranking)) {
                $urlranking = new \moodle_url('/blocks/playerhud/view.php', [
                    'id' => $COURSE->id,
                    'instanceid' => $this->instance->id,
                    'tab' => 'ranking',
                ]);

                // Ranking Logic.
                if (!$isteacher && $player->ranking_visibility == 1 && $player->enable_gamification == 1) {
                    $rank = \block_playerhud\game::get_user_rank($this->instance->id, $USER->id, $player->currentxp);
                    $rankdisplay = $rank;
                    $ranktooltip = "#{$rank} - " . get_string('view_ranking', 'block_playerhud');
                } else if ($isteacher) {
                    $rankdisplay = '-';
                    $ranktooltip = get_string('view_ranking', 'block_playerhud');
                } else {
                    $rankdisplay = '-';
                    $ranktooltip = get_string('enable_ranking', 'block_playerhud');
                }

                $rankdata = [
                    'rank' => $rankdisplay,
                    'url' => $urlranking->out(false),
                    'tooltip' => $ranktooltip,
                    'label' => get_string('view_ranking', 'block_playerhud'),
                ];
            }

            // Grid Links.
            $urlbase = new \moodle_url('/blocks/playerhud/view.php', ['id' => $COURSE->id, 'instanceid' => $this->instance->id]);

            // RPG character identity data (portrait, tier, karma) — only when RPG mode is on.
            $classdata = null;
            $karmadata = null;
            $urlclassselect = null;
            if (!empty($config->enable_rpg)) {
                $rpgprogress = \block_playerhud\game::get_player_class($this->instance->id, $USER->id);
                if ($rpgprogress && (int) $rpgprogress->classid > 0) {
                    $class = $DB->get_record('block_playerhud_classes', ['id' => $rpgprogress->classid]);
                    if ($class) {
                        $portraittier = \block_playerhud\utils::get_class_portrait_tier(
                            $this->instance->id,
                            $USER->id
                        );
                        $portraiturl = \block_playerhud\utils::get_class_evolution_image_by_tier(
                            $class,
                            $portraittier,
                            $context
                        );
                        $tierstars = [];
                        for ($i = 1; $i <= 5; $i++) {
                            $tierstars[] = ['filled' => ($i <= $portraittier)];
                        }
                        $classdesc = format_text($class->description ?? '', FORMAT_HTML, ['context' => $context]);
                        $classdata = [
                            'classname'       => format_string($class->name),
                            'fullname'        => format_string($class->name),
                            'portrait_url'    => $portraiturl,
                            'tier'            => $portraittier,
                            'tier_name'       => get_string('class_tier_' . $portraittier, 'block_playerhud'),
                            'tier_stars'      => $tierstars,
                            'description'     => $classdesc,
                            'has_description' => !empty(trim(strip_tags($classdesc))),
                        ];
                    }
                }

                $karma = \block_playerhud\game::get_player_karma($this->instance->id, $USER->id);
                $karmapercent = max(0, min(100, (int) round(($karma + 999) / 1998 * 100)));
                if ($karma < 0) {
                    $karmabarclass = 'ph-karma-fill--evil';
                    $karmaiconclass = 'ph-karma--evil';
                } else if ($karma > 0) {
                    $karmabarclass = 'ph-karma-fill--good';
                    $karmaiconclass = 'ph-karma--good';
                } else {
                    $karmabarclass = 'ph-karma-fill--neutral';
                    $karmaiconclass = 'ph-karma--neutral';
                }
                $karmavaluedisplay = ($karma === 0)
                    ? get_string('karma_neutral', 'block_playerhud')
                    : (($karma > 0 ? '+' : '') . $karma);
                $karmadata = [
                    'value'         => $karma,
                    'value_display' => $karmavaluedisplay,
                    'percent'       => $karmapercent,
                    'bar_class'     => $karmabarclass,
                    'icon_class'    => $karmaiconclass,
                    'label'         => get_string('karma', 'block_playerhud'),
                ];

                $urlclassselect = (new \moodle_url('/blocks/playerhud/view.php', [
                    'id'         => $COURSE->id,
                    'instanceid' => $this->instance->id,
                    'tab'        => 'class_select',
                ]))->out(false);
            }

            // Quest notification dot: show when a reward is waiting to be claimed.
            $hasclaimable = !empty($config->enable_quests) && \block_playerhud\quest::has_claimable_quests(
                $this->instance->id,
                $USER->id,
                $COURSE->id,
                $player->currentxp,
                $stats['level']
            );

            // Story notification dot: show when there is an available unread chapter.
            $hasunreadchapters = !$isteacher && \block_playerhud\story_manager::has_unread_chapters(
                $this->instance->id,
                $USER->id,
                $stats['level']
            );

            // PlayerGroup info (soft dependency — only when mod_playergroup is installed).
            $groupinfo = null;
            if (class_exists('\mod_playergroup\api\group_info')) {
                $groupinfo = \mod_playergroup\api\group_info::get_player_group_in_course(
                    (int) $COURSE->id,
                    (int) $USER->id
                );
            }

            // Final Data.
            $renderdata = [
                'username'         => fullname($USER),
                // Change the size of the Block's profile picture.
                'userpicture'      => $OUTPUT->user_picture($USER, ['size' => 120]),
                'enable_rpg'       => !empty($config->enable_rpg),
                'enable_items'     => !empty($config->enable_items),
                'enable_quests'    => !empty($config->enable_quests),
                'classdata'        => $classdata,
                'karma_data'       => $karmadata,
                'url_class_select' => $urlclassselect,
                'xp'          => $xpdisplay,
                'level'       => $stats['level'] . '/' . $stats['max_levels'],
                'level_class' => $stats['level_class'],
                'progress'    => $stats['progress'],
                'viewurl'     => $urlbase->out(false),
                'url_shop'    => (new \moodle_url($urlbase, ['tab' => 'shop']))->out(false),
                'url_history' => (new \moodle_url($urlbase, ['tab' => 'history']))->out(false),
                'url_quests'  => (new \moodle_url($urlbase, ['tab' => 'quests']))->out(false),
                'url_story'   => (new \moodle_url($urlbase, ['tab' => 'chapters']))->out(false),
                'has_unread_chapters' => $hasunreadchapters,
                'isteacher'   => $isteacher,
                'manageurl'   => $manageurl,
                'has_claimable_quests' => $hasclaimable,
                'has_items'   => !empty($recentitems),
                'items'       => $recentitems,
                'ranking'     => $rankdata,
                'hasgroup'     => $groupinfo !== null,
                'groupbadge'   => $groupinfo ? $groupinfo->badge : '',
                'groupname'    => $groupinfo ? format_string($groupinfo->groupname) : '',
                'groupmembers' => $groupinfo
                    ? $groupinfo->membercount . '/' . $groupinfo->maxmembers
                    : '',
                'url_disable' => (new \moodle_url('/blocks/playerhud/view.php', [
                    'id' => $COURSE->id,
                    'instanceid' => $this->instance->id,
                    'action' => 'toggle_hud',
                    'state' => 0,
                    'sesskey' => sesskey(),
                    'returnurl' => $this->page->url->out_as_local_url(false),
                ]))->out(false),
                'str_disable_gamification' => get_string('disable_exit', 'block_playerhud'),
                'str_confirm_msg' => get_string('confirm_disable', 'block_playerhud'),
            ];

            $this->content->text = $OUTPUT->render_from_template('block_playerhud/sidebar_view', $renderdata);

            // Initialize JS.
            $jsvars = [
                'strings' => [
                    'confirm_title' => get_string('confirmation', 'admin'),
                    'yes' => get_string('yes'),
                    'cancel' => get_string('cancel'),
                    'no_desc' => get_string('no_description', 'block_playerhud'),
                    'last_collected' => get_string('last_collected', 'block_playerhud'),
                ],
            ];
            $this->page->requires->js_call_amd('block_playerhud/view', 'init', [$jsvars]);

            $this->content->text .= $OUTPUT->render_from_template('block_playerhud/modal_item', []);
        } catch (\Throwable $e) {
            debugging($e->getMessage(), DEBUG_NORMAL);
        }

        return $this->content;
    }

    /**
     * Allow multiple instances of the block in the same course.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Define where this block can be added.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'my' => true,
        ];
    }

    /**
     * Enable block configuration.
     */
    public function has_config() {
        return true;
    }
}
