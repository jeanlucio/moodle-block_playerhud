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
 * Quest logic class for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

/**
 * Class quest
 *
 * Handles quest status checking and reward claiming.
 *
 * @package    block_playerhud
 */
class quest {
    /** @var int Quest type: Reach a specific level. */
    const TYPE_LEVEL = 1;

    /** @var int Quest type: Accumulate total XP. */
    const TYPE_XP_TOTAL = 2;

    /** @var int Quest type: Collect N unique items. */
    const TYPE_UNIQUE_ITEMS = 3;

    /** @var int Quest type: Collect N specific items. */
    const TYPE_SPECIFIC_ITEM = 4;

    /** @var int Quest type: Complete a Moodle activity. */
    const TYPE_ACTIVITY = 5;

    /** @var int Quest type: Collect N total items (including duplicates). */
    const TYPE_TOTAL_ITEMS = 6;

    /** @var int Quest type: Perform N trades in the shop. */
    const TYPE_TRADES = 7;

    /** @var int Quest type: Perform a specific trade N times. */
    const TYPE_SPECIFIC_TRADE = 8;

    /** @var int Quest type: Complete a specific story chapter. */
    const TYPE_CHAPTER = 9;

    /**
     * Checks the status of a quest for a specific user.
     *
     * @param object $quest The quest object.
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $currentxp The user's current XP.
     * @param int $currentlevel The user's current level.
     * @return \stdClass Status object {completed, progress, label, action_url, is_activity}.
     */
    public static function check_status($quest, $userid, $courseid, $currentxp, $currentlevel) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $status = new \stdClass();
        $status->completed = false;
        $status->progress = 0;
        $status->label = "";
        $status->action_url = null;
        $status->is_activity = false;
        $status->hidden = false;

        switch ($quest->type) {
            case self::TYPE_LEVEL:
                $target = (int)$quest->requirement;
                $current = $currentlevel;
                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_XP_TOTAL:
                $target = (int)$quest->requirement;
                $current = $currentxp;
                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target} XP";
                break;

            case self::TYPE_UNIQUE_ITEMS:
                // Refactored to count only items from this specific block instance.
                $sql = "SELECT COUNT(DISTINCT inv.itemid)
                          FROM {block_playerhud_inventory} inv
                          JOIN {block_playerhud_items} it ON inv.itemid = it.id
                         WHERE inv.userid = ? AND it.blockinstanceid = ?";
                $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_SPECIFIC_ITEM:
                $target = (int)$quest->requirement;
                $itemid = (int)$quest->req_itemid;
                // Item ID is unique, but belongs to an instance, so counting is safe within context.
                $current = $DB->count_records('block_playerhud_inventory', ['userid' => $userid, 'itemid' => $itemid]);

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_TOTAL_ITEMS:
                $sql = "SELECT COUNT(inv.id)
                          FROM {block_playerhud_inventory} inv
                          JOIN {block_playerhud_items} it ON inv.itemid = it.id
                         WHERE inv.userid = ? AND it.blockinstanceid = ? AND inv.source != 'revoked'";
                $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_TRADES:
                $sql = "SELECT COUNT(tl.id)
                          FROM {block_playerhud_trade_log} tl
                          JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                         WHERE tl.userid = ? AND t.blockinstanceid = ?";
                $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_SPECIFIC_TRADE:
                $target = (int)$quest->requirement;
                $tradeid = (int)$quest->req_itemid;

                $current = $DB->count_records('block_playerhud_trade_log', ['userid' => $userid, 'tradeid' => $tradeid]);

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_ACTIVITY:
                $status->is_activity = true;
                $cmid = (int)$quest->requirement;

                $modinfo = get_fast_modinfo($courseid);

                // Defensive coding: Check if CM exists before getting it to avoid fatal errors.
                if (!isset($modinfo->cms[$cmid])) {
                    $status->label = get_string('quest_status_removed', 'block_playerhud') . " (ID: $cmid)";
                    $status->completed = false;
                    $status->progress = 0;
                    return $status;
                }

                $cm = $modinfo->get_cm($cmid);

                if ($cm) {
                    // Hide quest entirely if the activity is not visible to this user.
                    if (!$cm->uservisible) {
                        $status->hidden = true;
                        return $status;
                    }

                    $status->action_url = $cm->url;

                    $completion = new \completion_info($modinfo->get_course());
                    $completiondata = $completion->get_data($cm, false, $userid);

                    if (
                        $completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS
                    ) {
                        $status->completed = true;
                        $status->progress = 100;
                        $status->label = get_string('quest_status_completed', 'block_playerhud');
                    } else {
                        $status->completed = false;
                        $status->progress = 0;
                        $status->label = get_string('quest_status_pending', 'block_playerhud');
                    }
                } else {
                    $status->label = get_string('quest_status_removed', 'block_playerhud');
                }
                break;

