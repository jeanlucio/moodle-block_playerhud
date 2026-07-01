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
 * Main management page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// Initial configuration and parameters.
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
$dir  = optional_param('dir', '', PARAM_ALPHA);

// Security checks.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);

// Set block context.
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

// Base URL for redirects.
$baseurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id' => $courseid,
    'instanceid' => $instanceid,
]);

// Page Setup.
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));

$rawblockconfig = base64_decode($bi->configdata ?? '', true);
$blockconfig = ($rawblockconfig !== false && $rawblockconfig !== '') ? unserialize_object($rawblockconfig) : new stdClass();
if (!is_object($blockconfig)) {
    $blockconfig = new stdClass();
}
$rpgmodeenabled  = !empty($blockconfig->enable_rpg) || !isset($blockconfig->enable_rpg);
$itemsenabled    = !empty($blockconfig->enable_items) || !isset($blockconfig->enable_items);
$questsenabled   = !empty($blockconfig->enable_quests) || !isset($blockconfig->enable_quests);

// Wizard empty state: show the welcome banner only while this instance has
// no items and no quests at all yet.
$wizarditemcount = $DB->count_records('block_playerhud_items', ['blockinstanceid' => $instanceid]);
$wizardquestcount = $DB->count_records('block_playerhud_quests', ['blockinstanceid' => $instanceid]);
$wizardshowemptybanner = ($wizarditemcount === 0 && $wizardquestcount === 0);

// Determine a sensible fallback tab when a feature group is disabled.
$fallbacktab = $itemsenabled ? 'items' : ($questsenabled ? 'quests' : 'reports');

// Redirect RPG tabs when RPG mode is disabled.
$rpgtabs = ['classes', 'chapters'];
if (in_array($activetab, $rpgtabs) && !$rpgmodeenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $fallbacktab]));
}

// Redirect items/drops/trades tabs when items feature is disabled.
$itemstabs = ['items', 'drops', 'trades'];
if (in_array($activetab, $itemstabs) && !$itemsenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $questsenabled ? 'quests' : 'reports']));
}

// Redirect quests tab when quests feature is disabled.
if ($activetab === 'quests' && !$questsenabled) {
    redirect(new moodle_url($baseurl, ['tab' => $fallbacktab]));
}

// Action processing (Global Controllers).

