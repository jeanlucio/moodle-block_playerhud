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

namespace block_playerhud\controller;

use moodle_url;

/**
 * Controller for handling item collection logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect {
    /**
     * Executes the main collection logic.
     *
     * This is the page-request fallback for a client that never got (or never ran) the
     * JavaScript that intercepts the drop link and calls the block_playerhud_collect_item
     * web service instead. It delegates the actual rules to game::process_collection(),
     * the same method the web service uses, so the two entry points cannot drift apart —
     * including the per-user lock that serializes concurrent collections of the same drop.
     *
     * @return void
     */
    public function execute(): void {
        global $USER;

        // 1. Parameters.
        $instanceid = required_param('instanceid', PARAM_INT);
        $dropid     = required_param('dropid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);

        // 2. Security.
        require_login($courseid);
        require_sesskey();
        $context = \context_block::instance($instanceid);
        require_capability('block/playerhud:interact', $context);

        // Verify the block instance actually belongs to the supplied course, so a mismatched
        // courseid from another page request cannot land this page on the wrong course's
        // "return to course" redirect after collecting.
        $blockcoursectx = $context->get_course_context(false);
        if (!$blockcoursectx || (int) $blockcoursectx->instanceid !== $courseid) {
            throw new \moodle_exception('accessdenied', 'admin');
        }

        $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);

        try {
            $result = \block_playerhud\game::process_collection($instanceid, $dropid, (int)$USER->id);
            redirect($returnurl, $result['message'], null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (\Exception $e) {
            redirect($returnurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}
