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
 * Script to process trade transactions securely.
 * Optimized for zero N+1 queries.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$tradeid = required_param('tradeid', PARAM_INT);

// 1. Initial Security and Context.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
require_sesskey();

$context = \context_block::instance($instanceid);
require_capability('block/playerhud:view', $context);

$returnparam = optional_param('returnurl', '', PARAM_LOCALURL);
if (!empty($returnparam)) {
    $returnurl = new moodle_url($returnparam);
} else {
    $returnurl = new moodle_url('/blocks/playerhud/view.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
        'tab' => 'shop',
    ]);
}

// 2. Fetch Trade.
$trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
if (!$trade) {
    redirect($returnurl, get_string('error_trade_invalid', 'block_playerhud'), \core\output\notification::NOTIFY_ERROR);
}

// 3. Access Checks (Group/Grouping).
if ($trade->groupid != 0) {
    $authorized = false;
    if ($trade->groupid > 0) {
        if (groups_is_member($trade->groupid, $USER->id)) {
            $authorized = true;
        }
    } else {
        $groupingid = abs($trade->groupid);
        $usergroups = groups_get_all_groups($course->id, $USER->id, $groupingid);
        if (!empty($usergroups)) {
            $authorized = true;
        }
    }

    if (!$authorized) {
        redirect($returnurl, get_string('error_trade_group', 'block_playerhud'), \core\output\notification::NOTIFY_ERROR);
    }
}

// 4. Pre-fetch all dependencies in BULK (Eliminating N+1 Queries).
$requirements = $DB->get_records('block_playerhud_trade_reqs', ['tradeid' => $trade->id]);
$rewards = $DB->get_records('block_playerhud_trade_rewards', ['tradeid' => $trade->id]);

// Collect all unique item IDs involved in this trade.
$allitemids = [];
if ($requirements) {
    foreach ($requirements as $req) {
        $allitemids[$req->itemid] = $req->itemid;
    }
}
if ($rewards) {
    foreach ($rewards as $rew) {
        $allitemids[$rew->itemid] = $rew->itemid;
    }
}

// Load all item definitions in a single query.
$itemsmap = [];
if (!empty($allitemids)) {
    [$itminsql, $itminparams] = $DB->get_in_or_equal($allitemids, SQL_PARAMS_NAMED, 'itm');
    $itemsmap = $DB->get_records_select('block_playerhud_items', "id $itminsql", $itminparams);
}

// 5. Class Restriction Check.
$myclassid = 0;
if ($DB->get_manager()->table_exists('block_playerhud_rpg_progress')) {
    $rpgprog = $DB->get_record('block_playerhud_rpg_progress', [
        'userid' => $USER->id,
        'blockinstanceid' => $instanceid,
    ]);
    if ($rpgprog) {
        $myclassid = $rpgprog->classid;
    }
}

// Validate rewards against user class using the pre-fetched itemsmap.
if ($rewards) {
    foreach ($rewards as $rew) {
        if (isset($itemsmap[$rew->itemid]) && !empty($itemsmap[$rew->itemid]->required_class_id)) {
            $reqclass = $itemsmap[$rew->itemid]->required_class_id;
            if (!block_playerhud_is_visible_for_class($reqclass, $myclassid)) {
                redirect($returnurl, get_string('error_trade_class', 'block_playerhud'), \core\output\notification::NOTIFY_ERROR);
            }
        }
    }
}

// 6. One-Time Check.
if ($trade->onetime == 1) {
    $alreadytraded = $DB->record_exists('block_playerhud_trade_log', ['tradeid' => $trade->id, 'userid' => $USER->id]);
    if ($alreadytraded) {
        redirect($returnurl, get_string('error_trade_onetime', 'block_playerhud'), \core\output\notification::NOTIFY_WARNING);
    }
}

// 7. Race Condition Lock.
$lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
$lockkey = 'trade_usr_' . $USER->id . '_inst_' . $instanceid;
$lock = $lockfactory->get_lock($lockkey, 10);

