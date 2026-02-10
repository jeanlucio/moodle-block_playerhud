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
 * Game logic class for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

defined('MOODLE_INTERNAL') || die();

/**
 * Game logic class.
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
            'userid' => $userid
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
            'itemid' => $itemid
        ]);
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
            'enabled' => 1
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
     * Get the rank of a specific user.
     *
     * @param int $blockinstanceid Instance ID.
     * @param int $userid User ID.
     * @param int $currentxp Current XP.
     * @return int The rank.
     */
public static function get_user_rank($blockinstanceid, $userid, $currentxp) {
        global $DB;

        // [CORREÇÃO] A contagem de "quem está na minha frente" deve considerar APENAS
        // usuários que estão ativos E visíveis. Se um aluno oculto tem mais XP,
        // ele não deve empurrar o meu ranking para baixo.
        
        $sql = "SELECT COUNT(id)
                  FROM {block_playerhud_user}
                 WHERE blockinstanceid = :pid 
                   AND currentxp > :xp
                   AND enable_gamification = 1
                   AND ranking_visibility = 1";

        $betterplayers = $DB->count_records_sql($sql, ['pid' => $blockinstanceid, 'xp' => $currentxp]);
        
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
/**
     * Fetch complete Leaderboard for block instance.
     *
     * @param int $blockinstanceid The instance ID.
     * @param int $courseid The course ID.
     * @param int $currentuserid Current user ID.
     * @param bool $isteacher Is user teacher?
     * @return array
     */
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

        // 1. [NOVO] Mapa de Grupos (Performance: 1 Query para todo o curso)
        // Busca todos os vínculos de grupo deste curso de uma vez.
        $user_groups_map = [];
        $sql_groups = "SELECT gm.userid, g.name
                         FROM {groups} g
                         JOIN {groups_members} gm ON g.id = gm.groupid
                        WHERE g.courseid = :courseid";
        
        $memberships = $DB->get_recordset_sql($sql_groups, ['courseid' => $courseid]);
        foreach ($memberships as $rec) {
            $user_groups_map[$rec->userid][] = format_string($rec->name);
        }
        $memberships->close();

        // 2. Busca Usuários (Lógica Padrão)
        $userfieldsapi = \core_user\fields::for_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT $userfields, u.id as userid,
                       pu.currentxp, pu.ranking_visibility, pu.enable_gamification
                  FROM {block_playerhud_user} pu
                  JOIN {user} u ON pu.userid = u.id
                 WHERE pu.blockinstanceid = :pid
              ORDER BY pu.currentxp DESC, u.lastname ASC";

        $rawusers = $DB->get_records_sql($sql, ['pid' => $blockinstanceid]);

        $individualranking = [];
        $rankcounter = 1;
        $coursecontext = \context_course::instance($courseid);

        foreach ($rawusers as $usr) {
            $isme = ($usr->userid == $currentuserid);

            if (has_capability('block/playerhud:manage', $coursecontext, $usr->userid)) {
                continue;
            }

            // Status
            $ispaused = ($usr->enable_gamification == 0);
            $ishidden = ($usr->ranking_visibility == 0);
            $iscompetitor = (!$ispaused && !$ishidden);
            
            // Filtro de Visualização
            $shoulddisplay = ($iscompetitor || $isteacher || $isme);
            if (!$shoulddisplay) {
                continue;
            }

            // Cálculo do Rank
            $usr->rank = '-';
            $usr->medal_emoji = null;

            if ($iscompetitor) {
                $usr->rank = $rankcounter++;
                if ($usr->rank == 1) $usr->medal_emoji = '🥇';
                else if ($usr->rank == 2) $usr->medal_emoji = '🥈';
                else if ($usr->rank == 3) $usr->medal_emoji = '🥉';
            }

            // [NOVO] Atribui o nome do grupo ao objeto do usuário
            $my_groups = isset($user_groups_map[$usr->userid]) ? $user_groups_map[$usr->userid] : [];
            $usr->group_name = empty($my_groups) ? '-' : implode(', ', $my_groups);

            // Flags visuais
            $usr->is_me = $isme;
            $usr->is_paused = $ispaused;
            $usr->is_hidden_marker = ($ishidden && !$ispaused);
            $usr->fullname = fullname($usr);

            $individualranking[] = $usr;
        }

        // ... (Lógica de Grupos abaixo continua inalterada)
        $groupranking = [];
        $groups = groups_get_all_groups($courseid);

        if ($groups) {
            foreach ($groups as $grp) {
                $members = groups_get_members($grp->id, 'u.id');
                if (!$members) continue;

                $memberids = array_keys($members);
                list($insql, $inparams) = $DB->get_in_or_equal($memberids);

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
            usort($groupranking, function ($a, $b) { return $b->average_xp <=> $a->average_xp; });
            $grank = 1;
            foreach ($groupranking as &$g) {
                $g->rank = $grank++;
                $g->medal_emoji = ($g->rank == 1) ? '🏆' : null;
            }
        }

        return ['individual' => $individualranking, 'groups' => $groupranking];
    }
}
