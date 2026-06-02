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

            if (!has_capability('block/playerhud:view', $context)) {
                return $this->content;
            }

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

            $rawconfig = base64_decode($this->instance->configdata ?? '', true);
            $config = ($rawconfig !== false && $rawconfig !== '') ? unserialize_object($rawconfig) : new \stdClass();
            if (!is_object($config)) {
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
            $stashhasmore = false;
            $stashmorebadge = '';
            $stashoverflowjson = '';
            if (!empty($config->enable_items)) {
                $rawinventory = \block_playerhud\game::get_inventory($USER->id, $this->instance->id);
                $stashlimit = 5;
                $overflowlimit = 20;
                $seenitems = [];
                $itemstodisplay = [];
                $overflowdisplay = [];

                foreach ($rawinventory as $invitem) {
                    if (in_array($invitem->id, $seenitems)) {
                        continue;
                    }
                    $seenitems[] = $invitem->id;
                    if (count($itemstodisplay) < $stashlimit) {
                        $itemstodisplay[$invitem->id] = $invitem;
                    } else if (count($overflowdisplay) < $overflowlimit) {
                        $overflowdisplay[$invitem->id] = $invitem;
                    }
                }

                $totalunique = count($seenitems);
                $morecount = max(0, $totalunique - $stashlimit);
                $stashhasmore = $morecount > 0;
                $stashmorebadge = $stashhasmore ? '+' . $morecount : '';

                // Single bulk call covers both stash and overflow items.
                $allmedia = \block_playerhud\utils::get_items_display_data(
                    $itemstodisplay + $overflowdisplay,
                    $context
                );

                foreach ($itemstodisplay as $invitem) {
                    $media = $allmedia[$invitem->id];
                    $recentitems[] = [
                        'name' => format_string($invitem->name),
                        'xp' => $invitem->xp . ' XP',
                        'image' => $media['is_image'] ? $media['url'] : strip_tags($media['content']),
                        'isimage' => $media['is_image'],
                        'description' => !empty($invitem->description)
                            ? format_text($invitem->description, FORMAT_HTML, ['context' => $context])
                            : '',
                        'date' => userdate($invitem->collecteddate, get_string('strftimedatefullshort', 'langconfig')),
                        'timestamp' => $invitem->collecteddate,
                    ];
                }

                // Build JSON for the overflow popover (+N badge).
                if (!empty($overflowdisplay)) {
                    $overflowjsonitems = [];
                    foreach ($overflowdisplay as $oid => $ovitem) {
                        $m = $allmedia[$oid] ?? ['is_image' => false, 'url' => '', 'content' => ''];
                        $overflowjsonitems[] = [
                            'n' => format_string($ovitem->name),
                            'i' => (int)(bool)$m['is_image'],
                            'u' => $m['is_image'] ? $m['url'] : strip_tags($m['content']),
                        ];
                    }
                    $stashoverflowjson = json_encode($overflowjsonitems);
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
                    $rank = \block_playerhud\game::get_user_rank(
                        $this->instance->id,
                        $USER->id,
                        $player->currentxp,
                        $COURSE->id
                    );
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
                        $portraitemoji = $portraiturl
                            ? null
                            : \block_playerhud\utils::get_class_portrait_emoji_by_tier(
                                $class,
                                $portraittier
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
                            'portrait_emoji'  => $portraitemoji,
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

            // Build user picture (check for equipped avatar).
            $avatarid = (int) get_user_preferences('block_playerhud_avatar_' . $this->instance->id, 0);
            if ($avatarid > 0) {
                $avataritem = \block_playerhud\game::get_avatar_item((int)$this->instance->id, $avatarid);
                $userpicture = $avataritem
                    ? \block_playerhud\utils::get_avatar_html(
                        $avataritem,
                        \context_block::instance((int)$this->instance->id),
                        $OUTPUT
                    )
                    : $OUTPUT->user_picture($USER, ['size' => 120]);
            } else {
                $userpicture = $OUTPUT->user_picture($USER, ['size' => 120]);
            }

            // Final Data.
            $renderdata = [
                'username'         => fullname($USER),
                'userpicture'      => $userpicture,
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
                'hasmore'     => $stashhasmore,
                'morebadge'   => $stashmorebadge,
                'overflowjson' => $stashoverflowjson,
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
        } catch (\moodle_exception $me) {
            debugging($me->getMessage(), DEBUG_NORMAL);
        } catch (\Throwable $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
                throw $e;
            }
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
