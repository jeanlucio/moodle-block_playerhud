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
 * Main management page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$dir  = optional_param('dir', 'ASC', PARAM_ALPHA);

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
        $scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        if ($scenes) {
            $sceneids = array_keys($scenes);
            [$nsql, $nparams] = $DB->get_in_or_equal($sceneids);
            // Bulk delete choices avoiding N+1.
            $DB->delete_records_select('block_playerhud_choices', "nodeid $nsql", $nparams);
        }
        $DB->delete_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $DB->delete_records('block_playerhud_chapters', ['id' => $chapterid, 'blockinstanceid' => $instanceid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            get_string('chapter_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Save API Keys.
if ($action == 'save_keys' && confirm_sesskey()) {
    $gkey = optional_param('gemini_key', '', PARAM_TEXT);
    $qkey = optional_param('groq_key', '', PARAM_TEXT);

    $config = (array) unserialize(base64_decode($bi->configdata));
    $config['apikey_gemini'] = trim($gkey);
    $config['apikey_groq'] = trim($qkey);

    $bi->configdata = base64_encode(serialize((object)$config));
    $DB->update_record('block_instances', $bi);

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

    $inv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);
    if ($inv) {
        $item = $DB->get_record('block_playerhud_items', ['id' => $inv->itemid, 'blockinstanceid' => $instanceid]);
        if ($item) {
            $player = $DB->get_record('block_playerhud_user', ['blockinstanceid' => $instanceid, 'userid' => $inv->userid]);
            if ($player) {
                $player->currentxp = max(0, $player->currentxp - $item->xp);
                $DB->update_record('block_playerhud_user', $player);
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
    $newinv->source = 'teacher'; // Mark as teacher granted for potential future features (e.g. filtering, special handling).
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

// Tab Definitions (V1.0 - Features under construction hidden).
$tabsdef = [
    'items'  => ['icon' => '📚', 'text' => get_string('tab_items', 'block_playerhud')],
    'trades' => ['icon' => '⚖️', 'text' => get_string('tab_trades', 'block_playerhud')],
    'reports' => ['icon' => '📊', 'text' => get_string('tab_reports', 'block_playerhud')],
    'config' => ['icon' => '🛠️', 'text' => get_string('tab_config', 'block_playerhud')],
];

$tabsdata = [];
foreach ($tabsdef as $key => $data) {
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
    'url_backpack' => (new moodle_url('/blocks/playerhud/view.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false),
    'str_backpack' => get_string('openbackpack', 'block_playerhud'),
    'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'str_back_course' => get_string('back_to_course', 'block_playerhud'),
    'tabs' => $tabsdata,
    'content_html' => $contenthtml,
];

echo $OUTPUT->render_from_template('block_playerhud/manage_layout', $layoutdata);
echo $OUTPUT->footer();
