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
 * Entry point for managing drops (MVC Style).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// 1. Get Parameters.
// We prioritize instanceid (Block ID) to deduce the context.
$instanceid = required_param('instanceid', PARAM_INT);
$courseid   = optional_param('courseid', 0, PARAM_INT);

// 2. Deduce Course ID if missing.
if (!$courseid) {
    // Try standard 'id' param often used for courseid.
    $courseid = optional_param('id', 0, PARAM_INT);
}

if (!$courseid) {
    // Fetch from Block Instance parent context.
    $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
    $parentcontext = context::instance_by_id($bi->parentcontextid);

    if ($parentcontext->contextlevel == CONTEXT_COURSE) {
        $courseid = $parentcontext->instanceid;
    } else {
        // Fallback for Dashboard or Frontpage (use Site ID as safer default).
        $courseid = SITEID;
    }
}

// 3. Security Check.
require_login($courseid);

// 4. Delegate logic to Controller.
$controller = new \block_playerhud\controller\drops();
echo $controller->view_manage_page();
