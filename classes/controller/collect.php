<?php
namespace block_playerhud\controller;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

class collect {
    public function execute() {
        global $USER, $DB, $PAGE;

        // 1. Parameters
        $instanceid = required_param('instanceid', PARAM_INT);
        $dropid     = required_param('dropid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);
        $isajax     = optional_param('ajax', 0, PARAM_INT);

        // 2. Security
        require_login($courseid);
        require_sesskey();

        // 3. Logic Delegation
        $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
        
        try {
            // Validate Logic (Load Records)
            $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
            $item = $DB->get_record('block_playerhud_items', ['id' => $drop->itemid], '*', MUST_EXIST);

            if (!$item->enabled) {
                throw new \moodle_exception('itemnotfound', 'block_playerhud');
            }

            // Game Rules Check
            $this->process_game_rules($drop, $USER->id);

            // Execute Transaction
            $earned_xp = $this->process_transaction($drop, $item, $instanceid, $USER->id);

            // Feedback
            $msgParams = new \stdClass();
            $msgParams->name = format_string($item->name);
            $msgParams->xp = ($earned_xp > 0) ? " (+{$earned_xp} XP)" : "";
            
            $this->respond($isajax, true, get_string('collected_msg', 'block_playerhud', $msgParams), $returnurl);

        } catch (\Exception $e) {
            $this->respond($isajax, false, $e->getMessage(), $returnurl);
        }
    }

    private function process_game_rules($drop, $userid) {
        global $DB;
        
        $inventory = $DB->get_records('block_playerhud_inventory', [
            'userid' => $userid, 
            'dropid' => $drop->id
        ], 'timecreated DESC');

        $count = count($inventory);
        $lastcollected = reset($inventory);

        // Max Usage
        if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        // Cooldown
        if ($lastcollected && $drop->respawntime > 0) {
            $readytime = $lastcollected->timecreated + $drop->respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', $minutesleft);
            }
        }
    }

    private function process_transaction($drop, $item, $instanceid, $userid) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $newinv = new \stdClass();
            $newinv->userid = $userid;
            $newinv->itemid = $item->id;
            $newinv->dropid = $drop->id;
            $newinv->timecreated = time();
            $newinv->source = 'map';
            $DB->insert_record('block_playerhud_inventory', $newinv);

            $xpgain = 0;
            if ($item->xp > 0 && $drop->maxusage > 0) {
                $xpgain = $item->xp;
                $player = \block_playerhud\game::get_player($instanceid, $userid);
                $newxp = $player->currentxp + $xpgain;
                $DB->set_field('block_playerhud_user', 'currentxp', $newxp, ['id' => $player->id]);
            }

            $transaction->allow_commit();
            return $xpgain;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    private function respond($ajax, $success, $message, $url) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => $success, 'message' => $message]);
            die();
        } else {
            redirect($url, $message, $success ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
        }
    }
}
