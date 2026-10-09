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
 * Administrator's usage and diagnostics page of the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_playerhud\local\diagnostics\integrity;
use block_playerhud\local\diagnostics\report;
use block_playerhud\output\admin\diagnostics;
use core\output\notification;

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

// Checks login and moodle/site:config, the capability the page is registered with.
admin_externalpage_setup('block_playerhud_diagnostics');

$pageurl = new moodle_url('/blocks/playerhud/admin/diagnostics.php');
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'refresh') {
    require_sesskey();
    report::invalidate();
    redirect($pageurl);
}

if ($action === 'cleanup') {
    require_sesskey();
    if (!optional_param('confirm', 0, PARAM_BOOL)) {
        redirect($pageurl, get_string('diag_cleanup_noconfirm', 'block_playerhud'), null, notification::NOTIFY_WARNING);
    }
    $result = integrity::cleanup(optional_param_array('instances', [], PARAM_INT));
    report::invalidate();
    if ($result['file'] === '') {
        redirect($pageurl, get_string('diag_cleanup_nothing', 'block_playerhud'), null, notification::NOTIFY_INFO);
    }
    redirect(
        $pageurl,
        get_string('diag_cleanup_done', 'block_playerhud', (object) $result),
        null,
        notification::NOTIFY_SUCCESS
    );
}

if ($action === 'deletebackup') {
    require_sesskey();
    $filename = required_param('file', PARAM_FILE);
    if (!integrity::delete_backup($filename)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    redirect($pageurl, get_string('diag_backup_deleted', 'block_playerhud', $filename), null, notification::NOTIFY_SUCCESS);
}

if ($action === 'download') {
    require_sesskey();
    $filename = required_param('file', PARAM_FILE);
    $path = integrity::resolve_backup($filename);
    if ($path === null) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    send_file($path, $filename, 0, 0, false, true, 'application/json');
}

$page = new diagnostics(report::get(), integrity::list_backups(), $pageurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('diag_title', 'block_playerhud'));
echo $OUTPUT->render_from_template('block_playerhud/admin_diagnostics', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
