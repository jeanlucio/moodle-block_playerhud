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
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

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

        // 2. Fetch Trades using our new optimized method.
        $trades = \block_playerhud\game::get_full_trades($this->instanceid);
        $tradesdata = [];

        if ($trades) {
            foreach ($trades as $trade) {
                if ($trade->centralized != 1) {
                    continue; // Skip hidden/map-only trades.
                }

                // 3. Check class restrictions on rewards.
                $visibleforme = true;
                foreach ($trade->rewards as $rew) {
                    if (!empty($rew->required_class_id)) {
                        if (!block_playerhud_is_visible_for_class($rew->required_class_id, $myclassid)) {
                            $visibleforme = false;
                            break;
                        }
                    }
                }

                if (!$visibleforme) {
                    continue;
                }

                // 4. Check One-Time restriction.
                $iscompleted = false;
                if ($trade->onetime) {
                    $iscompleted = $DB->record_exists('block_playerhud_trade_log', [
                        'tradeid' => $trade->id,
                        'userid' => $this->player->userid,
                    ]);
                }

                // 5. Format Requirements.
                $reqsdata = [];
                foreach ($trade->requirements as $req) {
                    $fakeitem = (object)['id' => $req->itemid, 'image' => $req->image];
                    $media = \block_playerhud\utils::get_item_display_data($fakeitem, $context);

                    $reqsdata[] = [
                        'qty' => $req->qty,
                        'name' => format_string($req->name),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : strip_tags($media['content']),
                    ];
                }

                // 6. Format Rewards.
                $rewsdata = [];
                foreach ($trade->rewards as $rew) {
                    $fakeitem = (object)['id' => $rew->itemid, 'image' => $rew->image];
                    $media = \block_playerhud\utils::get_item_display_data($fakeitem, $context);

                    $rewsdata[] = [
                        'qty' => $rew->qty,
                        'name' => format_string($rew->name),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : strip_tags($media['content']),
                    ];
                }

                // 7. Action URL.
                $processurl = new moodle_url('/blocks/playerhud/process_trade.php', [
                    'instanceid' => $this->instanceid,
                    'courseid' => $this->courseid,
                    'tradeid' => $trade->id,
                    'sesskey' => sesskey(),
                ]);

                $tradesdata[] = [
                    'id' => $trade->id,
                    'name' => format_string($trade->name),
                    'requirements' => $reqsdata,
                    'rewards' => $rewsdata,
                    'is_completed' => $iscompleted,
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
