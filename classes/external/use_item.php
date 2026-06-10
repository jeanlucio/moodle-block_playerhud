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
 * Web service to equip an avatar item or consume a deadline extension item.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

/**
 * External API to equip/unequip an avatar item or consume a deadline extension item.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class use_item extends external_api {
    /**
     * Parameters for use_item.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'itemid'     => new external_value(PARAM_INT, 'Item ID'),
            'targetcmid' => new external_value(PARAM_INT, 'Target course module ID (deadline_extension only)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Equip/unequip an avatar item or consume a deadline extension item.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $itemid Item ID.
     * @param int $targetcmid Target CM (for deadline_extension when cmid=0 on the item).
     * @return array
     */
    public static function execute(int $instanceid, int $courseid, int $itemid, int $targetcmid = 0): array {
        global $DB, $USER, $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'itemid'     => $itemid,
            'targetcmid' => $targetcmid,
        ]);
        $instanceid = $params['instanceid'];
        $courseid   = $params['courseid'];
        $itemid     = $params['itemid'];
        $targetcmid = $params['targetcmid'];

        $context = \context_block::instance($instanceid);
        self::validate_context($context);
        require_login();

        $item = $DB->get_record(
            'block_playerhud_items',
            ['id' => $itemid, 'blockinstanceid' => $instanceid, 'enabled' => 1],
            '*',
            MUST_EXIST
        );

        $hasinv = $DB->record_exists_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source NOT IN ('revoked','consumed')",
            ['uid' => $USER->id, 'iid' => $itemid]
        );
        if (!$hasinv) {
            throw new \moodle_exception('itemnotfound', 'block_playerhud');
        }

        if ($item->action_type === 'avatar_profile') {
            $prefkey  = 'block_playerhud_avatar_' . $instanceid;
            $current  = (int) get_user_preferences($prefkey, 0);
            $equipped = ($current !== $itemid);

            if ($equipped) {
                set_user_preference($prefkey, $itemid);
                $avatarhtml = \block_playerhud\utils::get_avatar_html($item, $context, $OUTPUT);
            } else {
                unset_user_preference($prefkey);
                $avatarhtml = $OUTPUT->user_picture($USER, ['size' => 100, 'class' => 'rounded-circle shadow-sm']);
            }

            return [
                'action'      => 'avatar_profile',
                'equipped'    => $equipped,
                'avatar_html' => $avatarhtml,
                'success'     => true,
                'message'     => $equipped
                    ? get_string('item_use_success_avatar', 'block_playerhud')
                    : get_string('item_unequip_success', 'block_playerhud'),
                'new_deadline' => '',
            ];
        }

        if ($item->action_type === 'deadline_extension') {
            if (!class_exists('\local_latepenalty\recalculator')) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_lp_not_installed', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $av   = !empty($item->action_value) ? json_decode($item->action_value, true) : [];
            $days = max(1, (int)($av['days'] ?? 1));
            $cmid = !empty($av['cmid']) ? (int)$av['cmid'] : $targetcmid;

            if ($cmid <= 0) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_use_pick_activity', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $rule = $DB->get_record('local_latepenalty_rules', ['cmid' => $cmid, 'enabled' => 1]);
            if (!$rule) {
                return [
                    'action'       => 'deadline_extension',
                    'equipped'     => false,
                    'avatar_html'  => '',
                    'success'      => false,
                    'message'      => get_string('item_lp_warning', 'block_playerhud'),
                    'new_deadline' => '',
                ];
            }

            $override = $DB->get_record('local_latepenalty_overrides', ['cmid' => $cmid, 'userid' => $USER->id]);
            if ($override && $override->deadline !== null) {
                $base = (int)$override->deadline;
            } else {
                $modinfo = get_fast_modinfo($courseid);
                $cm      = $modinfo->get_cm($cmid);
                $cmrec   = $cm->get_course_module_record();
                $cmrec->modname = $cm->modname;
                $base    = (int)(\local_latepenalty\penalty_helper::get_deadline($cmrec) ?? time());
            }

            $newdeadline = $base + ($days * DAYSECS);

            if ($override) {
                $override->deadline      = $newdeadline;
                $override->timemodified  = time();
                $DB->update_record('local_latepenalty_overrides', $override);
            } else {
                $DB->insert_record('local_latepenalty_overrides', (object)[
                    'cmid'          => $cmid,
                    'userid'        => $USER->id,
                    'deadline'      => $newdeadline,
                    'daily_penalty' => null,
                    'max_penalty'   => null,
                    'timecreated'   => time(),
                    'timemodified'  => time(),
                ]);
            }

            \local_latepenalty\recalculator::recalculate_for_student(
                $cmid,
                $USER->id,
                (float)$rule->daily_penalty,
                (float)$rule->max_penalty
            );

            $consumable = $DB->get_records_select(
                'block_playerhud_inventory',
                "userid = :uid AND itemid = :iid AND source NOT IN ('revoked','consumed')",
                ['uid' => $USER->id, 'iid' => $itemid],
                'id ASC',
                'id',
                0,
                1
            );
            if ($consumable) {
                $DB->set_field('block_playerhud_inventory', 'source', 'consumed', ['id' => reset($consumable)->id]);
            }

            $formatted = userdate($newdeadline, get_string('strftimedatetime', 'langconfig'));
            return [
                'action'       => 'deadline_extension',
                'equipped'     => false,
                'avatar_html'  => '',
                'success'      => true,
                'message'      => get_string('item_use_success_deadline', 'block_playerhud', $days),
                'new_deadline' => $formatted,
            ];
        }

        throw new \moodle_exception('errorgeneral', 'error');
    }

    /**
     * Return structure for use_item.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'action'       => new external_value(PARAM_ALPHANUMEXT, 'Action type performed'),
            'equipped'     => new external_value(PARAM_BOOL, 'Whether avatar is now equipped'),
            'avatar_html'  => new external_value(PARAM_RAW, 'Avatar HTML for DOM replacement'),
            'success'      => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
            'message'      => new external_value(PARAM_RAW, 'Feedback message'),
            'new_deadline' => new external_value(PARAM_TEXT, 'Formatted new deadline (deadline_extension only)'),
        ]);
    }
}