            case self::TYPE_CHAPTER:
                $chapterid = (int)$quest->requirement;
                $chapjson = $DB->get_field(
                    'block_playerhud_rpg_progress',
                    'completed_chapters',
                    ['blockinstanceid' => $quest->blockinstanceid, 'userid' => $userid]
                );
                $donechapters = ($chapjson) ? json_decode($chapjson, true) : [];
                if (!is_array($donechapters)) {
                    $donechapters = [];
                }
                $status->completed = in_array($chapterid, $donechapters);
                $status->progress  = $status->completed ? 100 : 0;
                $status->label     = $status->completed
                    ? get_string('quest_status_completed', 'block_playerhud')
                    : get_string('quest_status_pending', 'block_playerhud');
                break;
        }

        return $status;
    }

    /**
     * Claims the quest reward.
     *
     * @param int $questid The quest ID.
     * @param int $userid The user ID.
     * @param int $blockinstanceid The block instance ID.
     * @param int $courseid The course ID (required for activity checks).
     * @return string A description of the rewards claimed.
     * @throws \moodle_exception
     */
    public static function claim_reward($questid, $userid, $blockinstanceid, $courseid) {
        global $DB;

        // 1. Basic Validation.
        $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $blockinstanceid]);
        if (!$quest || !$quest->enabled) {
            throw new \moodle_exception('error_quest_invalid', 'block_playerhud');
        }

        // 2. Check if already claimed.
        if ($DB->record_exists('block_playerhud_quest_log', ['questid' => $questid, 'userid' => $userid])) {
            throw new \moodle_exception('error_quest_already_claimed', 'block_playerhud');
        }

        // 3. Re-verify requirements (Anti-cheat mechanism).
        $player = \block_playerhud\game::get_player($blockinstanceid, $userid);

        // Retrieve block configuration to calculate stats correctly.
        $blockinstance = $DB->get_record('block_instances', ['id' => $blockinstanceid]);
        $config = unserialize_object(base64_decode($blockinstance->configdata));
        if (!$config) {
            $config = new \stdClass(); // Fallback to defaults.
        }

        $stats = \block_playerhud\game::get_game_stats($config, $blockinstanceid, $player->currentxp);

        $check = self::check_status(
            $quest,
            $userid,
            $courseid,
            $player->currentxp,
            $stats['level']
        );

        if (!$check->completed) {
            throw new \moodle_exception('error_quest_requirements', 'block_playerhud');
        }

        // 4. Deliver Rewards (Transaction start).
        $transaction = $DB->start_delegated_transaction();
        try {
            // Log completion.
            $log = new \stdClass();
            $log->questid = $questid;
            $log->userid = $userid;
            $log->timecreated = time();
            $DB->insert_record('block_playerhud_quest_log', $log);

            $rewardstxt = [];

            // XP Reward.
            if ($quest->reward_xp > 0) {
                $player->currentxp += $quest->reward_xp;
                // Correction: Update timestamp for tie-breaking.
                $player->timemodified = time();
                $DB->update_record('block_playerhud_user', $player);

                $rewardstxt[] = "+{$quest->reward_xp} XP";
            }

            // Item Reward.
            if ($quest->reward_itemid > 0) {
                $item = $DB->get_record('block_playerhud_items', ['id' => $quest->reward_itemid]);
                if ($item) {
                    $inv = new \stdClass();
                    $inv->userid = $userid;
                    $inv->itemid = $item->id;
                    $inv->dropid = 0; // 0 indicates reward from Quest.
                    $inv->timecreated = time();
                    $inv->source = 'quest';
                    $DB->insert_record('block_playerhud_inventory', $inv);
                    $rewardstxt[] = format_string($item->name);
                }
            }

            $transaction->allow_commit();
            $separator = get_string('connector_and', 'block_playerhud');
            return implode($separator, $rewardstxt);
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Checks if the user has at least one completed-but-unclaimed quest.
     *
     * Optimized for sidebar use: lazy-loads DB counts only once per type,
     * and short-circuits as soon as a claimable quest is found.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $currentxp User's current XP.
     * @param int $currentlevel User's current level.
     * @return bool True if at least one reward is waiting to be claimed.
     */
    public static function has_claimable_quests(
        int $instanceid,
        int $userid,
        int $courseid,
        int $currentxp,
        int $currentlevel
    ): bool {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
        if (empty($quests)) {
            return false;
        }

        // Bulk-load claimed quest IDs for this user into a lookup map.
        $claimedids = $DB->get_fieldset_select('block_playerhud_quest_log', 'questid', 'userid = ?', [$userid]);
        $claimed = array_flip($claimedids);

        $unclaimed = array_filter($quests, static fn($q) => !isset($claimed[$q->id]));
        if (empty($unclaimed)) {
            return false;
        }

        // Lazy-loaded counters — each is fetched at most once regardless of quest count.
        $uniqueitems = null;
        $totalitems = null;
        $tradecount = null;
        $specificitemcnt = [];
        $specifictradecnt = [];
        $modinfo = null;
        $completedchapters = null;

        foreach ($unclaimed as $q) {
            $completed = false;

            switch ($q->type) {
                case self::TYPE_LEVEL:
                    $completed = ($currentlevel >= (int)$q->requirement);
                    break;

                case self::TYPE_XP_TOTAL:
                    $completed = ($currentxp >= (int)$q->requirement);
                    break;

                case self::TYPE_UNIQUE_ITEMS:
                    if ($uniqueitems === null) {
                        $sql = "SELECT COUNT(DISTINCT inv.itemid)
                                  FROM {block_playerhud_inventory} inv
                                  JOIN {block_playerhud_items} it ON inv.itemid = it.id
                                 WHERE inv.userid = ? AND it.blockinstanceid = ?";
                        $uniqueitems = (int)$DB->count_records_sql($sql, [$userid, $instanceid]);
                    }
                    $completed = ($uniqueitems >= (int)$q->requirement);
                    break;

                case self::TYPE_TOTAL_ITEMS:
                    if ($totalitems === null) {
                        $sql = "SELECT COUNT(inv.id)
                                  FROM {block_playerhud_inventory} inv
                                  JOIN {block_playerhud_items} it ON inv.itemid = it.id
                                 WHERE inv.userid = ? AND it.blockinstanceid = ? AND inv.source != 'revoked'";
                        $totalitems = (int)$DB->count_records_sql($sql, [$userid, $instanceid]);
                    }
                    $completed = ($totalitems >= (int)$q->requirement);
                    break;

                case self::TYPE_SPECIFIC_ITEM:
                    $itemid = (int)$q->req_itemid;
                    if (!isset($specificitemcnt[$itemid])) {
                        $specificitemcnt[$itemid] = (int)$DB->count_records(
                            'block_playerhud_inventory',
                            ['userid' => $userid, 'itemid' => $itemid]
                        );
                    }
                    $completed = ($specificitemcnt[$itemid] >= (int)$q->requirement);
                    break;

                case self::TYPE_TRADES:
                    if ($tradecount === null) {
                        $sql = "SELECT COUNT(tl.id)
                                  FROM {block_playerhud_trade_log} tl
                                  JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                                 WHERE tl.userid = ? AND t.blockinstanceid = ?";
                        $tradecount = (int)$DB->count_records_sql($sql, [$userid, $instanceid]);
                    }
                    $completed = ($tradecount >= (int)$q->requirement);
                    break;

                case self::TYPE_SPECIFIC_TRADE:
                    $tradeid = (int)$q->req_itemid;
                    if (!isset($specifictradecnt[$tradeid])) {
                        $specifictradecnt[$tradeid] = (int)$DB->count_records(
                            'block_playerhud_trade_log',
                            ['userid' => $userid, 'tradeid' => $tradeid]
                        );
                    }
                    $completed = ($specifictradecnt[$tradeid] >= (int)$q->requirement);
                    break;

                case self::TYPE_ACTIVITY:
                    $cmid = (int)$q->requirement;
                    if ($modinfo === null) {
                        $modinfo = get_fast_modinfo($courseid);
                    }
                    if (!isset($modinfo->cms[$cmid])) {
                        break;
                    }
                    $cm = $modinfo->get_cm($cmid);
                    if (!$cm || !$cm->uservisible) {
                        break;
                    }
                    $completion = new \completion_info($modinfo->get_course());
                    $completiondata = $completion->get_data($cm, false, $userid);
                    $completed = in_array(
                        $completiondata->completionstate,
                        [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]
                    );
                    break;

                case self::TYPE_CHAPTER:
                    if ($completedchapters === null) {
                        $chapjson = $DB->get_field(
                            'block_playerhud_rpg_progress',
                            'completed_chapters',
                            ['blockinstanceid' => $instanceid, 'userid' => $userid]
                        );
                        $completedchapters = $chapjson ? json_decode($chapjson, true) : [];
                        if (!is_array($completedchapters)) {
                            $completedchapters = [];
                        }
                    }
                    $completed = in_array((int)$q->requirement, $completedchapters);
                    break;
            }

            if ($completed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates heuristic quest suggestions based on course mapping.
     * Guaranteed Zero N+1 Queries.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param \stdClass $config Block configuration.
     * @return array Array of suggested quests.
     */
    public static function get_heuristic_suggestions(int $instanceid, int $courseid, \stdClass $config): array {
        global $DB;
        $suggestions = [];

        // Preload existing quests to avoid suggesting duplicates.
        $existing = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid], '', 'id, type, requirement');
        $hasquest = function ($type, $req) use ($existing) {
            foreach ($existing as $q) {
                if ($q->type == $type && $q->requirement == (string)$req) {
                    return true;
                }
            }
            return false;
        };

        // 1. Activity Mapping (Fast Modinfo uses Moodle's internal fast cache).
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->visible && $cm->completion > 0) {
                if (!$hasquest(self::TYPE_ACTIVITY, $cm->id)) {
                    $suggestions[] = [
                        'type' => self::TYPE_ACTIVITY,
                        'requirement' => $cm->id,
                        'name' => get_string('quest_sug_activity', 'block_playerhud', format_string($cm->name)),
                        'reward_xp' => 50,
                        'image_todo' => '📋',
                        'image_done' => '🏅',
                        'uid' => 'act_' . $cm->id,
                    ];
                }
            }
        }

        // 2. Level Milestones (25%, 50%, 75% of Max Level).
        // The max level is intentionally excluded: its XP reward has no progression value, and
        // if the reward is the only way to reach that level it creates an unreachable deadlock.
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $levelsteps = [
            (int)ceil($maxlevels * 0.25),
            (int)ceil($maxlevels * 0.50),
            (int)ceil($maxlevels * 0.75),
        ];
        $levelsteps = array_unique(array_filter($levelsteps, function ($v) {
            return $v > 1;
        }));

        foreach ($levelsteps as $lvl) {
            if (!$hasquest(self::TYPE_LEVEL, $lvl)) {
                $suggestions[] = [
                    'type' => self::TYPE_LEVEL,
                    'requirement' => $lvl,
                    'name' => get_string('quest_sug_level', 'block_playerhud', $lvl),
                    'reward_xp' => $lvl * 20,
                    'image_todo' => '📈',
                    'image_done' => '👑',
                    'uid' => 'lvl_' . $lvl,
                ];
            }
        }

        // 3. Collection Milestones.
        $totalitems = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
        if ($totalitems >= 2) {
            $itemsteps = [(int)ceil($totalitems * 0.5), $totalitems];
            $itemsteps = array_unique(array_filter($itemsteps, function ($v) {
                return $v > 0;
            }));
            foreach ($itemsteps as $itms) {
                if (!$hasquest(self::TYPE_UNIQUE_ITEMS, $itms)) {
                    $suggestions[] = [
                        'type' => self::TYPE_UNIQUE_ITEMS,
                        'requirement' => $itms,
                        'name' => get_string('quest_sug_items', 'block_playerhud', $itms),
                        'reward_xp' => $itms * 30,
                        'image_todo' => '🎒',
                        'image_done' => '🏆',
                        'uid' => 'col_' . $itms,
                    ];
                }
            }
        }

        // 4. Economy Milestones.
        $totaltrades = $DB->count_records('block_playerhud_trades', ['blockinstanceid' => $instanceid]);
        if ($totaltrades > 0) {
            $unlimitedtrades = $DB->count_records(
                'block_playerhud_trades',
                ['blockinstanceid' => $instanceid, 'onetime' => 0]
            );
            if ($unlimitedtrades > 0) {
                $tradesteps = [1, 5, 10];
            } else {
                $tradesteps = array_filter([1, 5, 10], fn($s) => $s <= $totaltrades);
            }
            foreach ($tradesteps as $trds) {
                if (!$hasquest(self::TYPE_TRADES, $trds)) {
                    $suggestions[] = [
                        'type' => self::TYPE_TRADES,
                        'requirement' => $trds,
                        'name' => get_string('quest_sug_trades', 'block_playerhud', $trds),
                        'reward_xp' => $trds * 40,
                        'image_todo' => '⚖️',
                        'image_done' => '🤝',
                        'uid' => 'trd_' . $trds,
                    ];
                }
            }
        }

        return $suggestions;
    }
}