if (!$lock) {
    redirect($returnurl, get_string('error_trade_lock', 'block_playerhud'), \core\output\notification::NOTIFY_ERROR);
}

try {
    // 8. Balance Validation (Bulk loading user inventory).
    $itemstoremove = [];
    $userinventorymap = [];

    if ($requirements && !empty($allitemids)) {
        // Fetch all required inventory items in a single query.
        [$invinsql, $invinparams] = $DB->get_in_or_equal($allitemids, SQL_PARAMS_NAMED, 'rinv');
        $invparams = array_merge(['userid' => $USER->id], $invinparams);
        $sql = "SELECT id, itemid
                  FROM {block_playerhud_inventory}
                 WHERE userid = :userid AND itemid $invinsql
              ORDER BY timecreated ASC";

        $invrecords = $DB->get_records_sql($sql, $invparams);

        // Group inventory by itemid in PHP memory.
        if ($invrecords) {
            foreach ($invrecords as $inv) {
                $userinventorymap[$inv->itemid][] = $inv->id;
            }
        }

        // Validate quantities.
        foreach ($requirements as $req) {
            $countuserhas = isset($userinventorymap[$req->itemid]) ? count($userinventorymap[$req->itemid]) : 0;

            if ($countuserhas < $req->qty) {
                $itemname = isset($itemsmap[$req->itemid]) ?
                    format_string($itemsmap[$req->itemid]->name) : get_string('item', 'block_playerhud');

                $a = new \stdClass();
                $a->missing = $req->qty - $countuserhas;
                $a->name = $itemname;

                throw new \moodle_exception('error_trade_insufficient', 'block_playerhud', '', $a);
            }

            // Slice only the exact amount of IDs needed to pay for the trade.
            $sliced = array_slice($userinventorymap[$req->itemid], 0, $req->qty);
            $itemstoremove = array_merge($itemstoremove, $sliced);
        }
    }

    // 9. Execution (Atomic Transaction).
    $transaction = $DB->start_delegated_transaction();

    // Consume Payment Items.
    if (!empty($itemstoremove)) {
        $DB->delete_records_list('block_playerhud_inventory', 'id', $itemstoremove);
    }

    // Give Reward Items.
    $rewardsnames = [];
    if ($rewards) {
        $now = time();
        foreach ($rewards as $rew) {
            if (!isset($itemsmap[$rew->itemid])) {
                continue;
            }

            $rewarditem = $itemsmap[$rew->itemid];
            for ($i = 0; $i < $rew->qty; $i++) {
                $newinv = new \stdClass();
                $newinv->userid = $USER->id;
                $newinv->itemid = $rew->itemid;
                $newinv->dropid = 0;
                $newinv->source = 'shop';
                $newinv->timecreated = $now;
                $DB->insert_record('block_playerhud_inventory', $newinv);
            }
            $rewardsnames[] = "{$rew->qty}x " . format_string($rewarditem->name);
        }
    }

    // Record Log.
    $log = new \stdClass();
    $log->tradeid = $trade->id;
    $log->userid = $USER->id;
    $log->timecreated = time();
    $DB->insert_record('block_playerhud_trade_log', $log);

    $transaction->allow_commit();

    // Success Redirect.
    $strrewards = implode(', ', $rewardsnames);
    redirect(
        $returnurl,
        get_string('trade_success_msg', 'block_playerhud', $strrewards),
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (\moodle_exception $me) {
    if (isset($transaction)) {
        $transaction->rollback($me);
    }
    redirect($returnurl, $me->getMessage(), \core\output\notification::NOTIFY_ERROR);
} catch (\Exception $e) {
    if (isset($transaction)) {
        $transaction->rollback($e);
    }
    redirect($returnurl, get_string('error_msg', 'block_playerhud', $e->getMessage()), \core\output\notification::NOTIFY_ERROR);
} finally {
    $lock->release();
}
