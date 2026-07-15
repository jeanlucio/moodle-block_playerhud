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
     * Sum the XP a student can actually earn from this instance's own items and quests.
     *
     * Only counts XP that is genuinely paid out through a designed acquisition channel: a
     * finite drop's maxusage (paid on map collection), or a quest's own reward_xp (paid on
     * claim). An item with no drop contributes nothing here even if it has its own XP value
     * configured, because none of its other delivery paths actually pay that value — a quest's
     * item reward and a trade's item reward both hand over the item as a plain collectible
     * without touching the student's XP balance, and a teacher's manual grant is a deliberate
     * correction tool, not a designed acquisition channel, so it must not inflate this ceiling
     * either (see block_playerhud\controller\items::grant_item()).
     *
     * The breakdown only lists items and quests that actually contribute XP (xp_total > 0):
     * a zero-XP item, or one only reachable through an infinite drop (anti-farm rule), has
     * nothing for a teacher to tune here, so it is left out rather than shown as a "+0 XP" row.
     *
     * @param int $instanceid The block instance ID.
     * @return array {total_xp: int, breakdown: array}.
     */
    public static function game_xp_totals(int $instanceid): array {
        global $DB;

        $totalxp = 0;
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
                if (empty($dropsbyitem[$item->id])) {
                    continue;
                }

                $itemxp = 0;
                $totaldropuses = 0;
                $hasinfinite = false;

                foreach ($dropsbyitem[$item->id] as $drop) {
                    if ($drop->maxusage > 0) {
                        $itemxp += ($item->xp * $drop->maxusage);
                        $totaldropuses += $drop->maxusage;
                    } else {
                        $hasinfinite = true;
                    }
                }

                if ($itemxp == 0) {
                    // Zero-XP item (own xp = 0, or only reachable through an infinite drop):
                    // it never contributes real XP, so it has nothing for a teacher to tune here.
                    continue;
                }

                $totalxp += $itemxp;
                $breakdown[] = [
                    'name' => $item->name,
                    'xp_each' => $item->xp,
                    'drop_count' => count($dropsbyitem[$item->id]),
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
            $totalxp += (int)$quest->reward_xp;
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

        // Add XP potentially granted by external game plugins (e.g. mod_playerwords), each
        // discovered automatically via the playerhud_grant_potential callback — same
        // get_plugins_with_function() discovery pattern already used for navigation callbacks.
        // A plugin implements <frankenstyle>_playerhud_grant_potential(int $blockinstanceid):
        // array in its own lib.php, returning zero or more rows already shaped exactly like the
        // item/quest breakdown entries above ({name, xp_each, drop_count, total_uses, xp_total,
        // is_quest, infinite}), so no template change is needed here to display them. The
        // plugin's own implementation is responsible for validating that any item it references
        // actually belongs to $blockinstanceid (see \block_playerhud\local\external_items) —
        // this method only sums whatever rows it is handed back.
        //
        // Both the discovery scan and each plugin's own callback run third-party code this
        // class does not control, so both are guarded: a broken lib.php or a buggy callback in
        // one external plugin must not take down this instance's own XP report, and must not be
        // mistaken for a bug in this plugin.
        try {
            $granters = get_plugins_with_function('playerhud_grant_potential', 'lib.php');
        } catch (\Throwable $e) {
            debugging('block_playerhud: playerhud_grant_potential discovery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $granters = [];
        }

        foreach ($granters as $plugins) {
            foreach ($plugins as $component => $pluginfunction) {
                try {
                    foreach ($pluginfunction($instanceid) as $row) {
                        $totalxp += (int)($row['xp_total'] ?? 0);
                        $breakdown[] = $row;
                    }
                } catch (\Throwable $e) {
                    debugging(
                        'block_playerhud: playerhud_grant_potential in ' . $component . ' failed: ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            }
        }

        return ['total_xp' => $totalxp, 'breakdown' => $breakdown];
    }

    /**
     * Compute the economy health of a block instance.
     *
     * Compares the XP a student can earn (see game_xp_totals()) against the XP ceiling
     * (XP per level times the number of levels).
     *
     * @param int $instanceid The block instance ID.
     * @param int $xpperlevel XP required for each level.
     * @param int $maxlevels Number of levels configured.
     * @return \stdClass {total_items_xp, xp_ceiling, ratio, status, breakdown}.
     */
    public static function economy_health(int $instanceid, int $xpperlevel, int $maxlevels): \stdClass {
        $xpceiling = $xpperlevel * $maxlevels;
        $totals = self::game_xp_totals($instanceid);
        $totalitemsxp = $totals['total_xp'];
        $breakdown = $totals['breakdown'];

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
        $xpceiling = $xpperlevel * $maxlevels;
        $currenttotalxp = self::game_xp_totals($instanceid)['total_xp'];

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
