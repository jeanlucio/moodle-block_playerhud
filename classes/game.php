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

namespace block_playerhud;

/**
 * Game logic class for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game {
    /**
     * Get or create a player record.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @return \stdClass The player object.
     */
    public static function get_player($blockinstanceid, $userid) {
        global $DB;
        $player = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);

        if (!$player) {
            $player = new \stdClass();
            $player->blockinstanceid = $blockinstanceid;
            $player->userid = $userid;
            $player->currentxp = 0;
            $player->enable_gamification = 1;
            $player->ranking_visibility = 1;
            $player->timecreated = time();
            $player->timemodified = time();
            $player->id = $DB->insert_record('block_playerhud_user', $player);
        }
        return $player;
    }

    /**
     * Enable or disable gamification for a user.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @param bool $status True to enable, false to disable.
     */
    public static function toggle_gamification($blockinstanceid, $userid, $status) {
        global $DB;
        $player = self::get_player($blockinstanceid, $userid);
        $player->enable_gamification = ($status) ? 1 : 0;
        $player->timemodified = time();
        $DB->update_record('block_playerhud_user', $player);
    }

    /**
     * Get user inventory for this block instance.
     *
     * @param int $userid The user ID.
     * @param int $blockinstanceid The block instance ID.
     * @return array List of items.
     */
    public static function get_inventory($userid, $blockinstanceid) {
        global $DB;

        $sql = "SELECT inv.id as unique_inventory_id, i.*, inv.timecreated as collecteddate
                  FROM {block_playerhud_items} i
                  JOIN {block_playerhud_inventory} inv ON inv.itemid = i.id
                 WHERE inv.userid = :userid AND i.blockinstanceid = :pid
              ORDER BY inv.timecreated DESC";

        return $DB->get_records_sql($sql, ['userid' => $userid, 'pid' => $blockinstanceid]);
    }

    /**
     * Check if user has a specific item.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID.
     * @return bool True if exists.
     */
    public static function has_item($userid, $itemid) {
        global $DB;
        return $DB->record_exists('block_playerhud_inventory', [
            'userid' => $userid,
            'itemid' => $itemid,
        ]);
    }

    /**
     * Process item collection logic (Shared between Controller and External API).
     *
     * @param int $instanceid Block instance ID.
     * @param int $dropid Drop location ID.
     * @param int $userid User ID.
     * @return array Result data and game stats.
     * @throws \moodle_exception
     */
    public static function process_collection($instanceid, $dropid, $userid) {
        global $DB;

        // 1. Validation.
        $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
        $item = $DB->get_record('block_playerhud_items', ['id' => $drop->itemid], '*', MUST_EXIST);

        if (!$item->enabled) {
            throw new \moodle_exception('itemnotfound', 'block_playerhud');
        }

        // 2. Check Limits & Cooldown.
        $inventory = $DB->get_records('block_playerhud_inventory', [
            'userid' => $userid,
            'dropid' => $drop->id,
        ], 'timecreated DESC');

        $count = count($inventory);
        $lastcollected = reset($inventory);

        if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        if ($lastcollected && $drop->respawntime > 0) {
            $readytime = $lastcollected->timecreated + $drop->respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', '', $minutesleft);
            }
        }

        // 3. Transaction.
        $earnedxp = 0;
        $transaction = $DB->start_delegated_transaction();
        try {
            $newinv = new \stdClass();
            $newinv->userid = $userid;
            $newinv->itemid = $item->id;
            $newinv->dropid = $drop->id;
            $newinv->timecreated = time();
            $newinv->source = 'map';
            $DB->insert_record('block_playerhud_inventory', $newinv);

            // Infinite drops (0 maxusage) give 0 XP to prevent farming.
            $isinfinitedrop = ((int)$drop->maxusage === 0);

            if ($item->xp > 0 && !$isinfinitedrop) {
                $earnedxp = (int)$item->xp;
                $player = self::get_player($instanceid, $userid);
                $player->currentxp += $earnedxp;
                $player->timemodified = time();
                $DB->update_record('block_playerhud_user', $player);
            }
            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        // 4. Prepare Response Data.
        $msgparams = new \stdClass();
        $msgparams->name = format_string($item->name);
        $msgparams->xp = ($earnedxp > 0) ? " (+{$earnedxp} XP)" : "";
        $message = get_string('collected_msg', 'block_playerhud', $msgparams);

        // Calculate Stats for HUD update.
        $player = self::get_player($instanceid, $userid);
        $bi = $DB->get_record('block_instances', ['id' => $instanceid]);
        $config = unserialize(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }
        $stats = self::get_game_stats($config, $instanceid, $player->currentxp);

        // Prepare Item Data for Stash update.
        $context = \context_block::instance($instanceid);
        $media = \block_playerhud\utils::get_item_display_data($item, $context);

        $itemdata = [
            'name' => format_string($item->name),
            'xp' => (int)$item->xp,
            'image' => $media['is_image'] ? $media['url'] : strip_tags($media['content']),
            'isimage' => $media['is_image'] ? 1 : 0,
            'description' => !empty($item->description) ? format_text($item->description, FORMAT_HTML) : '',
            'date' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
            'timestamp' => time(),
        ];

        // Cooldown Calculation.
        $cooldowndeadline = 0;
        $limitreached = false;

        $newcount = $count + 1;
        if ($drop->maxusage > 0 && $newcount >= $drop->maxusage) {
            $limitreached = true;
        }
        if (!$limitreached && $drop->respawntime > 0) {
            $cooldowndeadline = time() + $drop->respawntime;
        }

        return [
            'success' => true,
            'message' => $message,
            'game_data' => [
                'currentxp' => (int)$player->currentxp,
                'level' => (int)$stats['level'],
                'max_levels' => (int)$stats['max_levels'],
                'xp_target' => (int)$stats['total_game_xp'],
                'progress' => (int)$stats['progress'],
                'total_game_xp' => (int)$stats['total_game_xp'],
                'level_class' => $stats['level_class'],
                'is_win' => ($player->currentxp >= $stats['total_game_xp'] && $stats['total_game_xp'] > 0),
            ],
            'item_data' => $itemdata,
            'cooldown_deadline' => (int)$cooldowndeadline,
            'limit_reached' => (bool)$limitreached,
        ];
    }

    /**
     * Calculate game statistics based on block settings.
     *
     * @param object $config The block instance configuration.
     * @param int $blockinstanceid The block instance ID.
     * @param int $currentxp Current user XP.
     * @return array Stats array.
     */
    public static function get_game_stats($config, $blockinstanceid, $currentxp) {
        global $DB;

        // Settings from block configuration.
        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;

        // Calculate Game Goal (Sum of finite items).
        $allitems = $DB->get_records('block_playerhud_items', [
            'blockinstanceid' => $blockinstanceid,
            'enabled' => 1,
        ]);
        $totalgamexp = 0;

        if ($allitems) {
            foreach ($allitems as $item) {
                $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $item->id]);
                if (!empty($drops)) {
                    foreach ($drops as $drop) {
                        if ($drop->maxusage > 0) {
                            $totalgamexp += ($item->xp * $drop->maxusage);
                        }
                    }
                }
            }
        }

        $rawlevel = 1 + floor($currentxp / $xpperlevel);
        $level = ($rawlevel > $maxlevels) ? $maxlevels : $rawlevel;

        $xpfornextlevel = 0;
        $ismaxlevel = ($level >= $maxlevels);

        if (!$ismaxlevel) {
            $xpfornextlevel = ($level * $xpperlevel) - $currentxp;
        }

        if ($maxlevels > 0) {
            $tier = ceil(($level / $maxlevels) * 5);
        } else {
            $tier = 1;
        }

        $tier = max(1, min(5, (int)$tier));
        $levelclass = 'ph-lvl-tier-' . $tier;

        $percentage = ($totalgamexp > 0) ? ($currentxp / $totalgamexp) * 100 : 0;
        $visualprogress = min(100, $percentage);

        return [
            'level' => $level,
            'max_levels' => $maxlevels,
            'xp_per_level' => $xpperlevel,
            'xp_next' => $xpfornextlevel,
            'is_max' => $ismaxlevel,
            'level_class' => $levelclass,
            'total_game_xp' => $totalgamexp,
            'progress' => round($visualprogress),
        ];
    }

    /**
     * Toggle user visibility in ranking.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @param bool $visible True or false.
     */
    public static function toggle_ranking_visibility($blockinstanceid, $userid, $visible) {
        global $DB;
        $player = self::get_player($blockinstanceid, $userid);
        $player->ranking_visibility = ($visible) ? 1 : 0;
        $DB->update_record('block_playerhud_user', $player);
    }

    /**
     * Get the rank of a specific user considering tie-breakers.
     *
     * @param int $blockinstanceid Instance ID.
     * @param int $userid User ID.
     * @param int $currentxp Current XP.
     * @return int The rank.
     */
    public static function get_user_rank($blockinstanceid, $userid, $currentxp) {
        global $DB;

        // Search for 'timemodified' of current user for tie-breaking.
        $usertime = $DB->get_field('block_playerhud_user', 'timemodified', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);

        if (!$usertime) {
            $usertime = time(); // Fallback.
        }

        // RANKING LOGIC AND TIE-BREAKER:
        // Count users who:
        // 1. Have MORE XP than me.
        // 2. OR have the SAME XP, but the record is OLDER (lower timemodified = arrived first).

        $sql = "SELECT COUNT(id)
                  FROM {block_playerhud_user}
                 WHERE blockinstanceid = :pid
                   AND enable_gamification = 1
                   AND ranking_visibility = 1
                   AND (
                       currentxp > :xp
                       OR (currentxp = :xp_tie AND timemodified < :tm)
                   )";

        $params = [
            'pid' => $blockinstanceid,
            'xp' => $currentxp,
            'xp_tie' => $currentxp,
            'tm' => $usertime,
        ];

        $betterplayers = $DB->count_records_sql($sql, $params);

        return $betterplayers + 1;
    }

    /**
     * Fetch complete Leaderboard for block instance.
     *
     * @param int $blockinstanceid The instance ID.
     * @param int $courseid The course ID.
     * @param int $currentuserid Current user ID.
     * @param bool $isteacher Is user teacher?
     * @return array
     */
    public static function get_leaderboard($blockinstanceid, $courseid, $currentuserid, $isteacher) {
        global $DB;

        // 1. Groups Map.
        $usergroupsmap = [];
        $sqlgroups = "SELECT gm.userid, g.name
                        FROM {groups} g
                        JOIN {groups_members} gm ON g.id = gm.groupid
                       WHERE g.courseid = :courseid";

        $memberships = $DB->get_recordset_sql($sqlgroups, ['courseid' => $courseid]);
        foreach ($memberships as $rec) {
            $usergroupsmap[$rec->userid][] = format_string($rec->name);
        }
        $memberships->close();

        // 2. Search Users.
        $userfieldsapi = \core_user\fields::for_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        // SQL Modification:
        // Sort by XP (Descending) and then by DATE (Ascending - first comes first wins).
        $sql = "SELECT $userfields, u.id as userid,
                       pu.currentxp, pu.ranking_visibility, pu.enable_gamification, pu.timemodified
                  FROM {block_playerhud_user} pu
                  JOIN {user} u ON pu.userid = u.id
                 WHERE pu.blockinstanceid = :pid
              ORDER BY pu.currentxp DESC, pu.timemodified ASC, u.lastname ASC";

        $rawusers = $DB->get_records_sql($sql, ['pid' => $blockinstanceid]);

        $individualranking = [];
        $coursecontext = \context_course::instance($courseid);

        // Control variables for tie (Shared Rank).
        $rankcounter = 1;
        $lastxp = -1;
        $lasttime = -1;
        $currentdisplayrank = 1;

        foreach ($rawusers as $usr) {
            $isme = ($usr->userid == $currentuserid);

            if (has_capability('block/playerhud:manage', $coursecontext, $usr->userid)) {
                continue;
            }

            // Status.
            $ispaused = ($usr->enable_gamification == 0);
            $ishidden = ($usr->ranking_visibility == 0);
            $iscompetitor = (!$ispaused && !$ishidden);

            $shoulddisplay = ($iscompetitor || $isteacher || $isme);
            if (!$shoulddisplay) {
                continue;
            }

            // TIE LOGIC:
            // If competitor, calculate rank. If not, dash.
            $usr->rank = '-';
            $usr->medal_emoji = null;

            if ($iscompetitor) {
                // If XP and Time are DIFFERENT from previous, update display rank.
                // Otherwise (exact tie), keep the previous display rank.
                // Absolute counter always increments.
                if ($usr->currentxp != $lastxp || $usr->timemodified != $lasttime) {
                    $currentdisplayrank = $rankcounter;
                }

                $usr->rank = $currentdisplayrank;

                // Medals based on shared rank.
                if ($usr->rank == 1) {
                    $usr->medal_emoji = '🥇';
                } else if ($usr->rank == 2) {
                    $usr->medal_emoji = '🥈';
                } else if ($usr->rank == 3) {
                    $usr->medal_emoji = '🥉';
                }

                // Update references for next iteration.
                $lastxp = $usr->currentxp;
                $lasttime = $usr->timemodified;
                $rankcounter++;
            }

            // Data Formatting.
            $mygroups = isset($usergroupsmap[$usr->userid]) ? $usergroupsmap[$usr->userid] : [];
            $usr->group_name = empty($mygroups) ? '-' : implode(', ', $mygroups);

            // New: Formatted date for transparency.
            $usr->last_score_date = userdate($usr->timemodified, get_string('strftimedatetimeshort', 'langconfig'));

            $usr->is_me = $isme;
            $usr->is_paused = $ispaused;
            $usr->is_hidden_marker = ($ishidden && !$ispaused);
            $usr->fullname = fullname($usr);

            $individualranking[] = $usr;
        }

        // Groups logic.
        $groupranking = [];
        $groups = groups_get_all_groups($courseid);

        if ($groups) {
            foreach ($groups as $grp) {
                $members = groups_get_members($grp->id, 'u.id');
                if (!$members) {
                    continue;
                }
                $memberids = array_keys($members);
                [$insql, $inparams] = $DB->get_in_or_equal($memberids);
                $sqlgrp = "SELECT SUM(currentxp) as total, COUNT(id) as qtd
                             FROM {block_playerhud_user}
                            WHERE blockinstanceid = ?
                              AND enable_gamification = 1
                              AND ranking_visibility = 1
                              AND userid $insql";
                $params = array_merge([$blockinstanceid], $inparams);
                $grpstats = $DB->get_record_sql($sqlgrp, $params);
                if ($grpstats && $grpstats->qtd > 0) {
                    $avg = floor($grpstats->total / $grpstats->qtd);
                    $gobj = new \stdClass();
                    $gobj->id = $grp->id;
                    $gobj->name = format_string($grp->name);
                    $gobj->average_xp = $avg;
                    $gobj->member_count = $grpstats->qtd;
                    $gobj->is_my_group = groups_is_member($grp->id, $currentuserid);
                    $groupranking[] = $gobj;
                }
            }
            usort($groupranking, function ($a, $b) {
                return $b->average_xp <=> $a->average_xp;
            });
            $grank = 1;
            foreach ($groupranking as &$g) {
                $g->rank = $grank++;
                $g->medal_emoji = ($g->rank == 1) ? '🏆' : null;
            }
        }

        return ['individual' => $individualranking, 'groups' => $groupranking];
    }
}
