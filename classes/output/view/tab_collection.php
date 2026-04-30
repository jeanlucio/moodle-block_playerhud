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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Collection tab output renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_collection implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var int Block instance ID. */
    protected $instanceid;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player object.
     * @param int $instanceid Block instance ID.
     */
    public function __construct($config, $player, $instanceid) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $CFG, $PAGE;

        // 1. Capture sort parameter (Default: recent).
        $currentsort = optional_param('sort', 'recent', PARAM_ALPHANUMEXT);

        // 2. Fetch Inventory from DB, scoped to this block instance.
        $sql = "SELECT inv.*, d.maxusage as drop_maxusage
                  FROM {block_playerhud_inventory} inv
                  JOIN {block_playerhud_items} it ON it.id = inv.itemid
             LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
                 WHERE inv.userid = :userid
                   AND it.blockinstanceid = :instanceid
                   AND inv.source != 'revoked'";

        $rawinventory = $DB->get_records_sql($sql, [
            'userid' => $this->player->userid,
            'instanceid' => $this->instanceid,
        ]);

        $inventorybyitem = [];
        if ($rawinventory) {
            foreach ($rawinventory as $inv) {
                $inventorybyitem[$inv->itemid][] = $inv;
            }
        }

        // Fetch all items.
        $allitems = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid], 'xp ASC');

        // Check player class for visibility.
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

        // BULK FETCH: Check which items have infinite drops (Zero N+1 Optimization).
        $sqlinf = "SELECT DISTINCT itemid, 1 as is_inf
                     FROM {block_playerhud_drops}
                    WHERE blockinstanceid = :pid AND maxusage = 0";
        $infinitedrops = $DB->get_records_sql_menu($sqlinf, ['pid' => $this->instanceid]);

        $itemsdata = [];
        $context = \context_block::instance($this->instanceid);

        if ($allitems) {
            // Bulk load media for all collection items.
            $allmedia = \block_playerhud\utils::get_items_display_data($allitems, $context);

            foreach ($allitems as $item) {
                $usercopies = isset($inventorybyitem[$item->id]) ? $inventorybyitem[$item->id] : [];
                $totalcount = count($usercopies);

                // Visibility Rules (If item not owned).
                if ($totalcount == 0) {
                    if (!$item->enabled) {
                        continue;
                    }
                    if (!\block_playerhud\utils::is_visible_for_class($item->required_class_id, $myclassid)) {
                        continue;
                    }
                }

                $media = $allmedia[$item->id];

                // Use the pre-loaded bulk array.
                $isinfiniteconfig = isset($infinitedrops[$item->id]);
                $jspayload = $media['is_image'] ? $media['url'] : strip_tags($media['content']);

                // Separate count (Finite vs Infinite).
                $countinfinite = 0;
                $countfinite = 0;
                $lastts = 0;

                foreach ($usercopies as $copy) {
                    if ($copy->timecreated > $lastts) {
                        $lastts = $copy->timecreated;
                    }
                    if (!is_null($copy->drop_maxusage) && $copy->drop_maxusage == 0) {
                        $countinfinite++;
                    } else {
                        $countfinite++;
                    }
                }

                // Format name for sorting.
                $visiblename = format_string($item->name);
                $sortname = strip_tags($visiblename);

                // Uncollected Item Logic.
                if ($totalcount == 0) {
                    $itemobj = [
                        'card_class' => 'ph-missing',
                        'date_str' => '&nbsp;',
                    ];

                    if ($item->secret) {
                        $itemobj['name'] = get_string('secret_name', 'block_playerhud');
                        $sortname = 'zzzz_secret';
                        $itemobj['xp_text'] = "???";
                        $itemobj['description'] = get_string('secret_desc', 'block_playerhud');
                        $itemobj['is_image'] = false;
                        $itemobj['image_content'] = '❓';
                        $itemobj['data_image_payload'] = '❓';
                    } else {
                        $itemobj['name'] = $visiblename;
                        $itemobj['xp_text'] = "{$item->xp} XP";
                        $itemobj['description'] = format_text($item->description, FORMAT_HTML);
                        $itemobj['is_image'] = $media['is_image'];
                        $itemobj['image_url'] = $media['is_image'] ? $media['url'] : '';
                        $itemobj['image_content'] = $media['is_image'] ? '' : $media['content'];
                        $itemobj['data_image_payload'] = $jspayload;
                    }
                } else {
                    // Collected Item Logic.
                    $itemobj = [
                        'card_class' => 'ph-owned',
                        'name' => $visiblename,
                        'xp_text' => "{$item->xp} XP",
                        'description' => format_text($item->description, FORMAT_HTML),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : $media['content'],
                        'data_image_payload' => $jspayload,
                    ];

                    // Origins.
                    $itemobj['origin_map'] = 0;
                    $itemobj['origin_shop'] = 0;
                    $itemobj['origin_quest'] = 0;
                    $itemobj['origin_teacher'] = 0;
                    $itemobj['origin_legacy'] = 0;
                    foreach ($usercopies as $copy) {
                        $src = $copy->source ?? '';
                        if ($src == 'map') {
                            $itemobj['origin_map']++;
                        } else if ($src == 'shop') {
                            $itemobj['origin_shop']++;
                        } else if ($src == 'quest') {
                            $itemobj['origin_quest']++;
                        } else if ($src == 'teacher') {
                            $itemobj['origin_teacher']++;
                        } else {
                            $itemobj['origin_legacy']++;
                        }
                    }
                    if (
                        $itemobj['origin_map'] ||
                        $itemobj['origin_shop'] ||
                        $itemobj['origin_quest'] ||
                        $itemobj['origin_teacher'] ||
                        $itemobj['origin_legacy']
                    ) {
                        $itemobj['has_origins'] = true;
                    }

                    // New Badge.
                    $lastview = $this->player->last_inventory_view ?? 0;
                    if ($lastts > $lastview) {
                        $itemobj['badge_new'] = true;
                    }

                    // Archived Badge.
                    if (!$item->enabled) {
                        $itemobj['badge_archived'] = true;
                        $itemobj['card_class'] .= ' ph-item-archived';
                    }

                    $itemobj['date_str'] = userdate($lastts, get_string('strftimedatefullshort', 'langconfig'));
                }

                // Common data for sorting.
                $itemobj['id'] = $item->id;
                $itemobj['sort_name'] = $sortname;
                if ($totalcount == 0 && $item->secret) {
                    $itemobj['raw_xp'] = -1;
                } else {
                    $itemobj['raw_xp'] = (int)$item->xp;
                }

                $itemobj['count'] = $totalcount;
                $itemobj['timestamp'] = $lastts;

                $itemobj['count_infinite'] = $countinfinite;
                $itemobj['count_finite'] = $countfinite;
                $itemobj['has_infinite_copies'] = ($countinfinite > 0);
                $itemobj['has_finite_copies'] = ($countfinite > 0);
                $itemobj['is_infinite_type'] = $isinfiniteconfig;

                $itemobj['is_image_bool'] = $media['is_image'] ? 1 : 0;
                $itemobj['tabindex'] = '0';

                $itemsdata[] = $itemobj;
            }
        }

        // 3. Robust Sorting with core_collator for locale-aware sorting.
        usort($itemsdata, function ($a, $b) use ($currentsort) {
            switch ($currentsort) {
                case 'name_asc':
                    return strcmp($a['sort_name'], $b['sort_name']);

                case 'name_desc':
                    return strcmp($b['sort_name'], $a['sort_name']);

                case 'count_desc':
                    if ($a['count'] == $b['count']) {
                        return strcmp($a['sort_name'], $b['sort_name']);
                    }
                    return $b['count'] <=> $a['count'];

                case 'count_asc':
                    if ($a['count'] == $b['count']) {
                        return strcmp($a['sort_name'], $b['sort_name']);
                    }
                    return $a['count'] <=> $b['count'];

                case 'acquired':
                    $hasa = ($a['count'] > 0) ? 1 : 0;
                    $hasb = ($b['count'] > 0) ? 1 : 0;
                    if ($hasa == $hasb) {
                        return strcmp($a['sort_name'], $b['sort_name']);
                    }
                    return $hasb <=> $hasa;

                case 'missing':
                    $hasa = ($a['count'] > 0) ? 1 : 0;
                    $hasb = ($b['count'] > 0) ? 1 : 0;
                    if ($hasa == $hasb) {
                        return strcmp($a['sort_name'], $b['sort_name']);
                    }
                    return $hasa <=> $hasb;

                case 'recent':
                default:
                    // Default: Recent.
                    return $b['timestamp'] <=> $a['timestamp'];
            }
        });

        // 4. Prepare Filter Dropdown Data (No XP options).
        $url = new moodle_url($PAGE->url);
        $url->param('tab', 'collection');

        $options = [
            'recent'     => get_string('sort_recent', 'block_playerhud'),
            'acquired'   => get_string('sort_acquired', 'block_playerhud'),
            'missing'    => get_string('sort_missing', 'block_playerhud'),
            'count_desc' => get_string('sort_count_desc', 'block_playerhud'),
            'count_asc'  => get_string('sort_count_asc', 'block_playerhud'),
            'name_asc'   => get_string('sort_name_asc', 'block_playerhud'),
            'name_desc'  => get_string('sort_name_desc', 'block_playerhud'),
        ];

        $sortoptions = [];
        foreach ($options as $val => $label) {
            $u = new moodle_url($url, ['sort' => $val]);
            $sortoptions[] = [
                'value' => $u->out(false),
                'label' => $label,
                'selected' => ($val === $currentsort),
            ];
        }

        return [
            'items' => $itemsdata,
            'has_items' => !empty($itemsdata),
            'sort_options' => $sortoptions,
            'show_filter' => !empty($itemsdata),
        ];
    }
}
