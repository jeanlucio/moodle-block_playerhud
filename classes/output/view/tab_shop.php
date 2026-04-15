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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Shop tab output renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_shop implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player object.
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     */
    public function __construct($config, $player, $instanceid, $courseid) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template($output) {
        global $DB, $CFG;

        $context = \context_block::instance($this->instanceid);

        // 1. Get user RPG class (if module exists/enabled).
        $myclassid = 0;
        if ($DB->get_manager()->table_exists('block_playerhud_rpg_progress')) {
            $prog = $DB->get_record('block_playerhud_rpg_progress', [
                'userid' => $this->player->userid,
                'blockinstanceid' => $this->instanceid,
            ]);
            if ($prog) {
                $myclassid = $prog->classid;
            }
        }

        // 2. Fetch Trades using our optimized method.
        $trades = \block_playerhud\game::get_full_trades($this->instanceid);
        $tradesdata = [];

        // Fetch user inventory counts in a single query.
        $sqlinv = "SELECT itemid, COUNT(id) as qty
                     FROM {block_playerhud_inventory}
                    WHERE userid = :userid AND source != 'revoked'
                 GROUP BY itemid";
        $myinventory = $DB->get_records_sql_menu($sqlinv, ['userid' => $this->player->userid]);

        // Fetch completed trades (Distinct to avoid Moodle debugging warning on duplicates).
        $sql = "SELECT DISTINCT tradeid, 1 as completed
                  FROM {block_playerhud_trade_log}
                 WHERE userid = :userid";
        $completedtrades = $DB->get_records_sql_menu($sql, ['userid' => $this->player->userid]);

        // 3. BULK FETCH: Prepare all media for trades at once to avoid N+1 queries.
        $fakeitems = [];
        if ($trades) {
            foreach ($trades as $trade) {
                foreach ($trade->requirements as $req) {
                    $fakeitems[$req->itemid] = (object)['id' => $req->itemid, 'image' => $req->image];
                }
                foreach ($trade->rewards as $rew) {
                    $fakeitems[$rew->itemid] = (object)['id' => $rew->itemid, 'image' => $rew->image];
                }
            }
        }
        $allmedia = \block_playerhud\utils::get_items_display_data($fakeitems, $context);

        if ($trades) {
            foreach ($trades as $trade) {
                if ($trade->centralized != 1) {
                    continue; // Skip hidden/map-only trades.
                }

                // 4. Check class restrictions on rewards.
                $visibleforme = true;
                foreach ($trade->rewards as $rew) {
                    if (!empty($rew->required_class_id)) {
                        if (!\block_playerhud\utils::is_visible_for_class($rew->required_class_id, $myclassid)) {
                            $visibleforme = false;
                            break;
                        }
                    }
                }

                if (!$visibleforme) {
                    continue;
                }

                // 5. Check One-Time restriction.
                $iscompleted = false;
                if ($trade->onetime) {
                    $iscompleted = isset($completedtrades[$trade->id]);
                }

                // 6 & 9. Format Requirements and Check Affordability simultaneously.
                $reqsdata = [];
                $canafford = true;
                foreach ($trade->requirements as $req) {
                    $media = $allmedia[$req->itemid];

                    // Calculate affordability and define UI classes for visual feedback.
                    $myqty = isset($myinventory[$req->itemid]) ? $myinventory[$req->itemid] : 0;
                    $hasenough = ($myqty >= $req->qty);

                    if (!$hasenough) {
                        $canafford = false;
                    }

                    $reqsdata[] = [
                        'qty' => $req->qty,
                        'name' => format_string($req->name),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : strip_tags($media['content']),
                        'user_qty' => $myqty,
                        'qty_class' => $hasenough ? 'text-success' : 'text-danger',
                    ];
                }

                // 7. Format Rewards using the bulk-loaded media array.
                $rewsdata = [];
                foreach ($trade->rewards as $rew) {
                    $media = $allmedia[$rew->itemid];
                    $rewsdata[] = [
                        'qty' => $rew->qty,
                        'name' => format_string($rew->name),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : strip_tags($media['content']),
                    ];
                }

                // 8. Action URL for performing the trade.
                $processurl = new moodle_url('/blocks/playerhud/process_trade.php', [
                    'instanceid' => $this->instanceid,
                    'courseid' => $this->courseid,
                    'tradeid' => $trade->id,
                    'sesskey' => sesskey(),
                ]);

                // Compile data for this trade.
                $tradesdata[] = [
                    'id' => $trade->id,
                    'name' => format_string($trade->name),
                    'requirements' => $reqsdata,
                    'rewards' => $rewsdata,
                    'is_completed' => $iscompleted,
                    'can_afford' => $canafford,
                    'action_url' => $processurl->out(false),
                ];
            }
        }

        // Return unified structure for Mustache.
        return [
            'has_trades' => !empty($tradesdata),
            'trades' => $tradesdata,
            'str_warning' => get_string('shop_xp_warning', 'block_playerhud'),
            'str_pay' => get_string('shop_pay', 'block_playerhud'),
            'str_receive' => get_string('shop_receive', 'block_playerhud'),
            'str_redeemed' => get_string('trade_redeemed', 'block_playerhud'),
            'str_perform' => get_string('trade_perform', 'block_playerhud'),
            'str_missing_items' => get_string('trade_missing_items', 'block_playerhud'),
            'str_empty' => get_string('shop_empty', 'block_playerhud'),
        ];
    }

    /**
     * Display method.
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/tab_shop', $this->export_for_template($OUTPUT));
    }
}
