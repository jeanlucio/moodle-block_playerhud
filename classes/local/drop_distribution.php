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
 * Shared logic for mapping drops onto course activities.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Finds course modules eligible to receive a drop shortcode and suggests the best match
 * by name, so the manual distribution screen and the wizard's auto-distribute step share
 * a single implementation.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drop_distribution {
    /**
     * Lists the course modules that can receive a drop shortcode (their table has an
     * intro or content field), excluding modules pending deletion.
     *
     * @param int $courseid Course ID.
     * @return array<int, array{cmid: int, instance: int, name: string, modname: string,
     *     modname_translated: string, supports_content: bool, is_label: bool}>
     */
    public static function get_eligible_modules(int $courseid): array {
        global $DB;

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);

        $modules = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }
            $columns = $DB->get_columns($cm->modname);
            if (!isset($columns['intro']) && !isset($columns['content'])) {
                continue;
            }
            $modules[] = [
                'cmid' => $cm->id,
                'instance' => $cm->instance,
                'name' => format_string($cm->name),
                'modname' => $cm->modname,
                'modname_translated' => get_string('modulename', 'mod_' . $cm->modname),
                'supports_content' => ($cm->modname === 'page'),
                'is_label' => ($cm->modname === 'label'),
            ];
        }

        return $modules;
    }

    /**
     * Suggests the course module whose name best matches the given text.
     *
     * @param string $haystack Text to match against module names (e.g. drop/item name).
     * @param array $modules Modules as returned by {@see self::get_eligible_modules()}.
     * @return array|null The best-matching module, or null if $modules is empty.
     */
    public static function suggest_module(string $haystack, array $modules): ?array {
        if (empty($modules)) {
            return null;
        }

        $best = null;
        $bestscore = -1;
        $haystack = strtolower($haystack);

        foreach ($modules as $mod) {
            similar_text($haystack, strtolower($mod['name']), $percent);
            if ($percent > $bestscore) {
                $bestscore = $percent;
                $best = $mod;
            }
        }

        return $best;
    }

    /**
     * Returns, for each given drop code, the course modules whose intro/content field
     * already contains that drop's shortcode. Batched per module type to avoid N+1 queries.
     *
     * @param string[] $codesbydropid Drop codes keyed by drop ID.
     * @param array $modules Modules as returned by {@see self::get_eligible_modules()}.
     * @return array<int, array{cmids: int[], first_cmid: ?int, first_field: string}> Keyed by drop ID.
     */
    public static function find_inserted_cmids(array $codesbydropid, array $modules): array {
        global $DB;

        if (empty($codesbydropid) || empty($modules)) {
            return [];
        }

        // Group modules by type: [modname => [instance_id => cmid]].
        $bytype = [];
        foreach ($modules as $mod) {
            $bytype[$mod['modname']][$mod['instance']] = $mod['cmid'];
        }

        // For each module type, load intro/content fields in one query.
        // Result: [cmid => ['intro' => text, 'content' => text]].
        $contentbycmid = [];
        foreach ($bytype as $modname => $instances) {
            $instanceids = array_keys($instances);
            [$insql, $inparams] = $DB->get_in_or_equal($instanceids);

            $columns = $DB->get_columns($modname);
            $fields = ['id'];
            if (isset($columns['intro'])) {
                $fields[] = 'intro';
            }
            if (isset($columns['content'])) {
                $fields[] = 'content';
            }

            $rows = $DB->get_records_select($modname, "id $insql", $inparams, '', implode(',', $fields));
            foreach ($rows as $row) {
                $cmid = $instances[$row->id];
                $contentbycmid[$cmid] = [];
                if (isset($row->intro)) {
                    $contentbycmid[$cmid]['intro'] = (string) $row->intro;
                }
                if (isset($row->content)) {
                    $contentbycmid[$cmid]['content'] = (string) $row->content;
                }
            }
        }

        // For each drop code, collect cmids and note the first field where it was found.
        $result = [];
        foreach ($codesbydropid as $dropid => $code) {
            $needle = 'code=' . $code;
            $insertedcmids = [];
            $firstcmid = null;
            $firstfield = 'intro';

            foreach ($contentbycmid as $cmid => $fields) {
                foreach ($fields as $fieldname => $text) {
                    if (strpos($text, $needle) !== false) {
                        if ($firstcmid === null) {
                            $firstcmid = $cmid;
                            $firstfield = $fieldname;
                        }
                        if (!in_array($cmid, $insertedcmids)) {
                            $insertedcmids[] = $cmid;
                        }
                        break; // One field match per cmid is enough.
                    }
                }
            }

            $result[$dropid] = [
                'cmids' => $insertedcmids,
                'first_cmid' => $firstcmid,
                'first_field' => $firstfield,
            ];
        }

        return $result;
    }
}
