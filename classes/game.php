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

namespace block_playerhud;

/**
 * Game logic class for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game {
    /** @var int Milestone bitmask: first PlayerCoin collected. */
    const MILESTONE_COIN = 1;

    /** @var int Milestone bitmask: first quest reward claimed. */
    const MILESTONE_FIRSTQUEST = 2;

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
            $player->milestones = 0;
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
                 WHERE inv.userid = :userid AND i.blockinstanceid = :pid AND inv.source NOT IN ('revoked', 'consumed')
              ORDER BY inv.timecreated DESC, inv.id DESC";
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

        // Player is only set inside the transaction when XP was awarded; fetch it for the xp=0/infinite case.
        if (!isset($player)) {
            $player = self::get_player($instanceid, $userid);
        }

        $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
        $rawconfig = base64_decode($bi->configdata ?? '', true);
        $config = ($rawconfig !== false && $rawconfig !== '') ? unserialize_object($rawconfig) : new \stdClass();
        if (!is_object($config)) {
            $config = new \stdClass();
        }
        $stats = self::get_game_stats($config, $instanceid, $player->currentxp);

        // Detect a level boundary crossed by this collection. Compare the level before the
        // award (XP minus what was just earned) against the post-award level.
        $oldlevel = self::xp_to_level(
            (int)$player->currentxp - $earnedxp,
            (int)$stats['xp_per_level'],
            (int)$stats['max_levels']
        );
        $leveledup = ($earnedxp > 0 && (int)$stats['level'] > $oldlevel);

        // Detect crossing 100% of the game's total XP ("beating the game") with this
        // collection. Compared as a transition so it fires once per crossing.
        $gametotal = (int)$stats['total_game_xp'];
        $won = ($gametotal > 0
            && (int)$player->currentxp >= $gametotal
            && ((int)$player->currentxp - $earnedxp) < $gametotal);

        // One-time milestone: the very first PlayerCoin this user collects in this
        // instance. Stored as a bit in the player's milestones bitmask so it shows once.
        $milestone = '';
        $iscoin = (isset($item->action_type) && $item->action_type === 'playercoin');
        if ($iscoin && !((int)$player->milestones & self::MILESTONE_COIN)) {
            $player->milestones = (int)$player->milestones | self::MILESTONE_COIN;
            $player->timemodified = time();
            $DB->update_record('block_playerhud_user', $player);
            $milestone = 'coin';
        }

        // Prepare Item Data for Stash update.
        $context = \context_block::instance($instanceid);
        $media = \block_playerhud\utils::get_item_display_data($item, $context);

        $itemdata = [
            'name' => format_string($item->name),
            'xp' => ((int)$drop->maxusage === 0) ? 0 : (int)$item->xp,
            'image' => $media['is_image'] ? $media['url'] : strip_tags($media['content']),
            'isimage' => $media['is_image'] ? 1 : 0,
            'description' => !empty($item->description)
                ? format_text($item->description, FORMAT_HTML, ['context' => $context])
                : '',
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
                'leveled_up' => $leveledup,
                'won' => $won,
            ],
            'item_data' => $itemdata,
            'cooldown_deadline' => (int)$cooldowndeadline,
            'limit_reached' => (bool)$limitreached,
            'milestone' => $milestone,
        ];
    }

    /**
     * Convert an XP amount into a level number using the configured progression.
     *
     * Mirrors the level formula used in get_game_stats so callers can compare a
     * pre-award level against a post-award level without rebuilding the full stats.
     *
     * @param int $xp The XP amount to convert.
     * @param int $xpperlevel XP required per level.
     * @param int $maxlevels The level cap.
     * @return int The resulting level (clamped between 1 and $maxlevels).
     */
    public static function xp_to_level(int $xp, int $xpperlevel, int $maxlevels): int {
        if ($xpperlevel <= 0) {
            return 1;
        }
        $rawlevel = 1 + (int) floor($xp / $xpperlevel);
        if ($rawlevel > $maxlevels) {
            return $maxlevels;
        }
        return max(1, $rawlevel);
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
        // 1. Get all enabled items.
        $allitems = $DB->get_records('block_playerhud_items', [
            'blockinstanceid' => $blockinstanceid,
            'enabled' => 1,
        ]);

        $totalgamexp = 0;

        if ($allitems) {
            // 2. Optimization: Get ALL drops for this instance in ONE query.
            // Joining to ensure we only get drops for enabled items.
            $sql = "SELECT d.*, d.itemid
                      FROM {block_playerhud_drops} d
                      JOIN {block_playerhud_items} i ON d.itemid = i.id
                     WHERE i.blockinstanceid = :instanceid
                       AND i.enabled = 1";

            $alldrops = $DB->get_records_sql($sql, ['instanceid' => $blockinstanceid]);

            // 3. Group drops by itemid in memory.
            $dropsbyitem = [];
            foreach ($alldrops as $d) {
                $dropsbyitem[$d->itemid][] = $d;
            }

            // 4. Calculate total.
            foreach ($allitems as $item) {
                if (isset($dropsbyitem[$item->id])) {
                    foreach ($dropsbyitem[$item->id] as $drop) {
                        if ($drop->maxusage > 0) {
                            $totalgamexp += ($item->xp * $drop->maxusage);
                        }
                    }
                }
            }
        }

        // Add XP available from enabled quest rewards to the game total.
        $questxp = $DB->get_field_sql(
            "SELECT COALESCE(SUM(reward_xp), 0) FROM {block_playerhud_quests}
              WHERE blockinstanceid = :instanceid AND enabled = 1",
            ['instanceid' => $blockinstanceid]
        );
        $totalgamexp += (int)$questxp;

        $level = self::xp_to_level((int)$currentxp, $xpperlevel, $maxlevels);

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
            'progress' => (int)round($visualprogress),
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
     * EXCLUDES users with 'manage' capability (Teachers/Admins) from the count.
     * When SEPARATEGROUPS is active, only counts users in the same group.
     *
     * @param int $blockinstanceid Instance ID.
     * @param int $userid User ID.
     * @param int $currentxp Current XP.
     * @param int $courseid Course ID for SEPARATEGROUPS support (0 = disabled).
     * @return int The rank.
     */
    public static function get_user_rank($blockinstanceid, $userid, $currentxp, $courseid = 0) {
        global $DB;

        // Search for 'timemodified' of current user for tie-breaking.
        $usertime = $DB->get_field('block_playerhud_user', 'timemodified', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);

        if (!$usertime) {
            $usertime = time(); // Fallback.
        }

        // Filtering Teachers.
        // We must fetch the context to identify managers.
        $bi = $DB->get_record('block_instances', ['id' => $blockinstanceid], 'parentcontextid', MUST_EXIST);
        $context = \context::instance_by_id($bi->parentcontextid);

        // Get IDs of users with capability 'block/playerhud:manage' (Teachers).
        // $doanything = true ensures site admins are included, matching has_capability() behaviour.
        $managers = get_users_by_capability(
            $context,
            'block/playerhud:manage',
            'u.id',
            '',
            '',
            '',
            '',
            '',
            true,
            false,
            false
        );

        $managerids = array_keys($managers);
        // Get_users_by_capability does not enumerate site admins (they bypass the capability
        // system via $CFG->siteadmins). Merge them explicitly so they are excluded from the count.
        $managerids = array_unique(array_merge($managerids, array_keys(get_admins())));

        // Build exclusion clause.
        $excludeclause = "";
        $params = [
            'pid' => $blockinstanceid,
            'xp' => $currentxp,
            'xp_tie' => $currentxp,
            'tm' => $usertime,
        ];

        if (!empty($managerids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($managerids, SQL_PARAMS_NAMED, 'ex', false);
            // False = NOT IN.
            $excludeclause = "AND userid $insql";
            $params = array_merge($params, $inparams);
        }

        // SEPARATEGROUPS: restrict count to same-group members only.
        $groupclause = '';
        if ($courseid > 0) {
            $course = get_course($courseid);
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS) {
                $usergroups = groups_get_user_groups($courseid, $userid);
                $usergroupids = $usergroups[0] ?? [];
                if (!empty($usergroupids)) {
                    [$sgsql, $sgparams] = $DB->get_in_or_equal(
                        array_values($usergroupids),
                        SQL_PARAMS_NAMED,
                        'grp'
                    );
                    $groupclause = "AND userid IN "
                        . "(SELECT DISTINCT gm.userid FROM {groups_members} gm WHERE gm.groupid $sgsql)";
                    $params = array_merge($params, $sgparams);
                } else {
                    return 1;
                }
            }
        }

        // RANKING LOGIC:
        // Count users who:
        // 1. Have MORE XP than me.
        // 2. OR have the SAME XP, but arrived earlier.
        // 3. AND are NOT managers/teachers.
        // 4. AND are still actively enrolled in the course.
        $enrolledclause = '';
        if ($courseid > 0) {
            $enrolledclause = "AND userid IN (
                SELECT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :enrol_courseid
                 WHERE ue.status = 0 AND e.status = 0
            )";
            $params['enrol_courseid'] = $courseid;
        }

        $sql = "SELECT COUNT(id)
                  FROM {block_playerhud_user}
                 WHERE blockinstanceid = :pid
                   AND enable_gamification = 1
                   AND ranking_visibility = 1
                   $excludeclause
                   $groupclause
                   $enrolledclause
                   AND (
                       currentxp > :xp
                       OR (currentxp = :xp_tie AND timemodified < :tm)
                   )";

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
     * @param int $filtergroup Group ID to filter individual ranking (teacher only, 0 = no filter).
     * @return array
     */
    public static function get_leaderboard($blockinstanceid, $courseid, $currentuserid, $isteacher, $filtergroup = 0) {
        global $DB;

        // 1. Groups Map.
        $usergroupsmap = [];
        $allgroupids = [];
        $sqlgroups = "SELECT gm.userid, g.id AS groupid, g.name
                        FROM {groups} g
                        JOIN {groups_members} gm ON g.id = gm.groupid
                       WHERE g.courseid = :courseid";

        $memberships = $DB->get_recordset_sql($sqlgroups, ['courseid' => $courseid]);
        foreach ($memberships as $rec) {
            $groupid = (int) $rec->groupid;
            $usergroupsmap[$rec->userid][] = ['id' => $groupid, 'name' => format_string($rec->name)];
            $allgroupids[$groupid] = true;
        }
        $memberships->close();

        // PlayerGroup badges (soft dependency), single bulk query reused by both rankings.
        $groupbadges = [];
        $hasbadgeapi = method_exists('\mod_playergroup\api\group_info', 'get_badges_for_groups');
        if ($hasbadgeapi && !empty($allgroupids)) {
            $groupbadges = \mod_playergroup\api\group_info::get_badges_for_groups(array_keys($allgroupids));
        }

        // SEPARATEGROUPS: build the set of allowed user IDs for individual ranking.
        $alloweduserids = null; // Null = no restriction.
        if (!$isteacher) {
            $course = get_course($courseid);
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS) {
                $usergroups = groups_get_user_groups($courseid, $currentuserid);
                $usergroupids = $usergroups[0] ?? [];
                if (!empty($usergroupids)) {
                    [$sgsql, $sgparams] = $DB->get_in_or_equal(
                        array_values($usergroupids),
                        SQL_PARAMS_NAMED,
                        'sgm'
                    );
                    $membersql = "SELECT DISTINCT gm.userid FROM {groups_members} gm WHERE gm.groupid $sgsql";
                    $memberids = $DB->get_fieldset_sql($membersql, $sgparams);
                    $alloweduserids = array_flip($memberids);
                } else {
                    // No groups: show only the current user.
                    $alloweduserids = [$currentuserid => true];
                }
            }
        }

        // 2. Search Users.
        $userfieldsapi = \core_user\fields::for_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        // SQL Modification:
        // Sort by XP (Descending) and then by DATE (Ascending - first comes first wins).
        // Only include users actively enrolled in the course.
        $sql = "SELECT $userfields, u.id as userid,
                       pu.currentxp, pu.ranking_visibility, pu.enable_gamification, pu.timemodified
                  FROM {block_playerhud_user} pu
                  JOIN {user} u ON pu.userid = u.id
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :enrolcourseid AND e.status = 0
                 WHERE pu.blockinstanceid = :pid
              ORDER BY pu.currentxp DESC, pu.timemodified ASC, u.lastname ASC";

        $rawusers = $DB->get_records_sql($sql, ['pid' => $blockinstanceid, 'enrolcourseid' => $courseid]);

        $individualranking = [];
        $coursecontext = \context_course::instance($courseid);

        $managers = get_users_by_capability($coursecontext, 'block/playerhud:manage', 'u.id');
        $managerids = array_fill_keys(array_keys($managers), true);
        foreach (array_keys(get_admins()) as $adminid) {
            $managerids[$adminid] = true;
        }

        // Control variables for tie (Shared Rank).
        $rankcounter = 1;
        $lastxp = -1;
        $lasttime = -1;
        $currentdisplayrank = 1;

        foreach ($rawusers as $usr) {
            $isme = ($usr->userid == $currentuserid);

            if (isset($managerids[$usr->userid])) {
                continue;
            }

            // Teacher group filter: skip users outside the selected group.
            if ($isteacher && $filtergroup > 0 && !groups_is_member($filtergroup, $usr->userid)) {
                continue;
            }

            // SEPARATEGROUPS: skip users outside the current user's groups.
            if ($alloweduserids !== null && !isset($alloweduserids[$usr->userid])) {
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
            $mygroups = $usergroupsmap[$usr->userid] ?? [];
            $usr->groups = [];
            foreach ($mygroups as $grp) {
                $badge = $groupbadges[$grp['id']] ?? '';
                $usr->groups[] = [
                    'name'      => $grp['name'],
                    'badge'     => $badge,
                    'has_badge' => ($badge !== ''),
                ];
            }
            $usr->has_groups = !empty($usr->groups);
            $usr->group_name = empty($mygroups) ? '-' : implode(', ', array_column($mygroups, 'name'));
            $usr->last_score_date = ($usr->currentxp > 0)
                ? userdate($usr->timemodified, get_string('strftimedatetimeshort', 'langconfig'))
                : '-';

            $usr->is_me = $isme;
            $usr->is_paused = $ispaused;
            $usr->is_hidden_marker = ($ishidden && !$ispaused);
            $usr->fullname = fullname($usr);

            $individualranking[] = $usr;
        }

        // Groups logic (Optimized Zero N+1 query).
        $groupranking = [];
        $groups = groups_get_all_groups($courseid);

        if ($groups) {
            $groupids = array_keys($groups);
            [$gsql, $gparams] = $DB->get_in_or_equal($groupids);

            $sqlgrp = "SELECT gm.groupid, SUM(pu.currentxp) as total, COUNT(pu.id) as qtd,
                                MAX(pu.timemodified) as last_xp_change
                         FROM {groups_members} gm
                         JOIN {block_playerhud_user} pu ON pu.userid = gm.userid
                        WHERE pu.blockinstanceid = ?
                          AND pu.enable_gamification = 1
                          AND pu.ranking_visibility = 1
                          AND gm.groupid $gsql
                     GROUP BY gm.groupid";

            $params = array_merge([$blockinstanceid], $gparams);
            $allgroupstats = $DB->get_records_sql($sqlgrp, $params);

            foreach ($groups as $grp) {
                if (isset($allgroupstats[$grp->id]) && $allgroupstats[$grp->id]->qtd > 0) {
                    $stat = $allgroupstats[$grp->id];
                    $avg = floor($stat->total / $stat->qtd);

                    $gobj = new \stdClass();
                    $gobj->id = $grp->id;
                    $gobj->name = format_string($grp->name);
                    $gobj->average_xp = $avg;
                    $gobj->last_xp_change = (int)$stat->last_xp_change;
                    $gobj->member_count = $stat->qtd;
                    $gobj->is_my_group = groups_is_member($grp->id, $currentuserid);

                    $badge = $groupbadges[(int) $grp->id] ?? '';
                    $gobj->badge = $badge;
                    $gobj->has_badge = ($badge !== '');

                    $groupranking[] = $gobj;
                }
            }

            usort($groupranking, function ($a, $b) {
                if ($a->average_xp !== $b->average_xp) {
                    return $b->average_xp <=> $a->average_xp;
                }
                // Tiebreaker: group whose last XP event was earliest ranks higher.
                return $a->last_xp_change <=> $b->last_xp_change;
            });

            $grank = 1;
            $lastgavg = -1;
            $lastgtime = -1;
            $currentgdisplayrank = 1;
            foreach ($groupranking as &$g) {
                if ($g->average_xp !== $lastgavg || $g->last_xp_change !== $lastgtime) {
                    $currentgdisplayrank = $grank;
                }
                $g->rank = $currentgdisplayrank;
                $g->medal_emoji = ($g->rank == 1) ? '🏆' : null;
                $lastgavg = $g->average_xp;
                $lastgtime = $g->last_xp_change;
                $grank++;
            }
            unset($g);
        }

        return ['individual' => $individualranking, 'groups' => $groupranking];
    }

    /**
     * Get the RPG progress record for a user (includes classid, karma, chapter history).
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @return \stdClass|false The rpg_progress record or false if not found.
     */
    public static function get_player_class($blockinstanceid, $userid) {
        global $DB;
        return $DB->get_record('block_playerhud_rpg_progress', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
    }

    /**
     * Assign an RPG class to a player, creating the progress record if needed.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @param int $classid The class ID to assign.
     */
    public static function assign_class($blockinstanceid, $userid, $classid) {
        global $DB;
        $progress = self::get_player_class($blockinstanceid, $userid);
        if ($progress) {
            $progress->classid = $classid;
            $DB->update_record('block_playerhud_rpg_progress', $progress);
        } else {
            $progress = new \stdClass();
            $progress->blockinstanceid = $blockinstanceid;
            $progress->userid = $userid;
            $progress->classid = $classid;
            $progress->karma = 0;
            $progress->current_nodes = null;
            $progress->completed_chapters = null;
            $DB->insert_record('block_playerhud_rpg_progress', $progress);
        }
    }

    /**
     * Get all RPG classes for a block instance, ordered by name.
     *
     * @param int $blockinstanceid The block instance ID.
     * @return array Array of class objects keyed by ID.
     */
    public static function get_all_classes($blockinstanceid) {
        global $DB;
        return $DB->get_records('block_playerhud_classes', ['blockinstanceid' => $blockinstanceid], 'name ASC');
    }

    /**
     * Get the current karma value for a player.
     *
     * Returns 0 when no RPG progress record exists yet.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @return int The current karma value.
     */
    public static function get_player_karma(int $blockinstanceid, int $userid): int {
        global $DB;
        $karma = $DB->get_field('block_playerhud_rpg_progress', 'karma', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        return ($karma !== false) ? (int) $karma : 0;
    }

    /**
     * Adjust a player's karma by delta, clamped to [-999, 999].
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     * @param int $delta Positive or negative karma change.
     * @return int The new karma value after adjustment, or 0 if no record exists.
     */
    public static function adjust_karma(int $blockinstanceid, int $userid, int $delta): int {
        global $DB;
        $progress = $DB->get_record('block_playerhud_rpg_progress', [
            'blockinstanceid' => $blockinstanceid,
            'userid' => $userid,
        ]);
        if (!$progress) {
            return 0;
        }
        $newkarma = max(-999, min(999, (int) $progress->karma + $delta));
        $DB->set_field('block_playerhud_rpg_progress', 'karma', $newkarma, ['id' => $progress->id]);
        return $newkarma;
    }

    /**
     * Get all trades with their requirements and rewards for a specific block instance.
     * Optimized to avoid N+1 query problems using single batch queries.
     *
     * @param int $blockinstanceid The block instance ID.
     * @return array Array of trade objects populated with requirements and rewards.
     */
    public static function get_full_trades($blockinstanceid) {
        global $DB;

        // 1. Fetch all base trades for this instance.
        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $blockinstanceid], 'name ASC');

        if (!$trades) {
            return [];
        }

        // Prepare the IN clause for bulk fetching dependencies.
        $tradeids = array_keys($trades);
        [$insql, $inparams] = $DB->get_in_or_equal($tradeids, SQL_PARAMS_NAMED, 'trd');

        // Initialize empty arrays to prevent undefined property warnings.
        foreach ($trades as $trade) {
            $trade->requirements = [];
            $trade->rewards = [];
        }

        // 2. Fetch all requirements in a single optimized query.
        $sqlreq = "SELECT req.id, req.tradeid, req.itemid, req.qty,
                          i.name, i.image, i.required_class_id
                     FROM {block_playerhud_trade_reqs} req
                     JOIN {block_playerhud_items} i ON req.itemid = i.id
                    WHERE req.tradeid $insql
                 ORDER BY req.id ASC";

        $requirements = $DB->get_records_sql($sqlreq, $inparams);

        if ($requirements) {
            foreach ($requirements as $req) {
                if (isset($trades[$req->tradeid])) {
                    $trades[$req->tradeid]->requirements[] = $req;
                }
            }
        }

        // 3. Fetch all rewards in a single optimized query.
        $sqlrew = "SELECT rew.id, rew.tradeid, rew.itemid, rew.qty,
                          i.name, i.image, i.required_class_id
                     FROM {block_playerhud_trade_rewards} rew
                     JOIN {block_playerhud_items} i ON rew.itemid = i.id
                    WHERE rew.tradeid $insql
                 ORDER BY rew.id ASC";

        $rewards = $DB->get_records_sql($sqlrew, $inparams);

        if ($rewards) {
            foreach ($rewards as $rew) {
                if (isset($trades[$rew->tradeid])) {
                    $trades[$rew->tradeid]->rewards[] = $rew;
                }
            }
        }

        return $trades;
    }

    /**
     * Fetch an enabled item that may serve as an avatar for the given instance.
     *
     * @param int $instanceid Block instance ID.
     * @param int $itemid Item ID.
     * @return \stdClass|null Item record or null if not found / disabled.
     */
    public static function get_avatar_item(int $instanceid, int $itemid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('block_playerhud_items', [
            'id'              => $itemid,
            'blockinstanceid' => $instanceid,
            'enabled'         => 1,
        ]);
        return $record ?: null;
    }

    /**
     * Determine the state of the Suggest Trades button for a block instance.
     *
     * The button is enabled only when a PlayerCoin item exists AND at least one
     * avatar item exists AND not all avatar items are already covered by trades.
     *
     * "Covered" means the avatar appears as the sole reward in a trade, AND a
     * trade that bundles every avatar as a reward already exists.
     *
     * Returns an array with:
     *   - 'enabled' (bool) — whether the button should be clickable.
     *   - 'reason'  (string) — 'prereq' | 'all_covered' | '' (when enabled).
     *
     * @param int $instanceid Block instance ID.
     * @return array
     */
    public static function suggest_trades_state(int $instanceid): array {
        global $DB;

        $hascoin = $DB->record_exists('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type'     => 'playercoin',
        ]);
        $hasavatars = $DB->record_exists_select(
            'block_playerhud_items',
            "blockinstanceid = :id AND action_type = 'avatar_profile'",
            ['id' => $instanceid]
        );

        if (!$hascoin || !$hasavatars) {
            return ['enabled' => false, 'reason' => 'prereq'];
        }

        $avatarids = array_keys($DB->get_records_select(
            'block_playerhud_items',
            "blockinstanceid = :id AND action_type = 'avatar_profile'",
            ['id' => $instanceid],
            'id ASC',
            'id'
        ));

        if (empty($avatarids)) {
            return ['enabled' => false, 'reason' => 'prereq'];
        }

        [$avsql, $avparams] = $DB->get_in_or_equal($avatarids, SQL_PARAMS_NAMED, 'av');
        $coveredcnt = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT tr.itemid)
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid
                AND tr.itemid $avsql
                AND (SELECT COUNT(*) FROM {block_playerhud_trade_rewards} tr2
                      WHERE tr2.tradeid = tr.tradeid) = 1",
            array_merge(['iid' => $instanceid], $avparams)
        );

        if ($coveredcnt < count($avatarids)) {
            return ['enabled' => true, 'reason' => ''];
        }

        [$bsql, $bparams] = $DB->get_in_or_equal($avatarids, SQL_PARAMS_NAMED, 'bv');
        $hasbundle = !empty($DB->get_records_sql(
            "SELECT tr.tradeid
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid
                AND tr.itemid $bsql
           GROUP BY tr.tradeid
             HAVING COUNT(tr.itemid) >= :total",
            array_merge(['iid' => $instanceid, 'total' => count($avatarids)], $bparams)
        ));

        if ($hasbundle) {
            return ['enabled' => false, 'reason' => 'all_covered'];
        }

        return ['enabled' => true, 'reason' => ''];
    }

    /**
     * Build the heuristic trade suggestions for a block instance.
     *
     * One suggestion per avatar item that is not already the sole reward of an
     * existing trade, plus a single bundle (all avatars) when no bundle trade
     * exists yet. Requires a PlayerCoin item to exist.
     *
     * @param int $instanceid Block instance ID.
     * @return array List of suggestion descriptors (uid, cost_qty, cost_itemid, rewards, name, ...).
     */
    public static function build_trade_suggestions(int $instanceid): array {
        global $DB;

        $playercoin = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type'     => 'playercoin',
        ]);
        if (!$playercoin) {
            return [];
        }

        $avatars = $DB->get_records_select(
            'block_playerhud_items',
            "blockinstanceid = :id AND action_type = 'avatar_profile'",
            ['id' => $instanceid],
            'id ASC'
        );
        if (empty($avatars)) {
            return [];
        }

        $individualcoveredids = [];
        $avatarids = array_keys($avatars);
        [$avsql, $avparams] = $DB->get_in_or_equal($avatarids, SQL_PARAMS_NAMED, 'av');
        $soletrades = $DB->get_records_sql(
            "SELECT DISTINCT tr.itemid
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid
                AND tr.itemid $avsql
                AND (SELECT COUNT(*) FROM {block_playerhud_trade_rewards} tr2
                      WHERE tr2.tradeid = tr.tradeid) = 1",
            array_merge(['iid' => $instanceid], $avparams)
        );
        foreach ($soletrades as $r) {
            $individualcoveredids[$r->itemid] = true;
        }
        [$bsql, $bparams] = $DB->get_in_or_equal($avatarids, SQL_PARAMS_NAMED, 'bv');
        $bundleexists = !empty($DB->get_records_sql(
            "SELECT tr.tradeid
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid
                AND tr.itemid $bsql
           GROUP BY tr.tradeid
             HAVING COUNT(tr.itemid) >= :total",
            array_merge(['iid' => $instanceid, 'total' => count($avatarids)], $bparams)
        ));

        $suggestions = [];
        foreach ($avatars as $avatar) {
            if (isset($individualcoveredids[$avatar->id])) {
                continue;
            }
            $costqty = in_array($avatar->image, ['🤖', '👾'], true) ? 1 : 5;
            $suggestions[] = [
                'uid'          => 'ind_' . $avatar->id,
                'cost_qty'     => $costqty,
                'cost_itemid'  => $playercoin->id,
                'cost_emoji'   => '🪙',
                'reward_emoji' => $avatar->image,
                'reward_label' => format_string($avatar->name),
                'rewards'      => [['id' => $avatar->id, 'qty' => 1]],
                'name'         => format_string($avatar->name),
            ];
        }

        if (!$bundleexists) {
            $bundlerewards = array_map(fn($av) => ['id' => $av->id, 'qty' => 1], array_values($avatars));
            $suggestions[] = [
                'uid'          => 'bundle_all',
                'cost_qty'     => 50,
                'cost_itemid'  => $playercoin->id,
                'cost_emoji'   => '🪙',
                'reward_emoji' => '🎭',
                'reward_label' => get_string('avatar_pack_create', 'block_playerhud') .
                                  ' (' . count($avatars) . ')',
                'rewards'      => $bundlerewards,
                'name'         => get_string('avatar_pack_create', 'block_playerhud'),
            ];
        }

        return $suggestions;
    }

    /**
     * Persist a single trade from a suggestion descriptor.
     *
     * Creates a centralized one-time trade with one cost requirement and the
     * listed reward items. The caller is responsible for any surrounding
     * transaction.
     *
     * @param int $instanceid Block instance ID.
     * @param array $sug Suggestion descriptor from build_trade_suggestions().
     * @return int The new trade ID.
     */
    public static function create_trade_from_suggestion(int $instanceid, array $sug): int {
        global $DB;

        $now = time();
        $tradeid = $DB->insert_record('block_playerhud_trades', (object) [
            'blockinstanceid' => $instanceid,
            'name'            => $sug['name'],
            'groupid'         => 0,
            'centralized'     => 1,
            'onetime'         => 1,
            'timecreated'     => $now,
        ]);
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $tradeid,
            'itemid'  => $sug['cost_itemid'],
            'qty'     => $sug['cost_qty'],
        ]);
        foreach ($sug['rewards'] as $reward) {
            $DB->insert_record('block_playerhud_trade_rewards', (object) [
                'tradeid' => $tradeid,
                'itemid'  => $reward['id'],
                'qty'     => $reward['qty'],
            ]);
        }

        return $tradeid;
    }
}
