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
 * Collection tab output renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

        // 1. Capture sort parameter (Default: xp_asc).
        $currentsort = optional_param('sort', 'xp_asc', PARAM_ALPHANUMEXT);

        // 2. Fetch Inventory from DB.
        // JOIN to know if the origin is an infinite drop.
        $sql = "SELECT inv.*, d.maxusage as drop_maxusage
                  FROM {block_playerhud_inventory} inv
             LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
                 WHERE inv.userid = :userid";

        $rawinventory = $DB->get_records_sql($sql, ['userid' => $this->player->userid]);

        $inventorybyitem = [];
        if ($rawinventory) {
            foreach ($rawinventory as $inv) {
                $inventorybyitem[$inv->itemid][] = $inv;
            }
        }

        // Fetch all items (Initial SQL order doesn't matter as we will resort).
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

        $itemsdata = [];
        $context = \context_block::instance($this->instanceid);

        if ($allitems) {
            foreach ($allitems as $item) {
                $usercopies = isset($inventorybyitem[$item->id]) ? $inventorybyitem[$item->id] : [];
                $totalcount = count($usercopies);

                // Visibility Rules (If item not owned).
                if ($totalcount == 0) {
                    if (!$item->enabled) {
                        continue;
                    }
                    if (!block_playerhud_is_visible_for_class($item->required_class_id, $myclassid)) {
                        continue;
                    }
                }

                $media = \block_playerhud\utils::get_item_display_data($item, $context);
                $isinfiniteconfig = $DB->record_exists('block_playerhud_drops', [
                    'itemid' => $item->id,
                    'maxusage' => 0,
                ]);
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

                // Format name for sorting (remove HTML tags and convert secrets).
                $visiblename = format_string($item->name);
                $sortname = strip_tags($visiblename); // Clean key for sorting.

                // Uncollected Item Logic.
                if ($totalcount == 0) {
                    $itemobj = [
                        'card_class' => 'ph-missing',
                        'date_str' => '&nbsp;', // Empty space to maintain height.
                    ];

                    if ($item->secret) {
                        $itemobj['name'] = get_string('secret_name', 'block_playerhud');
                        $sortname = 'zzzz_secret'; // Force secrets to the end of A-Z list.
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
                    $itemobj['origin_legacy'] = 0;

                    foreach ($usercopies as $copy) {
                        $src = $copy->source ?? '';
                        if ($src == 'map') {
                            $itemobj['origin_map']++;
                        } else if ($src == 'shop') {
                            $itemobj['origin_shop']++;
                        } else if ($src == 'quest') {
                            $itemobj['origin_quest']++;
                        } else {
                            $itemobj['origin_legacy']++;
                        }
                    }
                    if (
                        $itemobj['origin_map'] ||
                        $itemobj['origin_shop'] ||
                        $itemobj['origin_quest'] ||
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

        // 3. Robust Sorting (Collator).
        // Fallback safe if intl fails (rare in Moodle).
        $collator = new \Collator('en_US');

        usort($itemsdata, function ($a, $b) use ($currentsort, $collator) {
            switch ($currentsort) {
                case 'name_asc':
                    return $collator->compare($a['sort_name'], $b['sort_name']);

                case 'name_desc':
                    return $collator->compare($b['sort_name'], $a['sort_name']);

                case 'xp_desc':
                    // Tie-break by name if XP is equal.
                    if ($b['raw_xp'] == $a['raw_xp']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $b['raw_xp'] <=> $a['raw_xp'];

                case 'xp_asc':
                    if ($a['raw_xp'] == $b['raw_xp']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $a['raw_xp'] <=> $b['raw_xp'];

                case 'count_desc':
                    if ($a['count'] == $b['count']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $b['count'] <=> $a['count'];

                case 'count_asc':
                    if ($a['count'] == $b['count']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $a['count'] <=> $b['count'];

                case 'recent':
                    return $b['timestamp'] <=> $a['timestamp'];

                case 'acquired':
                    // Those who have (count > 0) come first.
                    $hasa = ($a['count'] > 0) ? 1 : 0;
                    $hasb = ($b['count'] > 0) ? 1 : 0;
                    if ($hasa == $hasb) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $hasb <=> $hasa;

                case 'missing':
                    // Those who don't have (count == 0) come first.
                    $hasa = ($a['count'] > 0) ? 1 : 0;
                    $hasb = ($b['count'] > 0) ? 1 : 0;
                    if ($hasa == $hasb) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $hasa <=> $hasb;

                default:
                    // Default: XP Ascending.
                    return $a['raw_xp'] <=> $b['raw_xp'];
            }
        });

        // 4. Prepare Filter Dropdown Data.
        $url = new moodle_url($PAGE->url);
        $url->param('tab', 'collection');

        $options = [
            'xp_asc'     => get_string('sort_xp_asc', 'block_playerhud'),
            'xp_desc'    => get_string('sort_xp_desc', 'block_playerhud'),
            'name_asc'   => get_string('sort_name_asc', 'block_playerhud'),
            'name_desc'  => get_string('sort_name_desc', 'block_playerhud'),
            'count_desc' => get_string('sort_count_desc', 'block_playerhud'),
            'count_asc'  => get_string('sort_count_asc', 'block_playerhud'),
            'recent'     => get_string('sort_recent', 'block_playerhud'),
            'acquired'   => get_string('sort_acquired', 'block_playerhud'),
            'missing'    => get_string('sort_missing', 'block_playerhud'),
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
