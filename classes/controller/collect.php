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

namespace block_playerhud\controller;

use moodle_url;

/**
 * Controller for handling item collection logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect {
    /**
     * Executes the main collection logic.
     */
    public function execute() {
        global $USER, $DB;

        // 1. Parameters.
        $instanceid = required_param('instanceid', PARAM_INT);
        $dropid     = required_param('dropid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);
        $isajax     = optional_param('ajax', 0, PARAM_INT); // Detects if called via AJAX.

        // 2. Security.
        require_login($courseid);
        require_sesskey();
        $context = \context_block::instance($instanceid);
        require_capability('block/playerhud:view', $context);

        // Return URL (if not AJAX).
        $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);

        try {
            // Validate Drop and Item.
            $drop = $DB->get_record(
                'block_playerhud_drops',
                ['id' => $dropid, 'blockinstanceid' => $instanceid],
                '*',
                MUST_EXIST
            );
            $item = $DB->get_record(
                'block_playerhud_items',
                ['id' => $drop->itemid],
                '*',
                MUST_EXIST
            );

            if (!$item->enabled) {
                throw new \moodle_exception('itemnotfound', 'block_playerhud');
            }

            // Check Game Rules (Limits and Cooldown).
            $this->process_game_rules($drop, $USER->id);

            // Execute Transaction (Give item and XP).
            $earnedxp = $this->process_transaction($drop, $item, $instanceid, $USER->id);

            // Prepare Feedback Message.
            $msgparams = new \stdClass();
            $msgparams->name = format_string($item->name);
            $msgparams->xp = ($earnedxp > 0) ? " (+{$earnedxp} XP)" : "";
            $message = get_string('collected_msg', 'block_playerhud', $msgparams);

            // Data for frontend (AJAX).
            $gamedata = null;
            $itemdata = null; // Visual item data for sidebar.
            $cooldowndeadline = 0;
            $limitreached = false;

            if ($isajax) {
                // A. Player Data.
                $player = \block_playerhud\game::get_player($instanceid, $USER->id);
                $bi = $DB->get_record('block_instances', ['id' => $instanceid]);
                $config = unserialize(base64_decode($bi->configdata));
                if (!$config) {
                    $config = new \stdClass();
                }

                $stats = \block_playerhud\game::get_game_stats(
                    $config,
                    $instanceid,
                    $player->currentxp
                );

                // We send total_game_xp as 'xp_target' to JS.
                $gamedata = [
                    'currentxp' => $player->currentxp,
                    'level' => $stats['level'],
                    'max_levels' => $stats['max_levels'],
                    'xp_target' => $stats['total_game_xp'], // Points to Grand Total.
                    'progress' => $stats['progress'],
                    'total_game_xp' => $stats['total_game_xp'],
                    'level_class' => $stats['level_class'], // Level color.
                    // Check win condition.
                    'is_win' => ($player->currentxp >= $stats['total_game_xp'] && $stats['total_game_xp'] > 0),
                ];

                // B. Limits and Cooldown Check.
                $count = $DB->count_records('block_playerhud_inventory', [
                    'userid' => $USER->id,
                    'dropid' => $drop->id,
                ]);

                if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
                    $limitreached = true;
                }
                if (!$limitreached && $drop->respawntime > 0) {
                    $cooldowndeadline = time() + $drop->respawntime;
                }

                // C. Prepare visual Item data (to inject into sidebar).
                $context = \context_block::instance($instanceid);
                $media = \block_playerhud\utils::get_item_display_data($item, $context);
                $isimage = $media['is_image'] ? 1 : 0;
                $imageurl = $media['is_image'] ? $media['url'] : strip_tags($media['content']);
                $strxp = get_string('xp', 'block_playerhud');
                $desc = !empty($item->description) ? format_text($item->description, FORMAT_HTML) : '';

                $itemdata = [
                    'name' => format_string($item->name),
                    'xp' => $item->xp . ' ' . $strxp,
                    'image' => $imageurl,
                    'isimage' => $isimage,
                    'description' => $desc,
                    'date' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
                    'timestamp' => time(),
                ];
            }

            $this->respond(
                $isajax,
                true,
                $message,
                $returnurl,
                $gamedata,
                $cooldowndeadline,
                $limitreached,
                $itemdata
            );
        } catch (\Exception $e) {
            $this->respond($isajax, false, $e->getMessage(), $returnurl);
        }
    }

    /**
     * Checks if the user can collect the item (Cooldown and Limits).
     *
     * @param \stdClass $drop The drop object.
     * @param int $userid The user ID.
     * @throws \moodle_exception If limits are reached or cooldown is active.
     */
    private function process_game_rules($drop, $userid) {
        global $DB;
        $inventory = $DB->get_records('block_playerhud_inventory', [
            'userid' => $userid,
            'dropid' => $drop->id,
        ], 'timecreated DESC');

        $count = count($inventory);
        $lastcollected = reset($inventory);

        if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        if ($lastcollected && $drop->respawntime > 0) {
            $readytime = $lastcollected->timecreated + $drop->respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', '', $minutesleft);
            }
        }
    }

    /**
     * Inserts into inventory and awards XP.
     *
     * @param \stdClass $drop The drop object.
     * @param \stdClass $item The item object.
     * @param int $instanceid The block instance ID.
     * @param int $userid The user ID.
     * @return int The amount of XP earned.
     * @throws \Exception If transaction fails.
     */
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

            // XP Logic Protected.
            $xpgain = 0;

            // Golden Rule: If drop is infinite (maxusage == 0), XP gain is FORCED to 0.
            // This allows the item to have 100 XP (for finite drops/quests),
            // but not break the game on infinite drops.
            $isinfinitedrop = ((int)$drop->maxusage === 0);

            if ($item->xp > 0 && !$isinfinitedrop) {
                $xpgain = $item->xp;
                $player = \block_playerhud\game::get_player($instanceid, $userid);

                // Update full object to register tie-breaker time.
                $player->currentxp += $xpgain;
                $player->timemodified = time(); // Essential for time-based ranking!

                $DB->update_record('block_playerhud_user', $player);
            }

            $transaction->allow_commit();
            return $xpgain;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Sends the response (JSON or Redirect).
     *
     * @param int $ajax Whether it is an AJAX request.
     * @param bool $success Success status.
     * @param string $message Feedback message.
     * @param moodle_url $url Return URL.
     * @param array|null $data Game data.
     * @param int $cooldowndeadline Timestamp when cooldown ends.
     * @param bool $limitreached Whether limit is reached.
     * @param array|null $itemdata Visual item data.
     */
    private function respond(
        $ajax,
        $success,
        $message,
        $url,
        $data = null,
        $cooldowndeadline = 0,
        $limitreached = false,
        $itemdata = null
    ) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'game_data' => $data,
                'item_data' => $itemdata, // New field.
                'cooldown_deadline' => $cooldowndeadline,
                'limit_reached' => $limitreached,
            ]);
            die();
        } else {
            if ($success) {
                $type = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $type = \core\output\notification::NOTIFY_ERROR;
            }
            redirect($url, $message, $type);
        }
    }
}
