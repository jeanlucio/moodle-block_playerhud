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

/**
 * Utility functions for PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Returns the correct URL for the item image or formatted HTML for emojis.
     *
     * Priority:
     * 1. File uploaded via File API.
     * 2. External URL (if starts with http).
     * 3. Emoji/Text (returns null in URL and content wrapped in aria-hidden).
     *
     * @param \stdClass $item The item object from DB.
     * @param \context $context The block context.
     * @return array Data array ['url', 'is_image', 'content'].
     */
    public static function get_item_display_data($item, $context) {
        // 1. Try to fetch uploaded image (File API).
        $fs = get_file_storage();

        // Fetch files from 'item_image' area.
        // Component changed from mod_playerhud to block_playerhud.
        $files = $fs->get_area_files($context->id, 'block_playerhud', 'item_image', $item->id, 'sortorder', false);

        $uploadedfile = null;
        foreach ($files as $f) {
            if (!$f->is_directory() && $f->get_filesize() > 0) {
                $uploadedfile = $f;
                break;
            }
        }

        if ($uploadedfile) {
            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'block_playerhud',
                'item_image',
                $item->id,
                $uploadedfile->get_filepath(),
                $uploadedfile->get_filename()
            )->out();

            return [
                'url' => $url,
                'is_image' => true,
                'content' => $url,
            ];
        }

        // 2. If no upload, check text field for External URL.
        if (strpos($item->image, 'http') === 0) {
            return [
                'url' => $item->image,
                'is_image' => true,
                'content' => $item->image,
            ];
        } else {
            // 3. If not a link, it is an Emoji or Text.
            return [
                'url' => null,
                'is_image' => false,
                'content' => $item->image,
            ];
        }
    }

    /**
     * Returns the class evolution image URL based on current level.
     *
     * Logic: Proportional Visual Evolution (Tiers 1-5).
     *
     * @param \stdClass $class The class object.
     * @param int $level Current user level.
     * @param \context $context The block context.
     * @return string|null The image URL or null.
     */
    public static function get_class_evolution_image($class, $level, $context) {
        global $DB;

        // 1. Fetch max level config from Block Instance settings.
        // We no longer query a custom 'playerhud' table.
        // $class->blockinstanceid must exist in the classes table.
        $maxlevels = 20; // Default fallback.

        if (!empty($class->blockinstanceid)) {
            $blockinstance = $DB->get_record('block_instances', ['id' => $class->blockinstanceid]);
            if ($blockinstance && !empty($blockinstance->configdata)) {
                $config = unserialize(base64_decode($blockinstance->configdata));
                if (isset($config->max_levels) && $config->max_levels > 0) {
                    $maxlevels = $config->max_levels;
                }
            }
        }

        // 2. Calculate proportional tier (splits journey into 5 visual stages).
        $tier = ceil(($level / $maxlevels) * 5);

        // Clamping tier between 1 and 5.
        $tier = max(1, min(5, (int)$tier));

        $chosentier = $tier;
        $fs = get_file_storage(); // Instantiated outside loop for performance.

        // 3. Reverse loop to find the nearest image (Cascade fallback).
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
}
