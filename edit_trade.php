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
 * Script to add/edit a trade (shop offer).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$tradeid = optional_param('tradeid', 0, PARAM_INT);

// Security and Context.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

$baseurl = new moodle_url('/blocks/playerhud/edit_trade.php', [
    'courseid' => $courseid,
    'instanceid' => $instanceid,
]);
if ($tradeid) {
    $baseurl->param('tradeid', $tradeid);
}

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

// 1. Prepare images data for JS Map.
$allitemsdata = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
$jsitemsmap = [];

require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

foreach ($allitemsdata as $it) {
    $media = \block_playerhud\utils::get_item_display_data($it, $context);
    $key = (string)$it->id;

    if ($media['is_image']) {
        $jsitemsmap[$key] = $media['url'];
    } else {
        $jsitemsmap[$key] = 'EMOJI:' . strip_tags($media['content']);
    }
}

// 2. Database Fetching for Edit Mode.
$dbreqcount = 0;
$dbgivecount = 0;
$requirements = [];
$rewards = [];

if ($tradeid) {
    $requirements = $DB->get_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid], 'id ASC');
    $dbreqcount = count($requirements);

    $rewards = $DB->get_records('block_playerhud_trade_rewards', ['tradeid' => $tradeid], 'id ASC');
    $dbgivecount = count($rewards);
}

// 3. Initialize Form.
$mform = new \block_playerhud\form\edit_trade_form(null, [
    'instanceid' => $instanceid,
    'courseid' => $courseid,
    'db_req_count' => $dbreqcount,
    'db_give_count' => $dbgivecount,
]);

// Populate Form Data.
if ($tradeid && !$mform->is_submitted()) {
    $trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
    $data = (array)$trade;
    $data['courseid'] = $courseid;
    $data['instanceid'] = $instanceid;
    $data['tradeid'] = $tradeid;

    $i = 0;
    foreach ($requirements as $req) {
        $data["req_itemid_$i"] = $req->itemid;
        $data["req_qty_$i"] = $req->qty;
        $i++;
    }
    $data['repeats_req'] = max(3, $dbreqcount);

    $i = 0;
    foreach ($rewards as $rew) {
        $data["give_itemid_$i"] = $rew->itemid;
        $data["give_qty_$i"] = $rew->qty;
        $i++;
    }
    $data['repeats_give'] = max(3, $dbgivecount);

    $mform->set_data($data);
} else if (!$mform->is_submitted()) {
    $mform->set_data(['courseid' => $courseid, 'instanceid' => $instanceid]);
}

// 4. Form Processing.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'trades']));
} else if ($data = $mform->get_data()) {
    $transaction = $DB->start_delegated_transaction();

    $record = new \stdClass();
    $record->blockinstanceid = $instanceid;
    $record->name = $data->name;
    $record->centralized = $data->centralized;
    $record->onetime = $data->onetime;
    $record->groupid = $data->groupid;

    if (!empty($data->tradeid)) {
        $record->id = $data->tradeid;
        $DB->update_record('block_playerhud_trades', $record);
        $currenttradeid = $data->tradeid;

        // Clear old constraints safely.
        $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $currenttradeid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $currenttradeid]);
    } else {
        $record->timecreated = time();
        $currenttradeid = $DB->insert_record('block_playerhud_trades', $record);
    }

    // Save Requirements (Student Pays).
    $reqstoinsert = [];

    for ($i = 0; $i < $data->repeats_req; $i++) {
        $itemfield = "req_itemid_$i";
        $qtyfield = "req_qty_$i";

        if (!empty($data->$itemfield) && $data->$itemfield > 0) {
            $req = new \stdClass();
            $req->tradeid = $currenttradeid;
            $req->itemid = $data->$itemfield;
            $req->qty = max(1, (int)$data->$qtyfield);

            $reqstoinsert[] = $req;
        }
    }

    if (!empty($reqstoinsert)) {
        $DB->insert_records('block_playerhud_trade_reqs', $reqstoinsert);
    }


    // Save Rewards (Student Receives).
    $rewardstoinsert = [];

    for ($i = 0; $i < $data->repeats_give; $i++) {
        $itemfield = "give_itemid_$i";
        $qtyfield = "give_qty_$i";

        if (!empty($data->$itemfield) && $data->$itemfield > 0) {
            $rew = new \stdClass();
            $rew->tradeid = $currenttradeid;
            $rew->itemid = $data->$itemfield;
            $rew->qty = max(1, (int)$data->$qtyfield);

            $rewardstoinsert[] = $rew;
        }
    }

    if (!empty($rewardstoinsert)) {
        $DB->insert_records('block_playerhud_trade_rewards', $rewardstoinsert);
    }

    $transaction->allow_commit();
    redirect(
        new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'trades']),
        get_string('trade_saved', 'block_playerhud'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// 5. Render Page.
$PAGE->requires->js_call_amd('block_playerhud/edit_trade', 'init', [$jsitemsmap]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('trade_config_hdr', 'block_playerhud'));
$mform->display();
echo $OUTPUT->footer();
