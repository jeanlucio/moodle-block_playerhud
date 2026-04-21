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

namespace block_playerhud\output\manage;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Trades tab management (Teacher View).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_trades implements renderable, templatable {
    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param int $instanceid
     * @param int $courseid
     * @param string $sort
     * @param string $dir
     */
    public function __construct($instanceid, $courseid, $sort = '', $dir = '') {
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
        global $CFG, $PAGE;

        $context = \context_block::instance($this->instanceid);
        $trades = \block_playerhud\game::get_full_trades($this->instanceid);
        $tradesdata = [];

        // 1. BULK FETCH: Prepare all media for trades at once to avoid N+1 queries.
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
                // 2. Process Requirements.
                $reqsdata = [];
                foreach ($trade->requirements as $req) {
                    $media = $allmedia[$req->itemid];

                    $reqsdata[] = [
                        'qty' => $req->qty,
                        'name' => format_string($req->name),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : strip_tags($media['content']),
                    ];
                }

                // 3. Process Rewards.
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

                // 4. URLs and Actions.
                $editurl = new moodle_url('/blocks/playerhud/edit_trade.php', [
                    'courseid' => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'tradeid' => $trade->id,
                ]);

                $delurl = new moodle_url('/blocks/playerhud/manage.php', [
                    'id' => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'action' => 'delete_trade',
                    'tradeid' => $trade->id,
                    'sesskey' => sesskey(),
                ]);

                $msgraw = get_string('deletecheck', 'moodle', format_string($trade->name));

                $secureraw = $trade->id . '_' . $trade->timecreated;
                $securecode = strtoupper(substr(md5($secureraw), 0, 6));

                $tradesdata[] = [
                    'id' => $trade->id,
                    'name' => format_string($trade->name),
                    'is_centralized' => ($trade->centralized == 1),
                    'is_onetime' => ($trade->onetime == 1),
                    'shortcode' => "[PLAYERHUD_TRADE code={$securecode}]",
                    'url_edit' => $editurl->out(false),
                    'url_delete' => $delurl->out(false),
                    'confirm_msg' => s($msgraw),
                    'requirements' => $reqsdata,
                    'rewards' => $rewsdata,
                ];
            }
        }

        // 5. Global UI Data & JS Injection.
        $addurl = new moodle_url('/blocks/playerhud/edit_trade.php', [
            'courseid' => $this->courseid,
            'instanceid' => $this->instanceid,
        ]);

        $stronetime = str_replace('?', '', get_string('one_time_trade', 'block_playerhud'));

        $jsvars = [
            'strings' => [
                'confirm_title' => get_string('confirmation', 'admin'),
                'yes' => get_string('yes'),
                'cancel' => get_string('cancel'),
            ],
        ];

        $PAGE->requires->js_call_amd('block_playerhud/manage_trades', 'init', [$jsvars]);

        return [
            'has_trades' => !empty($tradesdata),
            'trades' => $tradesdata,
            'url_add' => $addurl->out(false),
            'summary_text' => get_string('trades_summary', 'block_playerhud', count($tradesdata)),
            'summary_hint' => get_string('trades_summary_hint', 'block_playerhud'),
            'str_title' => get_string('tab_trades', 'block_playerhud'),
            'str_add' => get_string('add_trade', 'block_playerhud'),
            'str_shop' => get_string('tab_shop', 'block_playerhud'),
            'str_hidden' => get_string('hidden', 'block_playerhud'),
            'str_onetime' => $stronetime,
            'str_unlimited' => get_string('unlimited', 'block_playerhud'),
            'str_pay' => get_string('shop_pay', 'block_playerhud'),
            'str_receive' => get_string('shop_receive', 'block_playerhud'),
            'str_copy' => get_string('gen_copy', 'block_playerhud'),
            'str_edit' => get_string('edit'),
            'str_delete' => get_string('delete'),
            'str_empty' => get_string('shop_empty', 'block_playerhud'),
        ];
    }

    /**
     * Display method required by manage.php controller pattern.
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/tab_trades', $this->export_for_template($OUTPUT));
    }
}
