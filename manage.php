<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY;
// without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.
// If not, see <http://www.gnu.org/licenses/>.

/**
 * Main management page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// 1. Initial configuration and parameters.
$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$activetab  = optional_param('tab', 'items', PARAM_ALPHA);

// Action Parameters.
$action  = optional_param('action', '', PARAM_ALPHANUMEXT);
$itemid  = optional_param('itemid', 0, PARAM_INT);
$questid = optional_param('questid', 0, PARAM_INT);
$tradeid = optional_param('tradeid', 0, PARAM_INT);

// Sorting Parameters.
$sort = optional_param('sort', '', PARAM_ALPHA);
$dir  = optional_param('dir', 'ASC', PARAM_ALPHA);

// 2. Security checks.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

// Base URL for redirects.
$baseurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id' => $courseid,
    'instanceid' => $instanceid
]);

// 3. Action processing (Global Controllers).

// Action: Toggle Item Status.
if ($action == 'toggle' && $itemid && confirm_sesskey()) {
    $it = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($it) {
        $newstatus = $it->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_items', 'enabled', $newstatus, ['id' => $itemid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Toggle Quest Status.
if ($action == 'toggle_quest' && $questid && confirm_sesskey()) {
    $q = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    if ($q) {
        $newstatus = $q->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_quests', 'enabled', $newstatus, ['id' => $questid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Item.
if ($action == 'delete' && $itemid && confirm_sesskey()) {
    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($item) {
        // 1. Remove XP from users holding this item.
        $sql = "SELECT userid, COUNT(id) as qtd FROM {block_playerhud_inventory} WHERE itemid = ? GROUP BY userid";
        $holders = $DB->get_records_sql($sql, [$itemid]);
        foreach ($holders as $holder) {
            $xptoremove = $item->xp * $holder->qtd;
            $DB->execute(
                "UPDATE {block_playerhud_user} 
                    SET currentxp = GREATEST(0, currentxp - ?) 
                  WHERE userid = ? AND blockinstanceid = ?",
                [$xptoremove, $holder->userid, $instanceid]
            );
        }

        // 2. Delete dependencies.
        $DB->delete_records('block_playerhud_inventory', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_drops', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_reqs', ['itemid' => $itemid]); 
        $DB->delete_records('block_playerhud_trade_rewards', ['itemid' => $itemid]);
        
        // 3. Delete the item files and record.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $itemid);
        $DB->delete_records('block_playerhud_items', ['id' => $itemid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'items']),
            get_string('deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Quest.
if ($action == 'delete_quest' && $questid && confirm_sesskey()) {
    $DB->delete_records('block_playerhud_quest_log', ['questid' => $questid]);
    $DB->delete_records('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    redirect(
        new moodle_url($baseurl, ['tab' => 'quests']),
        get_string('quest_deleted', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Action: Delete Trade.
if ($action == 'delete_trade' && $tradeid && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();
    try {
        $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_log', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
        $transaction->allow_commit();
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        $transaction->rollback($e);
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('error_msg', 'block_playerhud', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Action: Delete Class.
if ($action == 'delete_class') {
    $classid = optional_param('classid', 0, PARAM_INT);
    if ($classid && confirm_sesskey()) {
        $fs = get_file_storage();
        for ($i = 1; $i <= 5; $i++) {
            $fs->delete_area_files($context->id, 'block_playerhud', 'class_image_' . $i, $classid);
        }
        $DB->delete_records('block_playerhud_classes', ['id' => $classid, 'blockinstanceid' => $instanceid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'classes']),
            get_string('class_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Chapter.
if ($action == 'delete_chapter') {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid && confirm_sesskey()) {
        $scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        foreach ($scenes as $sc) {
            $DB->delete_records('block_playerhud_choices', ['nodeid' => $sc->id]);
        }
        $DB->delete_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $DB->delete_records('block_playerhud_chapters', ['id' => $chapterid, 'blockinstanceid' => $instanceid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            get_string('chapter_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Save API Keys (Block Config).
if ($action == 'save_keys' && confirm_sesskey()) {
    $gkey = optional_param('gemini_key', '', PARAM_TEXT);
    $qkey = optional_param('groq_key', '', PARAM_TEXT);

    // In blocks, we save settings to the instance configdata.
    $config = (array) unserialize(base64_decode($bi->configdata));
    $config['apikey_gemini'] = trim($gkey);
    $config['apikey_groq'] = trim($qkey);
    
    $bi->configdata = base64_encode(serialize((object)$config));
    $DB->update_record('block_instances', $bi);
    
    // CORREÇÃO: Usar 'changessaved' que existe no arquivo de idioma
    redirect(
        new moodle_url($baseurl, ['tab' => 'config']),
        get_string('changessaved', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// 4. PRE-RENDER LOGIC (Controller Strategy)
$render_class = "\\block_playerhud\\output\\manage\\tab_{$activetab}";
$renderer = null;

if (class_exists($render_class)) {
    $renderer = new $render_class($instanceid, $courseid, $sort, $dir);
    
    if (method_exists($renderer, 'process')) {
        $renderer->process();
    }
}

// 5. Page render (View).

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

// Header.
echo \html_writer::start_div('d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom');
echo \html_writer::tag('h2', get_string('master_panel', 'block_playerhud'), ['class' => 'm-0']);
echo \html_writer::link(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    '<i class="fa fa-arrow-left"></i> ' . get_string('back'),
    ['class' => 'btn btn-outline-secondary px-3']
);
echo \html_writer::end_div();

// Tabs Navigation.
$tabs = [
    'items' => ['icon' => '📚', 'text' => get_string('tab_items', 'block_playerhud')],
    'classes' => ['icon' => '🦸', 'text' => get_string('tab_classes', 'block_playerhud')],
    'chapters' => ['icon' => '📖', 'text' => get_string('tab_chapters', 'block_playerhud')],
    'trades' => ['icon' => '⚖️', 'text' => get_string('tab_trades', 'block_playerhud')],
    'quests' => ['icon' => '📜', 'text' => get_string('tab_quests', 'block_playerhud')],
    'reports' => ['icon' => '📊', 'text' => get_string('tab_reports', 'block_playerhud')],
    'config' => ['icon' => '🛠️', 'text' => get_string('tab_config', 'block_playerhud')],
];

echo \html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-4']);
foreach ($tabs as $key => $data) {
    $active = ($activetab == $key) ? 'active' : '';
    $url = new moodle_url($baseurl, ['tab' => $key]);
    $icon = '<span aria-hidden="true" class="me-2">' . $data['icon'] . '</span>';
    $link = \html_writer::link($url, $icon . $data['text'], ['class' => 'nav-link ' . $active]);
    echo \html_writer::tag('li', $link, ['class' => 'nav-item']);
}
echo \html_writer::end_tag('ul');

// 6. Content render.
echo \html_writer::start_div('container-fluid p-0 animate__animated animate__fadeIn');

if ($renderer) {
    echo $renderer->display();
} else {
    echo $OUTPUT->notification(
        get_string('tab_maintenance', 'block_playerhud', $tabs[$activetab]['text'] ?? $activetab),
        'info'
    );
}

echo \html_writer::end_div();

// Javascript Section.
$copyscript = <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    var buttons = document.querySelectorAll(".copy-btn");
    buttons.forEach(function(btn) {
        btn.addEventListener("click", function() {
            var targetId = this.getAttribute("data-target");
            var input = document.getElementById(targetId);
            if(input) {
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand("copy");
                var originalHtml = this.innerHTML;
                this.innerHTML = "<i class='fa fa-check text-success'></i>";
                var that = this;
                setTimeout(function(){ that.innerHTML = originalHtml; }, 2000);
            }
        });
    });
});
</script>
JS;
echo $copyscript;

echo $OUTPUT->footer();
