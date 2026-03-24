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

namespace block_playerhud;

/**
 * Trade manager class for PlayerHUD block.
 * Handles shop, economy, and atomic transactions.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trade_manager {
    /**
     * Get all trades with their requirements and rewards for a specific block instance.
     * Optimized to avoid N+1 query problems using single batch queries.
     *
     * @param int $blockinstanceid The block instance ID.
     * @return array Array of trade objects populated with requirements and rewards.
     */
    public static function get_full_trades(int $blockinstanceid): array {
        global $DB;

        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $blockinstanceid], 'name ASC');
        if (!$trades) {
            return [];
        }

        $tradeids = array_keys($trades);
        [$insql, $inparams] = $DB->get_in_or_equal($tradeids, SQL_PARAMS_NAMED, 'trd');

        foreach ($trades as $trade) {
            $trade->requirements = [];
            $trade->rewards = [];
        }

        $sqlreq = "SELECT req.id, req.tradeid, req.itemid, req.qty,
                          i.name, i.image, i.required_class_id
                     FROM {block_playerhud_trade_reqs} req
                     JOIN {block_playerhud_items} i ON req.itemid = i.id
                    WHERE req.tradeid $insql
                 ORDER BY req.id ASC";

        $requirements = $DB->get_records_sql($sqlreq, $inparams);

        if ($requirements) {
            foreach ($requirements as $req) {
                if (isset($trades[$req->tradeid])) {
                    $trades[$req->tradeid]->requirements[] = $req;
                }
            }
        }

        $sqlrew = "SELECT rew.id, rew.tradeid, rew.itemid, rew.qty,
                          i.name, i.image, i.required_class_id
                     FROM {block_playerhud_trade_rewards} rew
                     JOIN {block_playerhud_items} i ON rew.itemid = i.id
                    WHERE rew.tradeid $insql
                 ORDER BY rew.id ASC";

        $rewards = $DB->get_records_sql($sqlrew, $inparams);

        if ($rewards) {
            foreach ($rewards as $rew) {
                if (isset($trades[$rew->tradeid])) {
                    $trades[$rew->tradeid]->rewards[] = $rew;
                }
            }
        }

        return $trades;
    }

    /**
     * Executes a trade transaction securely, checking balances, limits, and enforcing atomic operations.
     *
     * @param int $tradeid The trade ID.
     * @param int $userid The user ID.
     * @param int $instanceid The block instance ID.
     * @param int $courseid The course ID (required for group checks).
     * @return string A comma-separated string of the received items.
     * @throws \moodle_exception If any business rule is violated.
     */
    public static function execute_trade(int $tradeid, int $userid, int $instanceid, int $courseid): string {
        global $DB;

        $trade = $DB->get_record('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
        if (!$trade) {
            throw new \moodle_exception('error_trade_invalid', 'block_playerhud');
        }

        if ($trade->groupid != 0) {
            $authorized = false;
            if ($trade->groupid > 0) {
                if (groups_is_member($trade->groupid, $userid)) {
                    $authorized = true;
                }
            } else {
                $groupingid = abs($trade->groupid);
                $usergroups = groups_get_all_groups($courseid, $userid, $groupingid);
                if (!empty($usergroups)) {
                    $authorized = true;
                }
            }

            if (!$authorized) {
                throw new \moodle_exception('error_trade_group', 'block_playerhud');
            }
        }

        $requirements = $DB->get_records('block_playerhud_trade_reqs', ['tradeid' => $trade->id]);
        $rewards = $DB->get_records('block_playerhud_trade_rewards', ['tradeid' => $trade->id]);

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

        $itemsmap = [];
        if (!empty($allitemids)) {
            [$itminsql, $itminparams] = $DB->get_in_or_equal($allitemids, SQL_PARAMS_NAMED, 'itm');
            $itemsmap = $DB->get_records_select('block_playerhud_items', "id $itminsql", $itminparams);
        }

        $myclassid = 0;
        if ($DB->get_manager()->table_exists('block_playerhud_rpg_progress')) {
            $rpgprog = $DB->get_record('block_playerhud_rpg_progress', [
                'userid' => $userid,
                'blockinstanceid' => $instanceid,
            ]);
            if ($rpgprog) {
                $myclassid = $rpgprog->classid;
            }
        }

        if ($rewards) {
            foreach ($rewards as $rew) {
                if (isset($itemsmap[$rew->itemid]) && !empty($itemsmap[$rew->itemid]->required_class_id)) {
                    $reqclass = $itemsmap[$rew->itemid]->required_class_id;
                    if (!block_playerhud_is_visible_for_class($reqclass, $myclassid)) {
                        throw new \moodle_exception('error_trade_class', 'block_playerhud');
                    }
                }
            }
        }

        if ($trade->onetime == 1) {
            $alreadytraded = $DB->record_exists('block_playerhud_trade_log', ['tradeid' => $trade->id, 'userid' => $userid]);
            if ($alreadytraded) {
                throw new \moodle_exception('error_trade_onetime', 'block_playerhud');
            }
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('block_playerhud');
        $lockkey = 'trade_usr_' . $userid . '_inst_' . $instanceid;
        $lock = $lockfactory->get_lock($lockkey, 10);

        if (!$lock) {
            throw new \moodle_exception('error_trade_lock', 'block_playerhud');
        }

        try {
            $itemstoremove = [];
            $userinventorymap = [];

            if ($requirements && !empty($allitemids)) {
                [$invinsql, $invinparams] = $DB->get_in_or_equal($allitemids, SQL_PARAMS_NAMED, 'rinv');
                $invparams = array_merge(['userid' => $userid], $invinparams);

                $sql = "SELECT id, itemid
                          FROM {block_playerhud_inventory}
                         WHERE userid = :userid AND itemid $invinsql
                      ORDER BY timecreated ASC";

                $invrecords = $DB->get_records_sql($sql, $invparams);

                if ($invrecords) {
                    foreach ($invrecords as $inv) {
                        $userinventorymap[$inv->itemid][] = $inv->id;
                    }
                }

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

                    $sliced = array_slice($userinventorymap[$req->itemid], 0, $req->qty);
                    $itemstoremove = array_merge($itemstoremove, $sliced);
                }
            }

            $transaction = $DB->start_delegated_transaction();

            if (!empty($itemstoremove)) {
                $DB->delete_records_list('block_playerhud_inventory', 'id', $itemstoremove);
            }

            $rewardsnames = [];
            $newinventories = [];

            if ($rewards) {
                $now = time();
                foreach ($rewards as $rew) {
                    if (!isset($itemsmap[$rew->itemid])) {
                        continue;
                    }

                    $rewarditem = $itemsmap[$rew->itemid];

                    for ($i = 0; $i < $rew->qty; $i++) {
                        $newinv = new \stdClass();
                        $newinv->userid = $userid;
                        $newinv->itemid = $rew->itemid;
                        $newinv->dropid = 0;
                        $newinv->source = 'shop';
                        $newinv->timecreated = $now;

                        $newinventories[] = $newinv;
                    }
                    $rewardsnames[] = "{$rew->qty}x " . format_string($rewarditem->name);
                }
            }

            if (!empty($newinventories)) {
                $DB->insert_records('block_playerhud_inventory', $newinventories);
            }

            $log = new \stdClass();
            $log->tradeid = $trade->id;
            $log->userid = $userid;
            $log->timecreated = time();
            $DB->insert_record('block_playerhud_trade_log', $log);

            $transaction->allow_commit();

            return implode(', ', $rewardsnames);
        } catch (\Exception $e) {
            if (isset($transaction)) {
                $transaction->rollback($e);
            }
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
