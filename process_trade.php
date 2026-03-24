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
 * Script to process trade transactions securely.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$tradeid = required_param('tradeid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
require_sesskey();

$context = \context_block::instance($instanceid);
require_capability('block/playerhud:view', $context);

$returnparam = optional_param('returnurl', '', PARAM_LOCALURL);

if (!empty($returnparam)) {
    $returnurl = new moodle_url($returnparam);
} else {
    $returnurl = new moodle_url('/blocks/playerhud/view.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
        'tab' => 'shop',
    ]);
}

try {
    $rewardstext = \block_playerhud\trade_manager::execute_trade($tradeid, $USER->id, $instanceid, $courseid);

    redirect(
        $returnurl,
        get_string('trade_success_msg', 'block_playerhud', $rewardstext),
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (\moodle_exception $me) {
    $notifylevel = ($me->errorcode === 'error_trade_onetime') ?
        \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_ERROR;

    redirect($returnurl, $me->getMessage(), $notifylevel);
} catch (\Exception $e) {
    redirect($returnurl, get_string('error_msg', 'block_playerhud', $e->getMessage()), \core\output\notification::NOTIFY_ERROR);
}
