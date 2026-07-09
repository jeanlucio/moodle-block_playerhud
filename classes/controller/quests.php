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
 * Controller for quest enable toggle and deletion.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

/**
 * Controller for the quest lifecycle: enable toggle and single/bulk deletion.
 *
 * Deleting a quest reverts the reward XP previously granted to every student
 * who completed it, so their progress stays consistent with the remaining
 * quests.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quests {
    /**
     * Flips the enabled flag of a quest belonging to the given instance.
     *
     * A foreign quest id is a no-op (returns false) so callers do not redirect
     * with a success message for something they did not change.
     *
     * @param int $questid The quest to toggle.
     * @param int $instanceid The owning block instance ID.
     * @return bool True when the quest was found and toggled, false otherwise.
     */
    public static function toggle_quest(int $questid, int $instanceid): bool {
        global $DB;

        $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
        if (!$quest) {
            return false;
        }

        $DB->set_field('block_playerhud_quests', 'enabled', $quest->enabled ? 0 : 1, ['id' => $questid]);

        return true;
    }

    /**
     * Deletes a quest, its completion log and the reward XP it had granted.
     *
     * @param int $questid The quest to delete.
     * @param int $instanceid The owning block instance ID.
     * @return bool True when the quest was found and deleted, false otherwise.
     */
    public static function delete_quest(int $questid, int $instanceid): bool {
        global $DB;

        $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
        if (!$quest) {
            return false;
        }

        $userscompleted = $DB->get_records_sql(
            "SELECT userid, SUM(xpawarded) AS totalxp
               FROM {block_playerhud_quest_log}
              WHERE questid = :qid
           GROUP BY userid",
            ['qid' => $questid]
        );

        $deductions = [];
        foreach ($userscompleted as $uc) {
            $deductions[$uc->userid] = $uc->totalxp;
        }
        self::revert_xp($deductions, $instanceid);

        $DB->delete_records('block_playerhud_quest_log', ['questid' => $questid]);
        $DB->delete_records('block_playerhud_quests', ['id' => $questid]);

        return true;
    }

    /**
     * Bulk-deletes quests of the given instance, reverting their reward XP.
     *
     * Each candidate id is filtered by blockinstanceid, so foreign ids are
     * skipped. Returns the number of quests actually deleted.
     *
     * @param int[] $questids Candidate quest IDs.
     * @param int $instanceid The owning block instance ID.
     * @return int The number of quests deleted.
     */
    public static function bulk_delete_quests(array $questids, int $instanceid): int {
        global $DB;

        if (empty($questids)) {
            return 0;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($questids);
        $params = array_merge($inparams, [$instanceid]);
        $quests = $DB->get_records_select('block_playerhud_quests', "id $insql AND blockinstanceid = ?", $params);
        if (!$quests) {
            return 0;
        }

        $validids = array_keys($quests);
        [$qinsql, $qinparams] = $DB->get_in_or_equal($validids);

        $holders = $DB->get_records_sql(
            "SELECT userid, SUM(xpawarded) AS totalxp
               FROM {block_playerhud_quest_log}
              WHERE questid $qinsql
           GROUP BY userid",
            $qinparams
        );

        $deductions = [];
        foreach ($holders as $holder) {
            $deductions[$holder->userid] = $holder->totalxp;
        }
        self::revert_xp($deductions, $instanceid);

        $DB->delete_records_select('block_playerhud_quest_log', "questid $qinsql", $qinparams);
        $DB->delete_records_select('block_playerhud_quests', "id $qinsql", $qinparams);

        return count($validids);
    }

    /**
     * Subtracts the given XP amount from each affected player (never below 0).
     *
     * Players are loaded in a single query to avoid an N+1 pattern.
     *
     * @param array $deductions Map of userid to XP amount to remove.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    private static function revert_xp(array $deductions, int $instanceid): void {
        global $DB;

        if (empty($deductions)) {
            return;
        }

        $userids = array_keys($deductions);
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $uparams['instanceid'] = $instanceid;

        $players = $DB->get_records_select(
            'block_playerhud_user',
            "blockinstanceid = :instanceid AND userid $usql",
            $uparams,
            '',
            'userid, id, currentxp, timemodified, enable_gamification'
        );

        foreach ($deductions as $userid => $xptoremove) {
            if (isset($players[$userid])) {
                $player = $players[$userid];
                \block_playerhud\game::change_xp($player, -(int)$xptoremove, $instanceid);
            }
        }
    }
}
