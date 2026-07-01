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
 * Reporting and balance analytics for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Pure analytics calculations shared by the management renderers.
 *
 * Extracted from the Config and Reports tabs so the business rules
 * (economy balance, level histogram) can be unit tested independently
 * of the presentation layer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analytics {
    /**
     * Compute the economy health of a block instance.
     *
     * Sums the XP a student can earn (enabled items times their drop usage,
     * plus enabled quest rewards) and compares it against the XP ceiling
     * (XP per level times the number of levels).
     *
     * @param int $instanceid The block instance ID.
     * @param int $xpperlevel XP required for each level.
     * @param int $maxlevels Number of levels configured.
     * @return \stdClass {total_items_xp, xp_ceiling, ratio, status, breakdown}.
     */
    public static function economy_health(int $instanceid, int $xpperlevel, int $maxlevels): \stdClass {
        global $DB;

        $xpceiling = $xpperlevel * $maxlevels;
        $totalitemsxp = 0;
        $breakdown = [];

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
        if ($items) {
            // Preload all drops for this instance to avoid an N+1 query problem.
            $sql = "SELECT d.id, d.itemid, d.maxusage
                      FROM {block_playerhud_drops} d
                      JOIN {block_playerhud_items} i ON d.itemid = i.id
                     WHERE i.blockinstanceid = :instanceid AND i.enabled = 1";
            $alldrops = $DB->get_records_sql($sql, ['instanceid' => $instanceid]);

            $dropsbyitem = [];
            foreach ($alldrops as $drop) {
                $dropsbyitem[$drop->itemid][] = $drop;
            }

            foreach ($items as $item) {
                $itemxp = 0;
                $dropcount = 0;
                $totaldropuses = 0;
                $hasinfinite = false;

                if (!empty($dropsbyitem[$item->id])) {
                    $dropcount = count($dropsbyitem[$item->id]);
                    foreach ($dropsbyitem[$item->id] as $drop) {
                        if ($drop->maxusage > 0) {
                            $itemxp += ($item->xp * $drop->maxusage);
                            $totaldropuses += $drop->maxusage;
                        } else {
                            $hasinfinite = true;
                        }
                    }
                } else {
                    // Item without a drop still contributes 1x its XP (available in library).
                    $itemxp = $item->xp;
                    $dropcount = 0;
                    $totaldropuses = 1;
                }

                $totalitemsxp += $itemxp;
                $breakdown[] = [
                    'name' => $item->name,
                    'xp_each' => $item->xp,
                    'drop_count' => $dropcount,
                    'total_uses' => $hasinfinite ? '∞' : $totaldropuses,
                    'xp_total' => $itemxp,
                    'is_quest' => false,
                    'infinite' => $hasinfinite,
                ];
            }
        }

        // Add XP from enabled quest rewards.
        $quests = $DB->get_records_select(
            'block_playerhud_quests',
            'blockinstanceid = :instanceid AND enabled = 1 AND reward_xp > 0',
            ['instanceid' => $instanceid],
            '',
            'id, name, reward_xp'
        );
        foreach ($quests as $quest) {
            $totalitemsxp += (int)$quest->reward_xp;
            $breakdown[] = [
                'name' => $quest->name,
                'xp_each' => $quest->reward_xp,
                'drop_count' => 0,
                'total_uses' => 1,
                'xp_total' => $quest->reward_xp,
                'is_quest' => true,
                'infinite' => false,
            ];
        }

        $ratio = ($xpceiling > 0) ? ($totalitemsxp / $xpceiling) * 100 : 0;

        if ($totalitemsxp == 0) {
            $status = 'empty';
        } else if ($ratio < 100) {
            $status = 'hard';
        } else if ($ratio > 100) {
            $status = 'easy';
        } else {
            $status = 'perfect';
        }

        return (object) [
            'total_items_xp' => $totalitemsxp,
            'xp_ceiling' => $xpceiling,
            'ratio' => $ratio,
            'status' => $status,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Computes the XP balance context used to steer AI generation difficulty.
     *
     * Shared by every entry point that asks the AI generator for new items: it tells
     * the generator how far the current item pool is from the configured XP ceiling,
     * so prompts can lean towards common or rare items accordingly.
     *
     * @param int $instanceid The block instance ID.
     * @param int $xpperlevel XP required for each level.
     * @param int $maxlevels Number of levels configured.
     * @param int $qty Number of elements about to be generated.
     * @return array {current_xp, target_xp, gap, qty}.
     */
    public static function balance_context(int $instanceid, int $xpperlevel, int $maxlevels, int $qty): array {
        global $DB;

        $xpceiling = $xpperlevel * $maxlevels;
        $currenttotalxp = 0;

        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
        if ($items) {
            // Preload all drops for this instance to avoid an N+1 query problem.
            $sql = "SELECT d.id, d.itemid, d.maxusage
                      FROM {block_playerhud_drops} d
                      JOIN {block_playerhud_items} i ON d.itemid = i.id
                     WHERE i.blockinstanceid = :instanceid AND i.enabled = 1";
            $alldrops = $DB->get_records_sql($sql, ['instanceid' => $instanceid]);

            $dropsbyitem = [];
            foreach ($alldrops as $drop) {
                $dropsbyitem[$drop->itemid][] = $drop;
            }

            foreach ($items as $item) {
                if (!empty($dropsbyitem[$item->id])) {
                    foreach ($dropsbyitem[$item->id] as $drop) {
                        if ($drop->maxusage > 0) {
                            $currenttotalxp += ($item->xp * $drop->maxusage);
                        }
                    }
                } else {
                    // Item without a drop still contributes 1x its XP (available in library).
                    $currenttotalxp += $item->xp;
                }
            }
        }

        return [
            'current_xp' => $currenttotalxp,
            'target_xp' => $xpceiling,
            'gap' => $xpceiling - $currenttotalxp,
            'qty' => $qty,
        ];
    }

    /**
     * Build the level-distribution histogram from a list of XP values.
     *
     * Players above the cap are grouped into a single "maxlevel+" bucket.
     * Each bucket carries its share of the tallest bar as a percentage.
     *
     * @param int[] $xpvalues The current XP of each player.
     * @param int $xpperlevel XP required for each level.
     * @param int $maxlevel The level cap (overflow bucket label).
     * @return array List of {label, total, percent} ordered by level.
     */
    public static function level_distribution(array $xpvalues, int $xpperlevel, int $maxlevel): array {
        $levelscount = [];
        $maxbarvalue = 0;

        foreach ($xpvalues as $xp) {
            $rawlevel = ($xpperlevel > 0) ? (int) floor($xp / $xpperlevel) + 1 : 1;
            $key = ($rawlevel > $maxlevel) ? $maxlevel . '+' : (int)$rawlevel;

            if (!isset($levelscount[$key])) {
                $levelscount[$key] = 0;
            }
            $levelscount[$key]++;

            if ($levelscount[$key] > $maxbarvalue) {
                $maxbarvalue = $levelscount[$key];
            }
        }

        uksort($levelscount, function ($a, $b) {
            $vala = (int)str_replace('+', '', (string)$a);
            $valb = (int)str_replace('+', '', (string)$b);
            if ($vala == $valb) {
                return (strpos((string)$a, '+') !== false) ? 1 : -1;
            }
            return ($vala < $valb) ? -1 : 1;
        });

        $levelsdata = [];
        foreach ($levelscount as $lvllabel => $total) {
            $levelsdata[] = [
                'label'   => (string)$lvllabel,
                'total'   => $total,
                'percent' => ($maxbarvalue > 0) ? ($total / $maxbarvalue) * 100 : 0,
            ];
        }

        return $levelsdata;
    }
}
