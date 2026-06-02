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
 * Main management page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Initial configuration and parameters.
$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$activetab  = optional_param('tab', 'items', PARAM_ALPHA);

// Action Parameters.
$action  = optional_param('action', '', PARAM_ALPHANUMEXT);
$itemid  = optional_param('itemid', 0, PARAM_INT);
$questid = optional_param('questid', 0, PARAM_INT);
$tradeid = optional_param('tradeid', 0, PARAM_INT);

// Sorting Parameters.
$sort = optional_param('sort', '', PARAM_ALPHA);
$dir  = optional_param('dir', '', PARAM_ALPHA);

// Security checks.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);

// Set block context.
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

// Base URL for redirects.
$baseurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id' => $courseid,
    'instanceid' => $instanceid,
]);

// Page Setup.
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));

$rawblockconfig = base64_decode($bi->configdata ?? '', true);
$blockconfig = ($rawblockconfig !== false && $rawblockconfig !== '') ? unserialize_object($rawblockconfig) : new stdClass();
if (!is_object($blockconfig)) {
    $blockconfig = new stdClass();
}
$rpgmodeenabled  = !empty($blockconfig->enable_rpg) || !isset($blockconfig->enable_rpg);
$itemsenabled    = !empty($blockconfig->enable_items) || !isset($blockconfig->enable_items);
$questsenabled   = !empty($blockconfig->enable_quests) || !isset($blockconfig->enable_quests);

// Determine a sensible fallback tab when a feature group is disabled.
$fallbacktab = $itemsenabled ? 'items' : ($questsenabled ? 'quests' : 'reports');

// Redirect RPG tabs when RPG mode is disabled.
$rpgtabs = ['classes', 'chapters'];
if (in_array($activetab, $rpgtabs) && !$rpgmodeenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $fallbacktab]));
}

// Redirect items/drops/trades tabs when items feature is disabled.
$itemstabs = ['items', 'drops', 'trades'];
if (in_array($activetab, $itemstabs) && !$itemsenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $questsenabled ? 'quests' : 'reports']));
}

// Redirect quests tab when quests feature is disabled.
if ($activetab === 'quests' && !$questsenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $fallbacktab]));
}

// Action processing (Global Controllers).

