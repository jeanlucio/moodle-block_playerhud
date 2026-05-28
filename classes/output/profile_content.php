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

/**
 * Output class for the PlayerHUD section on the user profile page.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output;

use renderer_base;
use stdClass;

/**
 * Renderable for the PlayerHUD section on the user profile page.
 */
class profile_content implements \renderable, \templatable {
    /** @var int Maximum items shown in the profile section. */
    private const ITEM_LIMIT = 5;

    /** @var int Maximum overflow items loaded for the +N popover. */
    private const OVERFLOW_LIMIT = 20;

    /**
     * Constructor.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The user ID.
     */
    public function __construct(
        /** @var int The block instance ID. */
        private readonly int $blockinstanceid,
        /** @var int The user ID. */
        private readonly int $userid
    ) {
    }

    /**
     * Export data for the Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $DB;

        $player = $DB->get_record('block_playerhud_user', [
            'blockinstanceid' => $this->blockinstanceid,
            'userid' => $this->userid,
        ]);

        if (!$player || !$player->enable_gamification) {
            return (object) ['hasgamification' => false];
        }

        $bi = $DB->get_record('block_instances', ['id' => $this->blockinstanceid], 'configdata', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));

        $stats = \block_playerhud\game::get_game_stats($config, $this->blockinstanceid, (int)$player->currentxp);

        $level = (int)$stats['level'];
        $currentxp = (int)$player->currentxp;
        $totalgamexp = (int)$stats['total_game_xp'];
        $levelprogress = (int)$stats['progress'];
        $xpdisplay = $totalgamexp > 0 ? $currentxp . ' / ' . $totalgamexp . ' XP' : $currentxp . ' XP';

        $allitems = $this->get_recent_items(self::ITEM_LIMIT + self::OVERFLOW_LIMIT);
        $totalitems = $this->count_unique_items();
        $morecount = max(0, $totalitems - self::ITEM_LIMIT);

        $displayitems = array_slice($allitems, 0, self::ITEM_LIMIT);
        $overflowraw = array_slice($allitems, self::ITEM_LIMIT, self::OVERFLOW_LIMIT);

        $overflowjson = '';
        if (!empty($overflowraw)) {
            $overflowjsonitems = [];
            foreach ($overflowraw as $oi) {
                $overflowjsonitems[] = [
                    'n' => $oi['name'],
                    'i' => $oi['isimageint'],
                    'u' => $oi['isimageint'] ? $oi['imageurl'] : $oi['imagecontent'],
                ];
            }
            $overflowjson = json_encode($overflowjsonitems);
        }

        return (object) [
            'hasgamification' => true,
            'level' => $level,
            'maxlevels' => (int)$stats['max_levels'],
            'xpdisplay' => $xpdisplay,
            'levelprogress' => $levelprogress,
            'hasitems' => !empty($displayitems),
            'items' => array_values($displayitems),
            'hasmore' => $morecount > 0,
            'morebadge' => $morecount > 0 ? '+' . $morecount : '',
            'overflowjson' => $overflowjson,
        ];
    }

    /**
     * Returns the most recently collected unique items up to $limit.
     *
     * @param int $limit Maximum number of items to return.
     * @return array Array of item data arrays for the template.
     */
    private function get_recent_items(int $limit = self::ITEM_LIMIT): array {
        global $DB;

        $sql = "SELECT inv.itemid, MAX(inv.timecreated) AS lastcollected, MAX(inv.id) AS lastinvid
                  FROM {block_playerhud_inventory} inv
                  JOIN {block_playerhud_items} i ON inv.itemid = i.id
                 WHERE inv.userid = :userid
                   AND i.blockinstanceid = :bid
                   AND inv.source NOT IN ('revoked', 'consumed')
                   AND i.enabled = 1
                 GROUP BY inv.itemid
                 ORDER BY lastcollected DESC, lastinvid DESC";

        $rows = $DB->get_records_sql($sql, [
            'userid' => $this->userid,
            'bid' => $this->blockinstanceid,
        ], 0, $limit);

        if (empty($rows)) {
            return [];
        }

        $itemids = array_keys($rows);
        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'item');
        $items = $DB->get_records_select('block_playerhud_items', "id {$insql}", $inparams);

        if (empty($items)) {
            return [];
        }

        $context = \context_block::instance($this->blockinstanceid);
        $mediamap = \block_playerhud\utils::get_items_display_data($items, $context);

        $result = [];
        foreach ($itemids as $iid) {
            if (!isset($items[$iid])) {
                continue;
            }
            $item = $items[$iid];
            $media = $mediamap[$iid] ?? ['is_image' => false, 'url' => '', 'content' => ''];
            $lastts = (int)$rows[$iid]->lastcollected;
            $result[] = [
                'name' => format_string($item->name),
                'isimage' => (bool)$media['is_image'],
                'isimageint' => $media['is_image'] ? 1 : 0,
                'imageurl' => $media['is_image'] ? $media['url'] : '',
                'imagecontent' => !$media['is_image'] ? $media['content'] : '',
                'description' => format_text($item->description, FORMAT_HTML, ['context' => $context]),
                'lasttimestamp' => $lastts,
                'lastdate' => $lastts > 0 ? userdate($lastts, get_string('strftimedatefullshort', 'langconfig')) : '',
            ];
        }

        return $result;
    }

    /**
     * Returns the total number of distinct items owned by the user in this instance.
     *
     * @return int Total unique items count.
     */
    private function count_unique_items(): int {
        global $DB;

        return (int)$DB->count_records_sql(
            "SELECT COUNT(DISTINCT inv.itemid)
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} i ON inv.itemid = i.id
              WHERE inv.userid = :userid
                AND i.blockinstanceid = :bid
                AND inv.source NOT IN ('revoked', 'consumed')
                AND i.enabled = 1",
            ['userid' => $this->userid, 'bid' => $this->blockinstanceid]
        );
    }
}
