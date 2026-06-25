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

namespace block_playerhud\controller;

use context_block;
use moodle_url;

/**
 * Controller for trade (shop offer) creation and editing.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trades {
    /**
     * Handles the create/edit form for a trade.
     *
     * @return string HTML output.
     */
    public function handle_edit_form(): string {
        global $DB, $PAGE, $OUTPUT, $CFG;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $tradeid    = optional_param('tradeid', 0, PARAM_INT);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

        require_login($course);
        $context = context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);

        $pageurl = new moodle_url('/blocks/playerhud/edit_trade.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
        ]);
        if ($tradeid) {
            $pageurl->param('tradeid', $tradeid);
        }

        $PAGE->set_url($pageurl);
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('pluginname', 'block_playerhud'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->set_pagelayout('standard');

        $allitemsdata = $DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid, 'enabled' => 1]
        );
        $allmedia   = \block_playerhud\utils::get_items_display_data($allitemsdata, $context);
        $jsitemsmap = [];
        foreach ($allitemsdata as $it) {
            $media                        = $allmedia[$it->id];
            $jsitemsmap[(string) $it->id] = $media['is_image']
                ? $media['url']
                : 'EMOJI:' . strip_tags($media['content']);
        }

        $dbreqcount  = 0;
        $dbgivecount = 0;
        $requirements = [];
        $rewards      = [];

        if ($tradeid) {
            $requirements = $DB->get_records(
                'block_playerhud_trade_reqs',
                ['tradeid' => $tradeid],
                'id ASC'
            );
            $dbreqcount = count($requirements);

            $rewards = $DB->get_records(
                'block_playerhud_trade_rewards',
                ['tradeid' => $tradeid],
                'id ASC'
            );
            $dbgivecount = count($rewards);
        }

        $mform = new \block_playerhud\form\edit_trade_form(null, [
            'instanceid'   => $instanceid,
            'courseid'     => $courseid,
            'db_req_count' => $dbreqcount,
            'db_give_count' => $dbgivecount,
        ]);

        if ($tradeid && !$mform->is_submitted()) {
            $trade = $DB->get_record(
                'block_playerhud_trades',
                ['id' => $tradeid, 'blockinstanceid' => $instanceid],
                '*',
                MUST_EXIST
            );
            $data              = (array) $trade;
            $data['courseid']  = $courseid;
            $data['instanceid'] = $instanceid;
            $data['tradeid']   = $tradeid;

            $i = 0;
            foreach ($requirements as $req) {
                $data["req_itemid_$i"] = $req->itemid;
                $data["req_qty_$i"]    = $req->qty;
                $i++;
            }
            $data['repeats_req'] = max(3, $dbreqcount);

            $i = 0;
            foreach ($rewards as $rew) {
                $data["give_itemid_$i"] = $rew->itemid;
                $data["give_qty_$i"]    = $rew->qty;
                $i++;
            }
            $data['repeats_give'] = max(3, $dbgivecount);

            $mform->set_data($data);
        } else if (!$mform->is_submitted()) {
            $mform->set_data(['courseid' => $courseid, 'instanceid' => $instanceid]);
        }

        $returnurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'trades',
        ]);

        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            $this->save_trade($data, $instanceid);
            redirect(
                $returnurl,
                get_string('trade_saved', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $PAGE->requires->js_call_amd('block_playerhud/edit_trade', 'init');

        $output  = $OUTPUT->header();
        $output .= $OUTPUT->heading(get_string('trade_config_hdr', 'block_playerhud'));
        $output .= \html_writer::tag(
            'script',
            json_encode($jsitemsmap, JSON_HEX_TAG | JSON_HEX_AMP),
            ['type' => 'application/json', 'id' => 'block-playerhud-items-data']
        );
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Persists a trade record together with its requirements and rewards in a single transaction.
     *
     * @param \stdClass $data Form data from edit_trade_form.
     * @param int $instanceid The URL-validated block instance ID (from capability check).
     * @return int The created or updated trade ID.
     */
    public function save_trade(\stdClass $data, int $instanceid): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $record                   = new \stdClass();
        $record->blockinstanceid  = $instanceid;
        $record->name             = $data->name;
        $record->centralized      = $data->centralized;
        $record->onetime          = $data->onetime;
        $record->groupid          = $data->groupid;

        if (!empty($data->tradeid)) {
            $DB->get_record(
                'block_playerhud_trades',
                ['id' => $data->tradeid, 'blockinstanceid' => $instanceid],
                'id',
                MUST_EXIST
            );
            $record->id     = $data->tradeid;
            $DB->update_record('block_playerhud_trades', $record);
            $currenttradeid = $data->tradeid;
            $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $currenttradeid]);
            $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $currenttradeid]);
        } else {
            $record->timecreated = time();
            $currenttradeid      = $DB->insert_record('block_playerhud_trades', $record);
        }

        $validitemids = $DB->get_fieldset_select(
            'block_playerhud_items',
            'id',
            'blockinstanceid = ?',
            [$instanceid]
        );

        $reqstoinsert = [];
        for ($i = 0; $i < $data->repeats_req; $i++) {
            $itemid = (int)($data->{"req_itemid_$i"} ?? 0);
            if ($itemid <= 0 || !in_array($itemid, $validitemids)) {
                continue;
            }
            $req          = new \stdClass();
            $req->tradeid = $currenttradeid;
            $req->itemid  = $itemid;
            $req->qty     = max(1, (int)($data->{"req_qty_$i"} ?? 1));
            $reqstoinsert[] = $req;
        }
        if (!empty($reqstoinsert)) {
            $DB->insert_records('block_playerhud_trade_reqs', $reqstoinsert);
        }

        $rewardstoinsert = [];
        for ($i = 0; $i < $data->repeats_give; $i++) {
            $itemid = (int)($data->{"give_itemid_$i"} ?? 0);
            if ($itemid <= 0 || !in_array($itemid, $validitemids)) {
                continue;
            }
            $rew          = new \stdClass();
            $rew->tradeid = $currenttradeid;
            $rew->itemid  = $itemid;
            $rew->qty     = max(1, (int)($data->{"give_qty_$i"} ?? 1));
            $rewardstoinsert[] = $rew;
        }
        if (!empty($rewardstoinsert)) {
            $DB->insert_records('block_playerhud_trade_rewards', $rewardstoinsert);
        }

        $transaction->allow_commit();

        return (int) $currenttradeid;
    }

    /**
     * Deletes a trade together with its requirements, rewards and log entries.
     *
     * The trade must belong to the given block instance.
     *
     * @param int $tradeid The trade to delete.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    public function delete_trade(int $tradeid, int $instanceid): void {
        global $DB;

        $DB->get_record(
            'block_playerhud_trades',
            ['id' => $tradeid, 'blockinstanceid' => $instanceid],
            'id',
            MUST_EXIST
        );

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
            $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $tradeid]);
            $DB->delete_records('block_playerhud_trade_log', ['tradeid' => $tradeid]);
            $DB->delete_records('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
        }
    }
}
