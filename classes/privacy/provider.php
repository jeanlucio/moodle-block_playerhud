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

namespace block_playerhud\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The collection object.
     * @return collection The collection with added metadata.
     */
    public static function get_metadata(collection $collection): collection {
        // API Keys (User Preferences).
        $collection->add_user_preference('block_playerhud_gemini_key', 'privacy:metadata:preference:gemini_key');
        $collection->add_user_preference('block_playerhud_groq_key', 'privacy:metadata:preference:groq_key');
        $collection->add_user_preference('block_playerhud_openai_key', 'privacy:metadata:preference:openai_key');
        $collection->add_user_preference('block_playerhud_openai_model', 'privacy:metadata:preference:openai_model');
        $collection->add_user_preference('block_playerhud_openai_url', 'privacy:metadata:preference:openai_url');
        // Main User Data.
        $collection->add_database_table('block_playerhud_user', [
            'currentxp' => 'privacy:metadata:playerhud_user:currentxp',
            'ranking_visibility' => 'privacy:metadata:playerhud_user:ranking_visibility',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playerhud_user');

        // Inventory.
        $collection->add_database_table('block_playerhud_inventory', [
            'itemid' => 'privacy:metadata:inventory:itemid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:inventory');

        // RPG Progress (Karma, Classes, Story).
        $collection->add_database_table('block_playerhud_rpg_progress', [
            'classid' => 'privacy:metadata:rpg:classid',
            'karma' => 'privacy:metadata:rpg:karma',
            'current_nodes' => 'privacy:metadata:rpg:nodes',
            'completed_chapters' => 'privacy:metadata:rpg:chapters',
        ], 'privacy:metadata:rpg');

        // Logs.
        $collection->add_database_table('block_playerhud_quest_log', [
            'questid' => 'privacy:metadata:quest_log:questid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:quest_log');

        $collection->add_database_table('block_playerhud_trade_log', [
            'tradeid' => 'privacy:metadata:trade_log:tradeid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:trade_log');

        $collection->add_database_table('block_playerhud_ai_logs', [
            'action_type' => 'privacy:metadata:ai_logs:action',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:ai_logs');

        // Declaration for Moodle Privacy Subsystem (External APIs).
        $collection->add_external_location_link('google_gemini', [
            'prompt' => 'privacy:metadata:external:prompt',
        ], 'privacy:metadata:external:gemini_summary');

        $collection->add_external_location_link('groq', [
            'prompt' => 'privacy:metadata:external:prompt',
        ], 'privacy:metadata:external:groq_summary');

        $collection->add_external_location_link('openai_compatible', [
            'prompt' => 'privacy:metadata:external:prompt',
        ], 'privacy:metadata:external:openai_summary');

        return $collection;
    }

    /**
     * Get the list of contexts where a user has data.
     *
     * @param int $userid The user ID.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {block_playerhud_user} u
                  JOIN {block_instances} bi ON u.blockinstanceid = bi.id
                  JOIN {context} ctx ON (ctx.instanceid = bi.id AND ctx.contextlevel = :blocklevel)
                 WHERE u.userid = :userid";

        $contextlist->add_from_sql($sql, ['userid' => $userid, 'blocklevel' => CONTEXT_BLOCK]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a specific context.
     *
     * @param userlist $userlist The userlist object.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_block) {
            return;
        }

        $params = ['instanceid' => $context->instanceid];

        // Users with profile.
        $sql = "SELECT userid FROM {block_playerhud_user} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Users with RPG progress.
        $sqlrpg = "SELECT userid FROM {block_playerhud_rpg_progress} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sqlrpg, $params);

        // Users with AI logs.
        $sqlai = "SELECT userid FROM {block_playerhud_ai_logs} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sqlai, $params);

        // Users with quest logs.
        $sqlql = "SELECT ql.userid
                    FROM {block_playerhud_quest_log} ql
                    JOIN {block_playerhud_quests} q ON ql.questid = q.id
                   WHERE q.blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sqlql, $params);
    }

    /**
     * Export all user data for the specified approved contextlist.
     * Refactored to avoid N+1 queries.
     *
     * @param approved_contextlist $contextlist The approved contextlist.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $contexts = $contextlist->get_contexts();

        $instanceids = [];
        $validcontexts = [];

        // 1. Gather all block instance IDs from the contexts.
        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_BLOCK) {
                $instanceids[] = $context->instanceid;
                $validcontexts[] = $context;
            }
        }

        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'inst');
        $params = array_merge(['userid' => $userid], $inparams);

        // 2. Bulk fetch Player Profiles.
        $players = $DB->get_records_select(
            'block_playerhud_user',
            "userid = :userid AND blockinstanceid $insql",
            $params,
            '',
            'blockinstanceid, currentxp, enable_gamification, timecreated'
        );

        // 3. Bulk fetch RPG Progress.
        $rpgs = $DB->get_records_select(
            'block_playerhud_rpg_progress',
            "userid = :userid AND blockinstanceid $insql",
            $params,
            '',
            'blockinstanceid, classid, karma, current_nodes, completed_chapters'
        );

        // 4. Bulk fetch Inventory.
        $sqlinv = "SELECT inv.id, it.blockinstanceid, inv.itemid, inv.timecreated
                     FROM {block_playerhud_inventory} inv
                     JOIN {block_playerhud_items} it ON inv.itemid = it.id
                    WHERE inv.userid = :userid AND it.blockinstanceid $insql";

        $inventoryrecords = $DB->get_records_sql($sqlinv, $params);
        $inventorybyinstance = [];

        if ($inventoryrecords) {
            foreach ($inventoryrecords as $inv) {
                $inventorybyinstance[$inv->blockinstanceid][] = [
                    'item_id' => $inv->itemid,
                    'collected_on' => transform::datetime($inv->timecreated),
                ];
            }
        }

        // 5. Bulk fetch Quest Logs.
        $sqlql = "SELECT ql.id, q.blockinstanceid, q.name AS questname, ql.timecreated
                    FROM {block_playerhud_quest_log} ql
                    JOIN {block_playerhud_quests} q ON ql.questid = q.id
                   WHERE ql.userid = :userid AND q.blockinstanceid $insql
                ORDER BY ql.timecreated DESC";

        $questlogs = $DB->get_records_sql($sqlql, $params);
        $questlogsbyinstance = [];

        if ($questlogs) {
            foreach ($questlogs as $log) {
                $questlogsbyinstance[$log->blockinstanceid][] = [
                    'quest_name' => $log->questname,
                    'claimed_on' => transform::datetime($log->timecreated),
                ];
            }
        }

        // 6. Bulk fetch AI Oracle Logs.
        $sqlai = "SELECT id, blockinstanceid, action_type, ai_provider, timecreated
                    FROM {block_playerhud_ai_logs}
                   WHERE userid = :userid AND blockinstanceid $insql
                ORDER BY timecreated DESC";
        $ailogrecords = $DB->get_records_sql($sqlai, $params);
        $ailogsbyinstance = [];

        if ($ailogrecords) {
            foreach ($ailogrecords as $log) {
                $ailogsbyinstance[$log->blockinstanceid][] = [
                    'action' => $log->action_type,
                    'provider' => $log->ai_provider,
                    'date' => transform::datetime($log->timecreated),
                ];
            }
        }

        // 7. Bulk fetch Trade Logs.
        $sqltrades = "SELECT tl.id, t.blockinstanceid, tl.timecreated, t.name as tradename
                        FROM {block_playerhud_trade_log} tl
                        JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                       WHERE tl.userid = :userid AND t.blockinstanceid $insql
                    ORDER BY tl.timecreated DESC";

        $tradelogs = $DB->get_records_sql($sqltrades, $params);
        $tradesbyinstance = [];

        if ($tradelogs) {
            foreach ($tradelogs as $log) {
                $tradesbyinstance[$log->blockinstanceid][] = [
                    'trade_name' => $log->tradename,
                    'transaction_date' => transform::datetime($log->timecreated),
                ];
            }
        }

        // 8. Export data using the in-memory arrays.
        foreach ($validcontexts as $context) {
            $instid = $context->instanceid;

            // A. General Profile.
            if (isset($players[$instid])) {
                $player = $players[$instid];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), get_string('profile')],
                    (object) [
                        'currentxp' => $player->currentxp,
                        'level_progress' => $player->enable_gamification ? get_string('yes') : get_string('no'),
                        'joined' => transform::datetime($player->timecreated),
                    ]
                );
            }

            // B. RPG Progress.
            if (isset($rpgs[$instid])) {
                $rpg = $rpgs[$instid];
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), get_string('privacy_export_rpg', 'block_playerhud')],
                    (object) [
                        'class_id' => $rpg->classid,
                        'karma' => $rpg->karma,
                        'history' => $rpg->current_nodes,
                        'completed_chapters' => $rpg->completed_chapters,
                    ]
                );
            }

            // C. Inventory.
            if (!empty($inventorybyinstance[$instid])) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), get_string('tab_collection', 'block_playerhud')],
                    (object) ['items' => $inventorybyinstance[$instid]]
                );
            }

            // D. Quest Logs (Claimed Quests).
            if (!empty($questlogsbyinstance[$instid])) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'),
                        get_string('privacy_export_quest_log', 'block_playerhud')],
                    (object) ['quests' => $questlogsbyinstance[$instid]]
                );
            }

            // E. Trade Logs (Shop History).
            if (!empty($tradesbyinstance[$instid])) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), get_string('tab_shop', 'block_playerhud')],
                    (object) ['transactions' => $tradesbyinstance[$instid]]
                );
            }

            // F. AI Oracle Logs.
            if (!empty($ailogsbyinstance[$instid])) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'),
                        get_string('privacy_export_ai_logs', 'block_playerhud')],
                    (object) ['logs' => $ailogsbyinstance[$instid]]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }
        $instanceid = $context->instanceid;

        // Delete direct tables.
        $DB->delete_records('block_playerhud_user', ['blockinstanceid' => $instanceid]);
        $DB->delete_records('block_playerhud_rpg_progress', ['blockinstanceid' => $instanceid]);
        $DB->delete_records('block_playerhud_ai_logs', ['blockinstanceid' => $instanceid]);

        // Delete inventory (Fetch block items).
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid], '', 'id');
        if ($items) {
            $itemids = array_keys($items);
            [$insql, $inparams] = $DB->get_in_or_equal($itemids);
            $DB->delete_records_select('block_playerhud_inventory', "itemid $insql", $inparams);
        }

        // Delete Quest logs.
        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid], '', 'id');
        if ($quests) {
            $questids = array_keys($quests);
            [$qinsql, $qinparams] = $DB->get_in_or_equal($questids);
            $DB->delete_records_select('block_playerhud_quest_log', "questid $qinsql", $qinparams);
        }

        // Delete Trade logs.
        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $instanceid], '', 'id');
        if ($trades) {
            $tradeids = array_keys($trades);
            [$tinsql, $tinparams] = $DB->get_in_or_equal($tradeids);
            $DB->delete_records_select('block_playerhud_trade_log', "tradeid $tinsql", $tinparams);
        }
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contextlist.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        $userids = [$userid];

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_BLOCK) {
                self::delete_data_for_user_list_in_context($context->instanceid, $userids);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }
        self::delete_data_for_user_list_in_context($context->instanceid, $userlist->get_userids());
    }

    /**
     * Helper function to delete data for a list of users in a context.
     *
     * @param int $instanceid The block instance ID.
     * @param array $userids The list of user IDs.
     */
    protected static function delete_data_for_user_list_in_context(int $instanceid, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['instanceid' => $instanceid], $uparams);

        // 1. Delete main tables.
        $DB->delete_records_select(
            'block_playerhud_user',
            "blockinstanceid = :instanceid AND userid $usql",
            $params
        );

        $DB->delete_records_select(
            'block_playerhud_rpg_progress',
            "blockinstanceid = :instanceid AND userid $usql",
            $params
        );

        $DB->delete_records_select(
            'block_playerhud_ai_logs',
            "blockinstanceid = :instanceid AND userid $usql",
            $params
        );

        // 2. Delete Inventory (JOIN with Items).
        $sqlinv = "SELECT inv.id FROM {block_playerhud_inventory} inv
                    JOIN {block_playerhud_items} it ON inv.itemid = it.id
                    WHERE it.blockinstanceid = :instanceid AND inv.userid $usql";
        $invrecords = $DB->get_records_sql($sqlinv, $params);
        if ($invrecords) {
            $invids = array_keys($invrecords);
            [$delsql, $delparams] = $DB->get_in_or_equal($invids);
            $DB->delete_records_select('block_playerhud_inventory', "id $delsql", $delparams);
        }

        // 3. Delete Quest Logs.
        $sqlquest = "SELECT ql.id FROM {block_playerhud_quest_log} ql
                      JOIN {block_playerhud_quests} q ON ql.questid = q.id
                      WHERE q.blockinstanceid = :instanceid AND ql.userid $usql";
        $questrecords = $DB->get_records_sql($sqlquest, $params);
        if ($questrecords) {
            $qlids = array_keys($questrecords);
            [$qdelsql, $qdelparams] = $DB->get_in_or_equal($qlids);
            $DB->delete_records_select('block_playerhud_quest_log', "id $qdelsql", $qdelparams);
        }

        // 4. Delete Trade Logs.
        $sqltrade = "SELECT tl.id FROM {block_playerhud_trade_log} tl
                      JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                      WHERE t.blockinstanceid = :instanceid AND tl.userid $usql";
        $traderecords = $DB->get_records_sql($sqltrade, $params);
        if ($traderecords) {
            $tlids = array_keys($traderecords);
            [$tdelsql, $tdelparams] = $DB->get_in_or_equal($tlids);
            $DB->delete_records_select('block_playerhud_trade_log', "id $tdelsql", $tdelparams);
        }
    }

    /**
     * Export all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $geminikey = get_user_preferences('block_playerhud_gemini_key', null, $userid);
        if ($geminikey !== null) {
            writer::with_context(\context_system::instance())->export_user_preference(
                'block_playerhud',
                'block_playerhud_gemini_key',
                $geminikey,
                get_string('privacy:metadata:preference:gemini_key', 'block_playerhud')
            );
        }

        $groqkey = get_user_preferences('block_playerhud_groq_key', null, $userid);
        if ($groqkey !== null) {
            writer::with_context(\context_system::instance())->export_user_preference(
                'block_playerhud',
                'block_playerhud_groq_key',
                $groqkey,
                get_string('privacy:metadata:preference:groq_key', 'block_playerhud')
            );
        }

        $openaikey = get_user_preferences('block_playerhud_openai_key', null, $userid);
        if ($openaikey !== null) {
            writer::with_context(\context_system::instance())->export_user_preference(
                'block_playerhud',
                'block_playerhud_openai_key',
                $openaikey,
                get_string('privacy:metadata:preference:openai_key', 'block_playerhud')
            );
        }

        $openaiurl = get_user_preferences('block_playerhud_openai_url', null, $userid);
        if ($openaiurl !== null) {
            writer::with_context(\context_system::instance())->export_user_preference(
                'block_playerhud',
                'block_playerhud_openai_url',
                $openaiurl,
                get_string('privacy:metadata:preference:openai_url', 'block_playerhud')
            );
        }

        $openaimodel = get_user_preferences('block_playerhud_openai_model', null, $userid);
        if ($openaimodel !== null) {
            writer::with_context(\context_system::instance())->export_user_preference(
                'block_playerhud',
                'block_playerhud_openai_model',
                $openaimodel,
                get_string('privacy:metadata:preference:openai_model', 'block_playerhud')
            );
        }
    }

    /**
     * Delete all use preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be deleted.
     */
    public static function delete_user_preferences(int $userid) {
        unset_user_preference('block_playerhud_gemini_key', $userid);
        unset_user_preference('block_playerhud_groq_key', $userid);
        unset_user_preference('block_playerhud_openai_key', $userid);
        unset_user_preference('block_playerhud_openai_model', $userid);
        unset_user_preference('block_playerhud_openai_url', $userid);
    }
}
