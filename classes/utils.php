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
 * Utility functions for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

/**
 * Class utils
 *
 * General helper functions for image handling and display logic.
 *
 * @package    block_playerhud
 */
class utils {
    /**
     * Retrieves display data for an array of items in bulk to avoid N+1 queries.
     *
     * @param array $items Array of item objects (must have ->id and ->image).
     * @param \context $context The block context.
     * @return array Array of display data keyed by item ID.
     */
    public static function get_items_display_data(array $items, \context $context): array {
        $results = [];
        if (empty($items)) {
            return $results;
        }

        $fs = get_file_storage();

        // Bulk fetch all files for this block instance's item_image area.
        // Passing false to itemid forces Moodle to retrieve all items in this area.
        $allfiles = $fs->get_area_files(
            $context->id,
            'block_playerhud',
            'item_image',
            false,
            'itemid, sortorder DESC, id DESC',
            false
        );

        // Group files by itemid in memory.
        $filesbyitem = [];
        foreach ($allfiles as $f) {
            if ($f->get_filesize() > 0) {
                if (!isset($filesbyitem[$f->get_itemid()])) {
                    $filesbyitem[$f->get_itemid()] = $f;
                }
            }
        }

        // Process each item using the in-memory map.
        foreach ($items as $item) {
            $itemid = $item->id;
            if (isset($filesbyitem[$itemid])) {
                $f = $filesbyitem[$itemid];
                $url = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'block_playerhud',
                    'item_image',
                    $itemid,
                    $f->get_filepath(),
                    $f->get_filename()
                )->out();

                $results[$itemid] = [
                    'url' => $url,
                    'is_image' => true,
                    'content' => $url,
                ];
            } else if (strpos($item->image, 'http') === 0) {
                $results[$itemid] = [
                    'url' => $item->image,
                    'is_image' => true,
                    'content' => $item->image,
                ];
            } else {
                $results[$itemid] = [
                    'url' => null,
                    'is_image' => false,
                    'content' => $item->image,
                ];
            }
        }

        return $results;
    }

    /**
     * Returns the correct URL for the item image or formatted HTML for emojis.
     * Refactored to act as a wrapper for the bulk method.
     *
     * @param \stdClass $item The item object from DB.
     * @param \context $context The block context.
     * @return array Data array ['url', 'is_image', 'content'].
     */
    public static function get_item_display_data($item, $context) {
        $results = self::get_items_display_data([$item->id => $item], $context);
        return $results[$item->id];
    }

    /**
     * Returns the class evolution image URL based on current level.
     *
     * Calculates the proportional tier (1-5) from the XP level and delegates
     * to get_class_evolution_image_by_tier().
     *
     * @param \stdClass $class The class object.
     * @param int $level Current user level.
     * @param \context $context The block context.
     * @return string|null The image URL or null.
     */
    public static function get_class_evolution_image($class, $level, $context) {
        global $DB;

        $maxlevels = 20;

        if (!empty($class->blockinstanceid)) {
            $blockinstance = $DB->get_record('block_instances', ['id' => $class->blockinstanceid]);
            if ($blockinstance && !empty($blockinstance->configdata)) {
                $config = unserialize(base64_decode($blockinstance->configdata));
                if (isset($config->max_levels) && $config->max_levels > 0) {
                    $maxlevels = $config->max_levels;
                }
            }
        }

        $tier = max(1, min(5, (int) ceil(($level / $maxlevels) * 5)));

        return self::get_class_evolution_image_by_tier($class, $tier, $context);
    }

    /**
     * Returns the class evolution image URL for a pre-calculated tier (1-5).
     *
     * Performs a cascade fallback: if no image is configured for the requested
     * tier, tries the previous tier until tier 1. Returns null if no image
     * is found at any tier.
     *
     * @param \stdClass $class The class object (must have ->id).
     * @param int $tier Desired tier, 1-5.
     * @param \context $context The block context.
     * @return string|null The image URL or null.
     */
    public static function get_class_evolution_image_by_tier(
        \stdClass $class,
        int $tier,
        \context $context
    ): ?string {
        $tier = max(1, min(5, $tier));
        $chosentier = $tier;
        $fs = get_file_storage();

        while ($chosentier >= 1) {
            $files = $fs->get_area_files(
                $context->id,
                'block_playerhud',
                'class_image_' . $chosentier,
                $class->id,
                'sortorder',
                false
            );

            foreach ($files as $f) {
                if (!$f->is_directory() && $f->get_filesize() > 0) {
                    return \moodle_url::make_pluginfile_url(
                        $context->id,
                        'block_playerhud',
                        'class_image_' . $chosentier,
                        $class->id,
                        $f->get_filepath(),
                        $f->get_filename()
                    )->out();
                }
            }
            $chosentier--;
        }

        return null;
    }

    /**
     * Calculate the class portrait tier (1-5) based on completed story chapters.
     *
     * This is independent from the XP-based tier used for widget colour coding.
     * Mapping: 0 chapters → 1, 1 → 2, 2-3 → 3, 4-5 → 4, 6+ → 5.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @return int Portrait tier between 1 and 5.
     */
    public static function get_class_portrait_tier(int $instanceid, int $userid): int {
        global $DB;

        $progress = $DB->get_record(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $instanceid, 'userid' => $userid]
        );

        if (!$progress || empty($progress->completed_chapters)) {
            return 1;
        }

        $completed = json_decode($progress->completed_chapters, true);

        if (!is_array($completed)) {
            return 1;
        }

        $count = count($completed);

        if ($count === 0) {
            return 1;
        }
        if ($count === 1) {
            return 2;
        }
        if ($count <= 3) {
            return 3;
        }
        if ($count <= 5) {
            return 4;
        }
        return 5;
    }

    /**
     * Check whether content (item, quest, or trade reward) is visible for the user's RPG class.
     *
     * An empty value or '0' in the required-class field means the content is public (no restriction).
     * Otherwise the stored value is a comma-separated list of class IDs; '0' in that list also
     * means public.
     *
     * @param string $requiredclassids Comma-separated class IDs allowed to see the content.
     * @param int    $userclassid      The current user's RPG class ID (0 if none selected).
     * @return bool True if the content should be visible to the user.
     */
    public static function is_visible_for_class(string $requiredclassids, int $userclassid): bool {
        if (empty($requiredclassids) || $requiredclassids === '0') {
            return true;
        }

        $allowedarray = explode(',', $requiredclassids);

        return in_array('0', $allowedarray) || in_array((string) $userclassid, $allowedarray);
    }

    /**
     * Generates a unique drop code for a given block instance.
     *
     * Uses Moodle's random_string(6) (base-36, 2.17 billion combinations) and
     * retries until the code is not already in use within the same instance,
     * matching the strategy used by block_stash.
     *
     * @param int $blockinstanceid The block instance ID to scope uniqueness.
     * @return string A unique 6-character alphanumeric code (uppercase).
     */
    public static function generate_drop_code(int $blockinstanceid): string {
        global $DB;
        do {
            $code = strtoupper(random_string(6));
            $exists = $DB->record_exists('block_playerhud_drops', [
                'blockinstanceid' => $blockinstanceid,
                'code'            => $code,
            ]);
        } while ($exists);
        return $code;
    }
}
