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
 * Quest logic class for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
                $status->label = "{$current} / {$target} " . get_string('items', 'block_playerhud');
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
        $config = unserialize(base64_decode($blockinstance->configdata));
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
}
