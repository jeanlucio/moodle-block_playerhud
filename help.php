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
 * Teacher help page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);

$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

$manageurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id'         => $courseid,
    'instanceid' => $instanceid,
]);

$PAGE->set_url(new moodle_url('/blocks/playerhud/help.php', [
    'id'         => $courseid,
    'instanceid' => $instanceid,
]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('help_teacher_title', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));

$sectionkeys = [
    'overview',
    'items',
    'trades',
    'quests',
    'classes',
    'story',
    'reports',
    'config',
];

$sections = [];
foreach ($sectionkeys as $i => $key) {
    $sections[] = [
        'id'      => 'ph-help-' . $key,
        'target'  => 'phHelp' . ucfirst($key),
        'title'   => get_string('help_teacher_section_' . $key . '_title', 'block_playerhud'),
        'content' => get_string('help_teacher_section_' . $key, 'block_playerhud'),
        'open'    => ($i === 0),
    ];
}

$templatedata = [
    'str_title'        => get_string('help_teacher_title', 'block_playerhud'),
    'url_manage'       => $manageurl->out(false),
    'str_back_manage'  => get_string('back_to_manage', 'block_playerhud'),
    'url_course'       => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'str_back_course'  => get_string('back_to_course', 'block_playerhud'),
    'sections'         => $sections,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_playerhud/help_teacher', $templatedata);
echo $OUTPUT->footer();