// Action: Toggle Item Status.
if ($action == 'toggle' && $itemid && confirm_sesskey()) {
    $it = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($it) {
        $newstatus = $it->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_items', 'enabled', $newstatus, ['id' => $itemid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Toggle Quest Status.
if ($action == 'toggle_quest' && $questid && confirm_sesskey()) {
    $q = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    if ($q) {
        $newstatus = $q->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_quests', 'enabled', $newstatus, ['id' => $questid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Item (Single).
if ($action === 'delete' && $itemid && confirm_sesskey()) {
    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($item) {
        // Bulk load users holding this item to remove XP (Prevent N+1 Queries).
        $sql = "SELECT userid, COUNT(id) as qtd FROM {block_playerhud_inventory} WHERE itemid = ? GROUP BY userid";
        $holders = $DB->get_records_sql($sql, [$itemid]);

        if ($holders) {
            $userids = array_keys($holders);
            [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            $uparams['instanceid'] = $instanceid;

            $players = $DB->get_records_select(
                'block_playerhud_user',
                "blockinstanceid = :instanceid AND userid $usql",
                $uparams,
                '',
                'userid, id, currentxp, timemodified, enable_gamification'
            );

            $now = time();
            foreach ($holders as $holder) {
                if (isset($players[$holder->userid])) {
                    $player = $players[$holder->userid];
                    $xptoremove = $item->xp * $holder->qtd;
                    $player->currentxp = max(0, $player->currentxp - $xptoremove);
                    $DB->update_record('block_playerhud_user', $player);
                }
            }
        }

        // Delete dependencies.
        $DB->delete_records('block_playerhud_inventory', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_drops', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_reqs', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['itemid' => $itemid]);

        // Delete the item files and record.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $itemid);
        $DB->delete_records('block_playerhud_items', ['id' => $itemid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Bulk Delete Items (Multiple).
if ($action === 'bulk_delete' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    if (!empty($bulkids)) {
        $fs = get_file_storage();
        $deletedcount = 0;

        // Get all selected items belonging to this instance.
        [$insql, $inparams] = $DB->get_in_or_equal($bulkids);
        $params = array_merge($inparams, [$instanceid]);
        $items = $DB->get_records_select('block_playerhud_items', "id $insql AND blockinstanceid = ?", $params);

        if ($items) {
            $itemids = array_keys($items);
            [$iteminsql, $iteminparams] = $DB->get_in_or_equal($itemids);

            // Calculate XP to remove per user in a single query.
            $sql = "SELECT inv.userid, SUM(it.xp) as totalxptoremove
                      FROM {block_playerhud_inventory} inv
                      JOIN {block_playerhud_items} it ON inv.itemid = it.id
                     WHERE inv.itemid $iteminsql
                  GROUP BY inv.userid";
            $holders = $DB->get_records_sql($sql, $iteminparams);

            // Bulk load users to avoid N+1.
            if ($holders) {
                $userids = array_keys($holders);
                [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
                $uparams['instanceid'] = $instanceid;

                $players = $DB->get_records_select(
                    'block_playerhud_user',
                    "blockinstanceid = :instanceid AND userid $usql",
                    $uparams,
                    '',
                    'userid, id, currentxp, timemodified, enable_gamification'
                );

                $now = time();
                foreach ($holders as $holder) {
                    if (isset($players[$holder->userid])) {
                        $player = $players[$holder->userid];
                        $player->currentxp = max(0, $player->currentxp - $holder->totalxptoremove);
                        $DB->update_record('block_playerhud_user', $player);
                    }
                }
            }

            // Delete dependencies in bulk without loops.
            $DB->delete_records_select('block_playerhud_inventory', "itemid $iteminsql", $iteminparams);
            $DB->delete_records_select('block_playerhud_drops', "itemid $iteminsql", $iteminparams);
            $DB->delete_records_select('block_playerhud_trade_reqs', "itemid $iteminsql", $iteminparams);
            $DB->delete_records_select('block_playerhud_trade_rewards', "itemid $iteminsql", $iteminparams);

            // Delete files and the items themselves.
            foreach ($itemids as $delid) {
                $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $delid);
            }
            $DB->delete_records_select('block_playerhud_items', "id $iteminsql", $iteminparams);

            $deletedcount = count($itemids);
        }

        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted_bulk', 'block_playerhud', $deletedcount),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('no_items_selected', 'block_playerhud'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Action: Delete Quest.
if ($action == 'delete_quest' && $questid && confirm_sesskey()) {
    $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    if ($quest) {
        // Revert XP for students who completed avoiding N+1.
        if ($quest->reward_xp > 0) {
            $sql = "SELECT userid, COUNT(id) as completions FROM {block_playerhud_quest_log} WHERE questid = :qid GROUP BY userid";
            $userscompleted = $DB->get_records_sql($sql, ['qid' => $questid]);

            if ($userscompleted) {
                $userids = array_keys($userscompleted);
                [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
                $uparams['instanceid'] = $instanceid;

                $players = $DB->get_records_select(
                    'block_playerhud_user',
                    "blockinstanceid = :instanceid AND userid $usql",
                    $uparams,
                    '',
                    'userid, id, currentxp, timemodified, enable_gamification'
                );

                $now = time();
                foreach ($userscompleted as $uc) {
                    if (isset($players[$uc->userid])) {
                        $player = $players[$uc->userid];
                        $xptoremove = $quest->reward_xp * $uc->completions;
                        $player->currentxp = max(0, $player->currentxp - $xptoremove);
                        $DB->update_record('block_playerhud_user', $player);
                    }
                }
            }
        }

        // Delete records.
        $DB->delete_records('block_playerhud_quest_log', ['questid' => $questid]);
        $DB->delete_records('block_playerhud_quests', ['id' => $questid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'quests']),
            get_string('quest_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Bulk Delete Quests (Multiple).
if ($action === 'bulk_delete_quests' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    if (!empty($bulkids)) {
        // Get all selected quests belonging to this instance.
        [$insql, $inparams] = $DB->get_in_or_equal($bulkids);
        $params = array_merge($inparams, [$instanceid]);
        $quests = $DB->get_records_select('block_playerhud_quests', "id $insql AND blockinstanceid = ?", $params);

        $deletedcount = 0;

        if ($quests) {
            $questids = array_keys($quests);
            [$qinsql, $qinparams] = $DB->get_in_or_equal($questids);

            // Calculate total XP to remove per user for all quests in a single query.
            $sql = "SELECT ql.userid, SUM(q.reward_xp) as totalxptoremove
                      FROM {block_playerhud_quest_log} ql
                      JOIN {block_playerhud_quests} q ON ql.questid = q.id
                     WHERE ql.questid $qinsql
                  GROUP BY ql.userid";
            $holders = $DB->get_records_sql($sql, $qinparams);

            // Bulk load users to avoid N+1.
            if ($holders) {
                $userids = array_keys($holders);
                [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
                $uparams['instanceid'] = $instanceid;

                $players = $DB->get_records_select(
                    'block_playerhud_user',
                    "blockinstanceid = :instanceid AND userid $usql",
                    $uparams,
                    '',
                    'userid, id, currentxp, timemodified, enable_gamification'
                );

                // Revert XP for all affected users.
                foreach ($holders as $holder) {
                    if (isset($players[$holder->userid])) {
                        $player = $players[$holder->userid];
                        $player->currentxp = max(0, $player->currentxp - $holder->totalxptoremove);
                        $DB->update_record('block_playerhud_user', $player);
                    }
                }
            }

            // Delete records in bulk without loops.
            $DB->delete_records_select('block_playerhud_quest_log', "questid $qinsql", $qinparams);
            $DB->delete_records_select('block_playerhud_quests', "id $qinsql", $qinparams);

            $deletedcount = count($questids);
        }

        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted_bulk', 'block_playerhud', $deletedcount),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('no_items_selected', 'block_playerhud'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Action: Delete Trade.
if ($action == 'delete_trade' && $tradeid && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();
    try {
        $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_log', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
        $transaction->allow_commit();

        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        $transaction->rollback($e);
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('error_msg', 'block_playerhud', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Action: Delete Class.
if ($action == 'delete_class') {
    $classid = optional_param('classid', 0, PARAM_INT);
    if ($classid && confirm_sesskey()) {
        $fs = get_file_storage();
        for ($i = 1; $i <= 5; $i++) {
            $fs->delete_area_files($context->id, 'block_playerhud', 'class_image_' . $i, $classid);
        }
        $DB->delete_records('block_playerhud_classes', ['id' => $classid, 'blockinstanceid' => $instanceid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'classes']),
            get_string('class_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Chapter.
if ($action == 'delete_chapter') {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid && confirm_sesskey()) {
        $chapter = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );
        $scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapter->id]);
        if ($scenes) {
            $sceneids = array_keys($scenes);
            [$nsql, $nparams] = $DB->get_in_or_equal($sceneids);
            // Bulk delete choices avoiding N+1.
            $DB->delete_records_select('block_playerhud_choices', "nodeid $nsql", $nparams);
        }
        $DB->delete_records('block_playerhud_story_nodes', ['chapterid' => $chapter->id]);
        $DB->delete_records('block_playerhud_chapters', ['id' => $chapter->id, 'blockinstanceid' => $instanceid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            get_string('chapter_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Move Chapter Up.
if ($action === 'move_chapter_up' && confirm_sesskey()) {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid) {
        $chapter = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        // Get the previous chapter (lower sortorder).
        $prevchapter = $DB->get_record_sql(
            "SELECT * FROM {block_playerhud_chapters}
             WHERE blockinstanceid = ? AND sortorder < ?
             ORDER BY sortorder DESC",
            [$instanceid, $chapter->sortorder]
        );

        if ($prevchapter) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $temporder = $chapter->sortorder;
                $chapter->sortorder = $prevchapter->sortorder;
                $prevchapter->sortorder = $temporder;

                $DB->update_record('block_playerhud_chapters', $chapter);
                $DB->update_record('block_playerhud_chapters', $prevchapter);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        }

        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

// Action: Move Chapter Down.
if ($action === 'move_chapter_down' && confirm_sesskey()) {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid) {
        $chapter = $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        // Get the next chapter (higher sortorder).
        $nextchapter = $DB->get_record_sql(
            "SELECT * FROM {block_playerhud_chapters}
             WHERE blockinstanceid = ? AND sortorder > ?
             ORDER BY sortorder ASC",
            [$instanceid, $chapter->sortorder]
        );

        if ($nextchapter) {
            $transaction = $DB->start_delegated_transaction();
            try {
                $temporder = $chapter->sortorder;
                $chapter->sortorder = $nextchapter->sortorder;
                $nextchapter->sortorder = $temporder;

                $DB->update_record('block_playerhud_chapters', $chapter);
                $DB->update_record('block_playerhud_chapters', $nextchapter);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        }

        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

// Action: Save API Keys.
if ($action === 'save_keys' && confirm_sesskey()) {
    $gkey = optional_param('gemini_key', '', PARAM_TEXT);
    $qkey = optional_param('groq_key', '', PARAM_TEXT);
    $okey = optional_param('openai_key', '', PARAM_TEXT);
    $ourl = optional_param('openai_url', '', PARAM_URL);
    $omodel = optional_param('openai_model', '', PARAM_TEXT);

    // Store keys as user preferences to prevent sensitive data from being stored in block config and potentially leaked in backups.
    set_user_preference('block_playerhud_gemini_key', trim($gkey));
    set_user_preference('block_playerhud_groq_key', trim($qkey));
    set_user_preference('block_playerhud_openai_key', trim($okey));
    set_user_preference('block_playerhud_openai_url', trim($ourl));
    set_user_preference('block_playerhud_openai_model', trim($omodel));

    // Remove keys from block config if they exist to prevent confusion and ensure they are only stored in user preferences.
    $rawconfig = base64_decode($bi->configdata ?? '', true);
    $config = ($rawconfig !== false && $rawconfig !== '') ? (array) unserialize_object($rawconfig) : [];
    if (isset($config['apikey_gemini']) || isset($config['apikey_groq'])) {
        unset($config['apikey_gemini'], $config['apikey_groq']);
        $bi->configdata = base64_encode(serialize((object)$config));
        $DB->update_record('block_instances', $bi);
    }

    redirect(
        new moodle_url($baseurl, ['tab' => 'config']),
        get_string('changessaved', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Action: Revoke Item (Teacher manually removes item).
if ($action === 'revoke_item' && confirm_sesskey()) {
    $invid = required_param('invid', PARAM_INT);
    $ruserid = required_param('r_userid', PARAM_INT);

    $inv = $DB->get_record_sql(
        "SELECT inv.*
           FROM {block_playerhud_inventory} inv
           JOIN {block_playerhud_items} i ON i.id = inv.itemid
          WHERE inv.id = :invid AND i.blockinstanceid = :instanceid",
        ['invid' => $invid, 'instanceid' => $instanceid]
    );
    if ($inv) {
        $item = $DB->get_record('block_playerhud_items', ['id' => $inv->itemid, 'blockinstanceid' => $instanceid]);
        if ($item) {
            $player = $DB->get_record('block_playerhud_user', ['blockinstanceid' => $instanceid, 'userid' => $inv->userid]);
            if ($player) {
                // Only deduct XP if the originating drop was finite — infinite drops (maxusage=0)
                // deliberately grant 0 XP in process_collection, so reverting them must not deduct.
                $isinfinite = false;
                if ($inv->dropid > 0) {
                    $drop = $DB->get_record('block_playerhud_drops', ['id' => $inv->dropid]);
                    $isinfinite = $drop && (int)$drop->maxusage === 0;
                }
                if (!$isinfinite && $item->xp > 0) {
                    $player->currentxp = max(0, $player->currentxp - $item->xp);
                    $DB->update_record('block_playerhud_user', $player);
                }
            }

            // Soft Revoke: Mark the inventory record as revoked instead of deleting.
            $inv->source = 'revoked';
            $inv->timecreated = time();
            $DB->update_record('block_playerhud_inventory', $inv);
        }
    }

    $url = new moodle_url($baseurl, ['tab' => 'reports', 'r_userid' => $ruserid]);
    redirect($url, get_string('item_revoked', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Grant Item (Teacher manually gives item).
if ($action === 'grant_item' && confirm_sesskey()) {
    $ruserid = required_param('r_userid', PARAM_INT);
    $itemid = required_param('itemid', PARAM_INT);

    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
    $player = \block_playerhud\game::get_player($instanceid, $ruserid);

    $newinv = new \stdClass();
    $newinv->userid = $ruserid;
    $newinv->itemid = $item->id;
    $newinv->dropid = 0;
    $newinv->source = 'teacher';
    $newinv->timecreated = time();
    $DB->insert_record('block_playerhud_inventory', $newinv);

    if ($item->xp > 0) {
        $player->currentxp += $item->xp;
        $player->timemodified = time();
        $DB->update_record('block_playerhud_user', $player);
    }

    $url = new moodle_url($baseurl, ['tab' => 'reports', 'r_userid' => $ruserid]);
    redirect($url, get_string('item_granted', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Auto Suggest Quests (Heuristic).
if ($action === 'suggest_quests' || $action === 'save_suggestions') {
    $rawconfig = base64_decode($bi->configdata ?? '', true);
    $config = ($rawconfig !== false && $rawconfig !== '') ? unserialize_object($rawconfig) : new \stdClass();
    if (!is_object($config)) {
        $config = new \stdClass();
    }

    $suggestions = \block_playerhud\quest::get_heuristic_suggestions($instanceid, $courseid, $config);

    // Build the form with suggestions.
    $formurl = new moodle_url($baseurl, ['tab' => 'quests']);
    $sugform = new \block_playerhud\form\suggest_quests_form($formurl, ['suggestions' => $suggestions]);

    if ($action === 'suggest_quests') {
        $sugform->set_data([
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'action'     => 'save_suggestions',
        ]);
    }

    if ($sugform->is_cancelled()) {
        redirect(new moodle_url($baseurl, ['tab' => 'quests']));
    } else if ($data = $sugform->get_data()) {
        $now = time();
        $records = [];
        foreach ($suggestions as $sug) {
            $field = 'sug_' . $sug['uid'];
            if (!empty($data->$field)) {
                $record = new \stdClass();
                $record->blockinstanceid  = $instanceid;
                $record->name             = $sug['name'];
                $record->description      = '';
                $record->type             = $sug['type'];
                $record->requirement      = (string)$sug['requirement'];
                $record->req_itemid       = 0;
                $record->reward_xp        = $sug['reward_xp'];
                $record->reward_itemid    = 0;
                $record->required_class_id = '0';
                $record->image_todo       = $sug['image_todo'];
                $record->image_done       = $sug['image_done'];
                $record->enabled          = 1;
                $record->timecreated      = $now;
                $record->timemodified     = $now;
                $records[] = $record;
            }
        }
        $count = count($records);
        if ($count > 0) {
            $DB->insert_records('block_playerhud_quests', $records);
        }

        // Redirect back to the quests tab with a success message indicating how many quests were created.
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests']),
            get_string('quest_sug_created', 'block_playerhud', $count),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $PAGE->requires->js_call_amd('block_playerhud/manage_quests', 'init', [[
        'strings' => [
            'confirm_title'   => get_string('confirmation', 'admin'),
            'yes'             => get_string('yes'),
            'cancel'          => get_string('cancel'),
            'delete_selected' => get_string('delete_selected', 'block_playerhud'),
            'delete_n_items'  => get_string('delete_n_items', 'block_playerhud'),
            'confirm_bulk'    => get_string('confirm_bulk_delete', 'block_playerhud'),
        ],
    ]]);

    echo $OUTPUT->header();
    $sugform->display();
    echo $OUTPUT->footer();
    exit;
}

// Action: Suggest Trades (Avatar pack + PlayerCoin heuristic).
if ($action === 'suggest_trades' || $action === 'save_suggest_trades') {
    // Fetch PlayerCoin.
    $playercoin = $DB->get_record('block_playerhud_items', [
        'blockinstanceid' => $instanceid,
        'action_type'     => 'playercoin',
    ], '*', MUST_EXIST);

    // Fetch all avatar items.
    $avatars = $DB->get_records_select(
        'block_playerhud_items',
        "blockinstanceid = :id AND action_type = 'avatar_profile'",
        ['id' => $instanceid],
        'id ASC'
    );

    // Preload coverage to skip suggestions that already have trades.
    // Individual: avatar is the sole reward of an existing trade.
    // Bundle: a single trade already rewards all current avatars.
    $individualcoveredids = [];
    $bundleexists = false;
    if (!empty($avatars)) {
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
    }

    // Build suggestion list: one per avatar without an individual trade + bundle if not yet created.
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

    // Bundle: 50 coins → all avatars, only if no bundle trade exists yet.
    if (!$bundleexists && !empty($avatars)) {
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

    if ($action === 'suggest_trades' && empty($suggestions)) {
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('trade_sug_none_available', 'block_playerhud'),
            \core\output\notification::NOTIFY_INFO
        );
    }

    $formurl = new moodle_url($baseurl, ['tab' => 'trades']);
    $sugform = new \block_playerhud\form\suggest_trades_form($formurl, ['suggestions' => $suggestions]);

    if ($action === 'suggest_trades') {
        $sugform->set_data([
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'action'     => 'save_suggest_trades',
        ]);
    }

    if ($sugform->is_cancelled()) {
        redirect(new moodle_url($baseurl, ['tab' => 'trades']));
    } else if ($data = $sugform->get_data()) {
        $now = time();
        $count = 0;
        $transaction = $DB->start_delegated_transaction();
        foreach ($suggestions as $sug) {
            $field = 'sug_' . $sug['uid'];
            if (empty($data->$field)) {
                continue;
            }
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
            $count++;
        }
        $transaction->allow_commit();
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('trade_sug_created', 'block_playerhud', $count),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $PAGE->requires->js_call_amd('block_playerhud/manage_trades', 'init', [[
        'strings' => [
            'confirm_title' => get_string('confirmation', 'admin'),
            'yes'           => get_string('yes'),
            'cancel'        => get_string('cancel'),
        ],
    ]]);

    echo $OUTPUT->header();
    $sugform->display();
    echo $OUTPUT->footer();
    exit;
}

// PRE-RENDER LOGIC (Controller Strategy).
$contenthtml = '';

// Try to load the tab controller.
$renderclass = "\\block_playerhud\\output\\manage\\tab_{$activetab}";
if (class_exists($renderclass)) {
    $renderer = new $renderclass($instanceid, $courseid, $sort, $dir);

    if (method_exists($renderer, 'process')) {
        $renderer->process();
    }

    if ($renderer instanceof \templatable) {
        if (method_exists($renderer, 'display')) {
            $contenthtml = $renderer->display();
        } else {
            $contenthtml = $OUTPUT->render_from_template(
                "block_playerhud/tab_{$activetab}",
                $renderer->export_for_template($OUTPUT)
            );
        }
    } else {
        if (method_exists($renderer, 'display')) {
            $contenthtml = $renderer->display();
        }
    }
} else {
    $contenthtml = $OUTPUT->notification(
        get_string('tab_maintenance', 'block_playerhud', ucfirst($activetab)),
        'info'
    );
}

echo $OUTPUT->header();

// Tab Definitions.
$tabsdef = [
    'items'    => $itemsenabled ? ['icon' => '📚', 'text' => get_string('tab_items', 'block_playerhud')] : null,
    'trades'   => $itemsenabled ? ['icon' => '🛒', 'text' => get_string('tab_trades', 'block_playerhud')] : null,
    'quests'   => $questsenabled ? ['icon' => '🎯', 'text' => get_string('tab_quests', 'block_playerhud')] : null,
    'classes'  => $rpgmodeenabled ? ['icon' => '⚔️', 'text' => get_string('tab_classes', 'block_playerhud')] : null,
    'chapters' => $rpgmodeenabled ? ['icon' => '📖', 'text' => get_string('tab_chapters', 'block_playerhud')] : null,
    'reports'   => ['icon' => '📊', 'text' => get_string('tab_reports', 'block_playerhud')],
    'assistant' => ['icon' => '🤖', 'text' => get_string('tab_assistant', 'block_playerhud')],
    'config'    => ['icon' => '🛠️', 'text' => get_string('tab_config', 'block_playerhud')],
];

$tabsdata = [];
foreach ($tabsdef as $key => $data) {
    if ($data === null) {
        continue;
    }
    $tabsdata[] = [
        'active' => ($activetab == $key),
        'url' => (new moodle_url($baseurl, ['tab' => $key]))->out(false),
        'icon' => $data['icon'],
        'text' => $data['text'],
    ];
}

// Data for Layout.
$layoutdata = [
    'str_title' => get_string('master_panel', 'block_playerhud'),
    'url_student_area' => (new moodle_url('/blocks/playerhud/view.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false),
    'str_student_area' => get_string('student_area', 'block_playerhud'),
    'url_help' => (new moodle_url('/blocks/playerhud/help.php', [
        'id'         => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false),
    'str_help' => get_string('help_btn', 'block_playerhud'),
    'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'str_back_course' => get_string('back_to_course', 'block_playerhud'),
    'tabs' => $tabsdata,
    'content_html' => $contenthtml,
];

echo $OUTPUT->render_from_template('block_playerhud/manage_layout', $layoutdata);
echo $OUTPUT->footer();
