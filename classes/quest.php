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
     * Build a quest DB record from a suggestion descriptor.
     *
     * Maps a suggestion — either produced by get_heuristic_suggestions() or built directly by
     * a caller (e.g. a wizard module creating its own bespoke quest) — to a quest record ready
     * for insertion. Does not touch the database, so callers can collect several records and
     * persist them in a single batch insert. Allows an optional XP reward override so callers
     * can scale rewards (e.g. per gamification profile).
     *
     * @param int $instanceid Block instance ID.
     * @param array $sug Suggestion descriptor (type, requirement, name, reward_xp, image_todo,
     *        image_done, and optionally req_itemid for TYPE_SPECIFIC_ITEM/TYPE_SPECIFIC_TRADE,
     *        and/or reward_itemid to grant an item alongside the XP reward).
     * @param int|null $rewardxpoverride Optional XP reward to use instead of the suggestion's value.
     * @return \stdClass Quest record ready for insert_record/insert_records.
     */
    public static function build_record_from_suggestion(int $instanceid, array $sug, ?int $rewardxpoverride = null): \stdClass {
        $now = time();
        $record = new \stdClass();
        $record->blockinstanceid   = $instanceid;
        $record->name              = $sug['name'];
        $record->description       = '';
        $record->type              = $sug['type'];
        $record->requirement       = (string)$sug['requirement'];
        $record->req_itemid        = (int)($sug['req_itemid'] ?? 0);
        $record->reward_xp         = $rewardxpoverride !== null ? max(0, $rewardxpoverride) : (int)$sug['reward_xp'];
        $record->reward_itemid     = (int)($sug['reward_itemid'] ?? 0);
        $record->required_class_id = '0';
        $record->image_todo        = $sug['image_todo'];
        $record->image_done        = $sug['image_done'];
        $record->enabled           = 1;
        $record->timecreated       = $now;
        $record->timemodified      = $now;

        return $record;
    }

    /**
     * Precomputes the per-user aggregates check_status() needs, for every quest in a list at
     * once — unique/total item counts, trade count and completed chapters are the same value
     * regardless of which quest asks for them, and TYPE_SPECIFIC_ITEM/TYPE_SPECIFIC_TRADE only
     * vary by itemid/tradeid, so both are cheap to batch with one grouped query. Each aggregate
     * is computed only when at least one quest in the list actually needs it. Pass the result as
     * check_status()'s optional last argument when checking many quests in a loop (e.g. the
     * student Quests tab) to turn what would be one query per quest into a handful of queries
     * total, regardless of quest count.
     *
     * @param int $userid The user ID.
     * @param int $blockinstanceid The block instance ID.
     * @param \stdClass[] $quests Every quest that will be passed to check_status() afterwards.
     * @return array Totals structure, passed as check_status()'s optional last argument.
     */
    public static function preload_totals(int $userid, int $blockinstanceid, array $quests): array {
        global $DB;

        $totals = [
            'unique_items' => null,
            'total_items' => null,
            'trades' => null,
            'done_chapters' => null,
            'specific_items' => [],
            'specific_trades' => [],
        ];

        $needuniqueitems = false;
        $needtotalitems = false;
        $needtrades = false;
        $needchapters = false;
        $specificitemids = [];
        $specifictradeids = [];

        foreach ($quests as $quest) {
            switch ((int) $quest->type) {
                case self::TYPE_UNIQUE_ITEMS:
                    $needuniqueitems = true;
                    break;
                case self::TYPE_TOTAL_ITEMS:
                    $needtotalitems = true;
                    break;
                case self::TYPE_TRADES:
                    $needtrades = true;
                    break;
                case self::TYPE_CHAPTER:
                    $needchapters = true;
                    break;
                case self::TYPE_SPECIFIC_ITEM:
                    $itemid = (int) $quest->req_itemid;
                    if ($itemid > 0) {
                        $specificitemids[$itemid] = $itemid;
                    }
                    break;
                case self::TYPE_SPECIFIC_TRADE:
                    $tradeid = (int) $quest->req_itemid;
                    if ($tradeid > 0) {
                        $specifictradeids[$tradeid] = $tradeid;
                    }
                    break;
            }
        }

        if ($needuniqueitems) {
            $totals['unique_items'] = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT inv.itemid)
                   FROM {block_playerhud_inventory} inv
                   JOIN {block_playerhud_items} it ON inv.itemid = it.id
                  WHERE inv.userid = ? AND it.blockinstanceid = ?",
                [$userid, $blockinstanceid]
            );
        }

        if ($needtotalitems) {
            $totals['total_items'] = (int) $DB->count_records_sql(
                "SELECT COUNT(inv.id)
                   FROM {block_playerhud_inventory} inv
                   JOIN {block_playerhud_items} it ON inv.itemid = it.id
                  WHERE inv.userid = ? AND it.blockinstanceid = ? AND inv.source NOT IN ('revoked', 'consumed')",
                [$userid, $blockinstanceid]
            );
        }

        if ($needtrades) {
            $totals['trades'] = (int) $DB->count_records_sql(
                "SELECT COUNT(tl.id)
                   FROM {block_playerhud_trade_log} tl
                   JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                  WHERE tl.userid = ? AND t.blockinstanceid = ?",
                [$userid, $blockinstanceid]
            );
        }

        if ($needchapters) {
            $chapjson = $DB->get_field(
                'block_playerhud_rpg_progress',
                'completed_chapters',
                ['blockinstanceid' => $blockinstanceid, 'userid' => $userid]
            );
            $donechapters = ($chapjson) ? json_decode($chapjson, true) : [];
            $totals['done_chapters'] = is_array($donechapters) ? $donechapters : [];
        }

        if (!empty($specificitemids)) {
            $totals['specific_items'] = self::count_specific_items($userid, array_values($specificitemids));
        }

        if (!empty($specifictradeids)) {
            $totals['specific_trades'] = self::count_specific_trades($userid, array_values($specifictradeids));
        }

        return $totals;
    }

    /**
     * Counts owned inventory rows per itemid, in one grouped query instead of one per item.
     *
     * @param int $userid The user ID.
     * @param int[] $itemids Distinct item IDs to count.
     * @return array Counts keyed by itemid.
     */
    private static function count_specific_items(int $userid, array $itemids): array {
        global $DB;

        if (empty($itemids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $inparams['userid'] = $userid;
        $rows = $DB->get_records_sql(
            "SELECT itemid, COUNT(*) AS cnt
               FROM {block_playerhud_inventory}
              WHERE userid = :userid AND itemid $insql
           GROUP BY itemid",
            $inparams
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->itemid] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Counts trade_log rows per tradeid, in one grouped query instead of one per trade.
     *
     * @param int $userid The user ID.
     * @param int[] $tradeids Distinct trade IDs to count.
     * @return array Counts keyed by tradeid.
     */
    private static function count_specific_trades(int $userid, array $tradeids): array {
        global $DB;

        if (empty($tradeids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($tradeids, SQL_PARAMS_NAMED);
        $inparams['userid'] = $userid;
        $rows = $DB->get_records_sql(
            "SELECT tradeid, COUNT(*) AS cnt
               FROM {block_playerhud_trade_log}
              WHERE userid = :userid AND tradeid $insql
           GROUP BY tradeid",
            $inparams
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->tradeid] = (int) $row->cnt;
        }
        return $counts;
    }

    /**
     * Checks the status of a quest for a specific user.
     *
     * @param \stdClass $quest The quest object.
     * @param int $userid The user ID.
     * @param int $courseid The course ID.
     * @param int $currentxp The user's current XP.
     * @param int $currentlevel The user's current level.
     * @param array|null $totals Precomputed aggregates from {@see preload_totals()}; null (the
     *                           default, used by claim_reward()'s single-quest check) always
     *                           queries.
     * @return \stdClass Status object {completed, progress, label, action_url, is_activity}.
     */
    public static function check_status(
        \stdClass $quest,
        int $userid,
        int $courseid,
        int $currentxp,
        int $currentlevel,
        ?array $totals = null
    ): \stdClass {
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
                if ($totals !== null && $totals['unique_items'] !== null) {
                    $current = $totals['unique_items'];
                } else {
                    // Refactored to count only items from this specific block instance.
                    $sql = "SELECT COUNT(DISTINCT inv.itemid)
                              FROM {block_playerhud_inventory} inv
                              JOIN {block_playerhud_items} it ON inv.itemid = it.id
                             WHERE inv.userid = ? AND it.blockinstanceid = ?";
                    $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                }
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_SPECIFIC_ITEM:
                $target = (int)$quest->requirement;
                $itemid = (int)$quest->req_itemid;

                if ($itemid <= 0) {
                    $status->label = get_string('quest_status_pending', 'block_playerhud');
                    break;
                }

                // Item ID is unique, but belongs to an instance, so counting is safe within context.
                if ($totals !== null) {
                    $current = $totals['specific_items'][$itemid] ?? 0;
                } else {
                    $current = $DB->count_records('block_playerhud_inventory', ['userid' => $userid, 'itemid' => $itemid]);
                }

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_TOTAL_ITEMS:
                if ($totals !== null && $totals['total_items'] !== null) {
                    $current = $totals['total_items'];
                } else {
                    $sql = "SELECT COUNT(inv.id)
                              FROM {block_playerhud_inventory} inv
                              JOIN {block_playerhud_items} it ON inv.itemid = it.id
                             WHERE inv.userid = ? AND it.blockinstanceid = ? AND inv.source NOT IN ('revoked', 'consumed')";
                    $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                }
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_TRADES:
                if ($totals !== null && $totals['trades'] !== null) {
                    $current = $totals['trades'];
                } else {
                    $sql = "SELECT COUNT(tl.id)
                              FROM {block_playerhud_trade_log} tl
                              JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                             WHERE tl.userid = ? AND t.blockinstanceid = ?";
                    $current = $DB->count_records_sql($sql, [$userid, $quest->blockinstanceid]);
                }
                $target = (int)$quest->requirement;

                $status->completed = ($current >= $target);
                $status->progress = ($target > 0) ? min(100, floor(($current / $target) * 100)) : 100;
                $status->label = "{$current} / {$target}";
                break;

            case self::TYPE_SPECIFIC_TRADE:
                $target = (int)$quest->requirement;
                $tradeid = (int)$quest->req_itemid;

                if ($tradeid <= 0) {
                    $status->label = get_string('quest_status_pending', 'block_playerhud');
                    break;
                }

                if ($totals !== null) {
                    $current = $totals['specific_trades'][$tradeid] ?? 0;
                } else {
                    $current = $DB->count_records('block_playerhud_trade_log', ['userid' => $userid, 'tradeid' => $tradeid]);
                }

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
                if ($totals !== null && $totals['done_chapters'] !== null) {
                    $donechapters = $totals['done_chapters'];
                } else {
                    $chapjson = $DB->get_field(
                        'block_playerhud_rpg_progress',
                        'completed_chapters',
                        ['blockinstanceid' => $quest->blockinstanceid, 'userid' => $userid]
                    );
                    $donechapters = ($chapjson) ? json_decode($chapjson, true) : [];
                    if (!is_array($donechapters)) {
                        $donechapters = [];
                    }
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
    public static function claim_reward(int $questid, int $userid, int $blockinstanceid, int $courseid): string {
        global $DB;

        // 1. Basic Validation.
        $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $blockinstanceid]);
        if (!$quest || !$quest->enabled) {
            throw new \moodle_exception('error_quest_invalid', 'block_playerhud');
        }

        // 2. Serialize concurrent claims for this user+quest, mirroring the lock
        // trade_manager::execute_trade() uses to prevent two simultaneous requests
        // from both passing the already-claimed check before either one writes.
        $lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
        $lockkey = 'quest_usr_' . $userid . '_q_' . $questid;
        $lock = $lockfactory->get_lock($lockkey, 10);

        if (!$lock) {
            throw new \moodle_exception('error_quest_lock', 'block_playerhud');
        }

        try {
            // 3. Check if already claimed.
            if ($DB->record_exists('block_playerhud_quest_log', ['questid' => $questid, 'userid' => $userid])) {
                throw new \moodle_exception('error_quest_already_claimed', 'block_playerhud');
            }

            // 4. Re-verify requirements (Anti-cheat mechanism).
            $player = \block_playerhud\game::get_player($blockinstanceid, $userid);

            $blockinstance = $DB->get_record('block_instances', ['id' => $blockinstanceid], '*', MUST_EXIST);
            $rawconfig = base64_decode($blockinstance->configdata ?? '', true);
            $config = ($rawconfig !== false && $rawconfig !== '') ? unserialize_object($rawconfig) : null;
            if (!$config || !is_object($config)) {
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

            // Snapshot before the reward is delivered, to detect celebrations afterwards.
            $oldlevel = (int)$stats['level'];
            $oldxp = (int)$player->currentxp;
            $gametotal = (int)$stats['total_game_xp'];

            // 5. Deliver Rewards (Transaction start).
            $transaction = $DB->start_delegated_transaction();
            try {
                // Log completion.
                $log = new \stdClass();
                $log->questid = $questid;
                $log->userid = $userid;
                $log->timecreated = time();
                $log->xpawarded = (int)$quest->reward_xp;
                $log->id = $DB->insert_record('block_playerhud_quest_log', $log);

                event\quest_collected::create([
                    'context' => \context_block::instance($blockinstanceid),
                    'objectid' => (int)$log->id,
                    'relateduserid' => (int)$userid,
                    'other' => ['questid' => (int)$questid, 'xp' => (int)$quest->reward_xp],
                ])->trigger();

                $rewardstxt = [];

                // XP Reward.
                if ($quest->reward_xp > 0) {
                    \block_playerhud\game::change_xp($player, (int)$quest->reward_xp, $blockinstanceid);
                    $rewardstxt[] = "+{$quest->reward_xp} XP";
                }

                // Item Reward. The item's own xp value is never paid here — only reward_xp
                // above pays real XP — so xpawarded is always 0 for a quest-granted item.
                if ($quest->reward_itemid > 0) {
                    $item = $DB->get_record('block_playerhud_items', ['id' => $quest->reward_itemid]);
                    if ($item) {
                        $inv = new \stdClass();
                        $inv->userid = $userid;
                        $inv->itemid = $item->id;
                        $inv->dropid = 0; // 0 indicates reward from Quest.
                        $inv->timecreated = time();
                        $inv->source = 'quest';
                        $inv->xpawarded = 0;
                        $DB->insert_record('block_playerhud_inventory', $inv);
                        $rewardstxt[] = format_string($item->name);
                    }
                }

                $transaction->allow_commit();

                // Pick a single celebration to flash on the page reloaded after the claim
                // redirect, by priority: beating the game (100%) > level-up > first quest
                // claimed. The first-quest milestone bit is only burned when it is the one
                // actually shown, so a claim that is overshadowed still shows it next time.
                $newxp = (int)$player->currentxp;
                $won = ($gametotal > 0 && $newxp >= $gametotal && $oldxp < $gametotal);
                $newlevel = \block_playerhud\game::xp_to_level(
                    $newxp,
                    (int)$stats['xp_per_level'],
                    (int)$stats['max_levels']
                );

                $celebration = '';
                if ($won) {
                    $celebration = 'win';
                } else if ($newlevel > $oldlevel) {
                    $celebration = 'levelup:' . $newlevel;
                }

                if ($celebration !== '') {
                    set_user_preference('block_playerhud_celebration', $celebration, $userid);
                }

                $separator = get_string('connector_and', 'block_playerhud');
                return implode($separator, $rewardstxt);
            } catch (\Exception $e) {
                $transaction->rollback($e);
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Checks if the user has at least one completed-but-unclaimed quest.
     *
     * Optimized for sidebar use: each aggregate is fetched at most once, lazily, on first actual
     * need — never for a type the loop short-circuits before reaching. TYPE_SPECIFIC_ITEM/
     * TYPE_SPECIFIC_TRADE still group every distinct id from the whole unclaimed list into one
     * query apiece the first time either type is reached, rather than one query per id.
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

        // Lazy-loaded counters — each is fetched at most once, only if actually needed before
        // a claimable quest short-circuits the loop.
        $uniqueitems = null;
        $totalitems = null;
        $tradecount = null;
        $specificitemcnt = null;
        $specifictradecnt = null;
        $completedchapters = null;
        $modinfo = null;

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
                                 WHERE inv.userid = ? AND it.blockinstanceid = ? AND inv.source NOT IN ('revoked', 'consumed')";
                        $totalitems = (int)$DB->count_records_sql($sql, [$userid, $instanceid]);
                    }
                    $completed = ($totalitems >= (int)$q->requirement);
                    break;

                case self::TYPE_SPECIFIC_ITEM:
                    $itemid = (int)$q->req_itemid;
                    if ($itemid <= 0) {
                        break;
                    }
                    if ($specificitemcnt === null) {
                        // First specific-item quest reached: group every distinct itemid from
                        // the whole unclaimed list into one query, not just this one.
                        $itemids = [];
                        foreach ($unclaimed as $uq) {
                            if ($uq->type == self::TYPE_SPECIFIC_ITEM && (int) $uq->req_itemid > 0) {
                                $itemids[(int) $uq->req_itemid] = (int) $uq->req_itemid;
                            }
                        }
                        $specificitemcnt = self::count_specific_items($userid, array_values($itemids));
                    }
                    $completed = (($specificitemcnt[$itemid] ?? 0) >= (int)$q->requirement);
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
                    if ($tradeid <= 0) {
                        break;
                    }
                    if ($specifictradecnt === null) {
                        // First specific-trade quest reached: group every distinct tradeid from
                        // the whole unclaimed list into one query, not just this one.
                        $tradeids = [];
                        foreach ($unclaimed as $uq) {
                            if ($uq->type == self::TYPE_SPECIFIC_TRADE && (int) $uq->req_itemid > 0) {
                                $tradeids[(int) $uq->req_itemid] = (int) $uq->req_itemid;
                            }
                        }
                        $specifictradecnt = self::count_specific_trades($userid, array_values($tradeids));
                    }
                    $completed = (($specifictradecnt[$tradeid] ?? 0) >= (int)$q->requirement);
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