// Action: Toggle Item Status.
if ($action == 'toggle' && $itemid && confirm_sesskey()) {
    if (\block_playerhud\controller\items::toggle_item($itemid, $instanceid)) {
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Toggle Quest Status.
if ($action == 'toggle_quest' && $questid && confirm_sesskey()) {
    if (\block_playerhud\controller\quests::toggle_quest($questid, $instanceid)) {
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Item (Single) — show confirmation if trades would become empty.
if ($action === 'delete' && $itemid && confirm_sesskey()) {
    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($item) {
        $orphanedtrades  = \block_playerhud\controller\items::find_orphaned_trades($instanceid, [$itemid]);
        $survivingtrades = \block_playerhud\controller\items::find_affected_surviving_trades($instanceid, [$itemid]);

        if (!empty($orphanedtrades) || !empty($survivingtrades)) {
            $itemsurl = new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]);
            $PAGE->set_url($itemsurl);

            $confirmctx = \block_playerhud\output\manage\item_delete_confirm::build_context(
                format_string($item->name),
                $orphanedtrades,
                $survivingtrades,
                false,
                [$itemid],
                [
                    'form'   => $baseurl->out(false),
                    'cancel' => $itemsurl->out(false),
                    'edit'   => (new moodle_url($baseurl, ['tab' => 'trades']))->out(false),
                ],
                $sort,
                $dir
            );

            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('block_playerhud/manage_item_delete_confirm', $confirmctx);
            echo $OUTPUT->footer();
            exit;
        }

        \block_playerhud\controller\items::delete_item($item, $instanceid, $context);
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Item (confirmed — cascades orphaned trades).
if ($action === 'delete_force' && $itemid && confirm_sesskey()) {
    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($item) {
        $affectedtrades = \block_playerhud\controller\items::find_orphaned_trades($instanceid, [$itemid]);
        \block_playerhud\controller\items::delete_item($item, $instanceid, $context, array_keys($affectedtrades));
        $msg = count($affectedtrades) > 0
            ? get_string('deleted_with_trades', 'block_playerhud', count($affectedtrades))
            : get_string('deleted', 'block_playerhud');
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            $msg,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Bulk Delete Items — show confirmation if any trade would become empty.
if ($action === 'bulk_delete' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    if (!empty($bulkids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($bulkids);
        $params = array_merge($inparams, [$instanceid]);
        $items = $DB->get_records_select('block_playerhud_items', "id $insql AND blockinstanceid = ?", $params);

        if ($items) {
            $itemids = array_keys($items);
            $orphanedtrades  = \block_playerhud\controller\items::find_orphaned_trades($instanceid, $itemids);
            $survivingtrades = \block_playerhud\controller\items::find_affected_surviving_trades($instanceid, $itemids);

            if (!empty($orphanedtrades) || !empty($survivingtrades)) {
                $itemsurl = new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]);
                $PAGE->set_url($itemsurl);

                $confirmctx = \block_playerhud\output\manage\item_delete_confirm::build_context(
                    get_string('delete_selected', 'block_playerhud'),
                    $orphanedtrades,
                    $survivingtrades,
                    true,
                    $itemids,
                    [
                        'form'   => $baseurl->out(false),
                        'cancel' => $itemsurl->out(false),
                        'edit'   => (new moodle_url($baseurl, ['tab' => 'trades']))->out(false),
                    ],
                    $sort,
                    $dir
                );

                echo $OUTPUT->header();
                echo $OUTPUT->render_from_template('block_playerhud/manage_item_delete_confirm', $confirmctx);
                echo $OUTPUT->footer();
                exit;
            }

            \block_playerhud\controller\items::bulk_delete_items($items, $instanceid, $context);
            redirect(
                new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
                get_string('deleted_bulk', 'block_playerhud', count($itemids)),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    } else {
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('no_items_selected', 'block_playerhud'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Action: Bulk Delete Items (confirmed — cascades orphaned trades).
if ($action === 'bulk_delete_force' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    if (!empty($bulkids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($bulkids);
        $params = array_merge($inparams, [$instanceid]);
        $items = $DB->get_records_select('block_playerhud_items', "id $insql AND blockinstanceid = ?", $params);

        if ($items) {
            $itemids = array_keys($items);
            $affectedtrades = \block_playerhud\controller\items::find_orphaned_trades($instanceid, $itemids);
            \block_playerhud\controller\items::bulk_delete_items($items, $instanceid, $context, array_keys($affectedtrades));
            $tradecount = count($affectedtrades);
            $msg = $tradecount > 0
                ? get_string('deleted_bulk_with_trades', 'block_playerhud', (object) [
                    'items'  => count($itemids),
                    'trades' => $tradecount,
                  ])
                : get_string('deleted_bulk', 'block_playerhud', count($itemids));
            redirect(
                new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
                $msg,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
}

// Action: Delete Quest.
if ($action == 'delete_quest' && $questid && confirm_sesskey()) {
    if (\block_playerhud\controller\quests::delete_quest($questid, $instanceid)) {
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests']),
            get_string('quest_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Bulk Delete Quests (Multiple).
if ($action === 'bulk_delete_quests' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    if (!empty($bulkids)) {
        $deletedcount = \block_playerhud\controller\quests::bulk_delete_quests($bulkids, $instanceid);

        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted_bulk', 'block_playerhud', $deletedcount),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('no_items_selected', 'block_playerhud'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Action: Delete Trade.
if ($action == 'delete_trade' && $tradeid && confirm_sesskey()) {
    try {
        (new \block_playerhud\controller\trades())->delete_trade($tradeid, $instanceid);
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
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
        (new \block_playerhud\controller\classes())->delete_class($classid, $instanceid, $context);
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
        (new \block_playerhud\controller\chapters())->delete_chapter($chapterid, $instanceid);
        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            get_string('chapter_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Move Chapter Up.
if ($action === 'move_chapter_up' && confirm_sesskey()) {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid) {
        (new \block_playerhud\controller\chapters())->move_chapter($chapterid, $instanceid, 'up');
        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

// Action: Move Chapter Down.
if ($action === 'move_chapter_down' && confirm_sesskey()) {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid) {
        (new \block_playerhud\controller\chapters())->move_chapter($chapterid, $instanceid, 'down');
        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}

// Action: Save API Keys.
if ($action === 'save_keys' && confirm_sesskey()) {
    \block_playerhud\controller\aikeys::save([
        'gemini_key'   => optional_param('gemini_key', '', PARAM_TEXT),
        'groq_key'     => optional_param('groq_key', '', PARAM_TEXT),
        'openai_key'   => optional_param('openai_key', '', PARAM_TEXT),
        'openai_url'   => optional_param('openai_url', '', PARAM_URL),
        'openai_model' => optional_param('openai_model', '', PARAM_TEXT),
    ], $bi, $USER->id);

    redirect(
        new moodle_url($baseurl, ['tab' => 'config']),
        get_string('changessaved', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Action: Revoke Item (Teacher manually removes item).
if ($action === 'revoke_item' && confirm_sesskey()) {
    $invid = required_param('invid', PARAM_INT);
    $ruserid = required_param('r_userid', PARAM_INT);

    \block_playerhud\controller\items::revoke_item($invid, $instanceid);

    $url = new moodle_url($baseurl, ['tab' => 'reports', 'r_userid' => $ruserid]);
    redirect($url, get_string('item_revoked', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Grant Item (Teacher manually gives item).
if ($action === 'grant_item' && confirm_sesskey()) {
    $ruserid = required_param('r_userid', PARAM_INT);
    $itemid = required_param('itemid', PARAM_INT);

    \block_playerhud\controller\items::grant_item($itemid, $ruserid, $instanceid);

    $url = new moodle_url($baseurl, ['tab' => 'reports', 'r_userid' => $ruserid]);
    redirect($url, get_string('item_granted', 'block_playerhud'), \core\output\notification::NOTIFY_SUCCESS);
}

// Action: Auto Suggest Quests (Heuristic).
if ($action === 'suggest_quests' || $action === 'save_suggestions') {
    $rawconfig = base64_decode($bi->configdata ?? '', true);
    $config = ($rawconfig !== false && $rawconfig !== '') ? unserialize_object($rawconfig) : new \stdClass();
    if (!is_object($config)) {
        $config = new \stdClass();
    }

    $suggestions = \block_playerhud\quest::get_heuristic_suggestions($instanceid, $courseid, $config);

    // Build the form with suggestions.
    $formurl = new moodle_url($baseurl, ['tab' => 'quests']);
    $sugform = new \block_playerhud\form\suggest_quests_form($formurl, ['suggestions' => $suggestions]);

    if ($action === 'suggest_quests') {
        $sugform->set_data([
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'action'     => 'save_suggestions',
        ]);
    }

    if ($sugform->is_cancelled()) {
        redirect(new moodle_url($baseurl, ['tab' => 'quests']));
    } else if ($data = $sugform->get_data()) {
        $count = \block_playerhud\controller\suggestions::save_quest_suggestions($instanceid, $suggestions, $data);

        // Redirect back to the quests tab with a success message indicating how many quests were created.
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests']),
            get_string('quest_sug_created', 'block_playerhud', $count),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $PAGE->requires->js_call_amd('block_playerhud/manage_quests', 'init', [[
        'strings' => [
            'confirm_title'   => get_string('confirmation', 'admin'),
            'yes'             => get_string('yes'),
            'cancel'          => get_string('cancel'),
            'delete_selected' => get_string('delete_selected', 'block_playerhud'),
            'delete_n_items'  => get_string('delete_n_items', 'block_playerhud'),
            'confirm_bulk'    => get_string('confirm_bulk_delete', 'block_playerhud'),
        ],
    ]]);

    echo $OUTPUT->header();
    $sugform->display();
    echo $OUTPUT->footer();
    exit;
}

// Action: Suggest Trades (Avatar pack + PlayerCoin heuristic).
if ($action === 'suggest_trades' || $action === 'save_suggest_trades') {
    // Build the heuristic suggestion list (PlayerCoin + avatars).
    $suggestions = \block_playerhud\game::build_trade_suggestions($instanceid);

    if ($action === 'suggest_trades' && empty($suggestions)) {
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('trade_sug_none_available', 'block_playerhud'),
            \core\output\notification::NOTIFY_INFO
        );
    }

    $formurl = new moodle_url($baseurl, ['tab' => 'trades']);
    $sugform = new \block_playerhud\form\suggest_trades_form($formurl, ['suggestions' => $suggestions]);

    if ($action === 'suggest_trades') {
        $sugform->set_data([
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'action'     => 'save_suggest_trades',
        ]);
    }

    if ($sugform->is_cancelled()) {
        redirect(new moodle_url($baseurl, ['tab' => 'trades']));
    } else if ($data = $sugform->get_data()) {
        $count = \block_playerhud\controller\suggestions::save_trade_suggestions($instanceid, $suggestions, $data);
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('trade_sug_created', 'block_playerhud', $count),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $PAGE->requires->js_call_amd('block_playerhud/manage_trades', 'init', [[
        'strings' => [
            'confirm_title' => get_string('confirmation', 'admin'),
            'yes'           => get_string('yes'),
            'cancel'        => get_string('cancel'),
        ],
    ]]);

    echo $OUTPUT->header();
    $sugform->display();
    echo $OUTPUT->footer();
    exit;
}

// PRE-RENDER LOGIC (Controller Strategy).
$contenthtml = '';

// Try to load the tab controller.
$renderclass = "\\block_playerhud\\output\\manage\\tab_{$activetab}";
if (class_exists($renderclass)) {
    $renderer = new $renderclass($instanceid, $courseid, $sort, $dir);

    if (method_exists($renderer, 'process')) {
        $renderer->process();
    }

    if ($renderer instanceof \templatable) {
        if (method_exists($renderer, 'display')) {
            $contenthtml = $renderer->display();
        } else {
            $contenthtml = $OUTPUT->render_from_template(
                "block_playerhud/tab_{$activetab}",
                $renderer->export_for_template($OUTPUT)
            );
        }
    } else {
        if (method_exists($renderer, 'display')) {
            $contenthtml = $renderer->display();
        }
    }
} else {
    $contenthtml = $OUTPUT->notification(
        get_string('tab_maintenance', 'block_playerhud', ucfirst($activetab)),
        'info'
    );
}

echo $OUTPUT->header();

// Tab Definitions.
$tabsdef = [
    'items'    => $itemsenabled ? ['icon' => '📚', 'text' => get_string('tab_items', 'block_playerhud')] : null,
    'trades'   => $itemsenabled ? ['icon' => '🛒', 'text' => get_string('tab_trades', 'block_playerhud')] : null,
    'quests'   => $questsenabled ? ['icon' => '🎯', 'text' => get_string('tab_quests', 'block_playerhud')] : null,
    'classes'  => $rpgmodeenabled ? ['icon' => '⚔️', 'text' => get_string('tab_classes', 'block_playerhud')] : null,
    'chapters' => $rpgmodeenabled ? ['icon' => '📖', 'text' => get_string('tab_chapters', 'block_playerhud')] : null,
    'reports'   => ['icon' => '📊', 'text' => get_string('tab_reports', 'block_playerhud')],
    'assistant' => ['icon' => '🤖', 'text' => get_string('tab_assistant', 'block_playerhud')],
    'config'    => ['icon' => '🛠️', 'text' => get_string('tab_config', 'block_playerhud')],
];

$tabsdata = [];
foreach ($tabsdef as $key => $data) {
    if ($data === null) {
        continue;
    }
    $tabsdata[] = [
        'active' => ($activetab == $key),
        'url' => (new moodle_url($baseurl, ['tab' => $key]))->out(false),
        'icon' => $data['icon'],
        'text' => $data['text'],
    ];
}

// Wizard modal: rendered once here so the header button works from any tab.
$wizardtoneoptions = [
    ['value' => 'scifi', 'label' => get_string('wizard_tone_scifi', 'block_playerhud')],
    ['value' => 'fantasy', 'label' => get_string('wizard_tone_fantasy', 'block_playerhud')],
    ['value' => 'mystery', 'label' => get_string('wizard_tone_mystery', 'block_playerhud')],
    ['value' => 'academic', 'label' => get_string('wizard_tone_academic', 'block_playerhud')],
];
$wizardsizeoptions = [
    ['value' => 'short', 'label' => get_string('wizard_size_short', 'block_playerhud')],
    ['value' => 'long', 'label' => get_string('wizard_size_long', 'block_playerhud')],
];
$modalwizardhtml = $OUTPUT->render_from_template('block_playerhud/modal_wizard', [
    'instanceid' => $instanceid,
    'courseid' => $courseid,
    'tone_options' => $wizardtoneoptions,
    'size_options' => $wizardsizeoptions,
]);

$PAGE->requires->js_call_amd('block_playerhud/wizard', 'init', [[
    'instanceid' => $instanceid,
    'courseid'   => $courseid,
]]);

// Data for Layout.
$layoutdata = [
    'str_title' => get_string('master_panel', 'block_playerhud'),
    'url_student_area' => (new moodle_url('/blocks/playerhud/view.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false),
    'str_student_area' => get_string('student_area', 'block_playerhud'),
    'url_help' => (new moodle_url('/blocks/playerhud/help.php', [
        'id'         => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false),
    'str_help' => get_string('help_btn', 'block_playerhud'),
    'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'str_back_course' => get_string('back_to_course', 'block_playerhud'),
    'str_wizard_open' => get_string('wizard_open_btn', 'block_playerhud'),
    'wizard_show_empty_banner' => $wizardshowemptybanner,
    'str_wizard_empty_title' => get_string('wizard_empty_title', 'block_playerhud'),
    'str_wizard_empty_desc' => get_string('wizard_empty_desc', 'block_playerhud'),
    'str_wizard_empty_btn' => get_string('wizard_empty_btn', 'block_playerhud'),
    'modal_wizard_html' => $modalwizardhtml,
    'tabs' => $tabsdata,
    'content_html' => $contenthtml,
];

echo $OUTPUT->render_from_template('block_playerhud/manage_layout', $layoutdata);
echo $OUTPUT->footer();
