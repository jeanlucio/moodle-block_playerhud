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
 * Data export entry point for PlayerHUD gamification grades.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// 1. Get Parameters.
$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$format     = optional_param('format', 'csv', PARAM_ALPHANUMEXT);

// 2. Security Checks.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi     = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = \context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

// Note: require_sesskey() is intentionally omitted here because data export
// is a read-only GET request and does not modify database state.

// 3. Delegate to the controller.
$controller = new \block_playerhud\controller\export();
$controller->execute($courseid, $instanceid, $format, $course->shortname);
