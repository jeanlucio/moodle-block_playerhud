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
 * Main student view script for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// 1. Parameters & Setup.
$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$tab        = optional_param('tab', 'collection', PARAM_ALPHANUMEXT);
$action     = optional_param('action', '', PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi     = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = context_block::instance($instanceid);

// Check permissions.
require_capability('block/playerhud:view', $context);

// Load Block Configuration.
$config = unserialize(base64_decode($bi->configdata));
if (!$config) {
    $config = new stdClass();
}
$config->enable_rpg      = isset($config->enable_rpg) ? $config->enable_rpg : 1;
$config->enable_ranking  = isset($config->enable_ranking) ? $config->enable_ranking : 1;
$config->enable_items    = isset($config->enable_items) ? $config->enable_items : 1;
$config->enable_quests   = isset($config->enable_quests) ? $config->enable_quests : 1;

// 2. Page Setup.
$PAGE->set_url('/blocks/playerhud/view.php', ['id' => $courseid, 'instanceid' => $instanceid]);
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// 3. Controller Logic.
$player = \block_playerhud\game::get_player($instanceid, $USER->id);
$isteacher = has_capability('block/playerhud:manage', $context);

// Auto-Enable Teacher.
if ($isteacher && empty($player->enable_gamification)) {
    \block_playerhud\game::toggle_gamification($instanceid, $USER->id, true);
    $player->enable_gamification = 1;
}

// Logic: Opt-in / Opt-out Actions.
if ($action === 'toggle_hud' && confirm_sesskey()) {
    $targetstate = optional_param('state', 0, PARAM_INT);
    $returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

    \block_playerhud\game::toggle_gamification($instanceid, $USER->id, (bool)$targetstate);

    // Redirect to source URL if provided, otherwise reload current view.
    redirect($returnurl ? new moodle_url($returnurl) : $PAGE->url);
}

// Logic: Privacy Toggle.
if ($tab == 'toggle_ranking_pref' && confirm_sesskey()) {
    $newvis = ($player->ranking_visibility == 1) ? 0 : 1;
    \block_playerhud\game::toggle_ranking_visibility($instanceid, $USER->id, $newvis);
    redirect(
        new moodle_url('/blocks/playerhud/view.php', [
            'id' => $courseid,
            'instanceid' => $instanceid,
            'tab' => 'ranking',
        ]),
        get_string('privacy_updated', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Logic: Select RPG Class.
if ($action === 'select_class' && confirm_sesskey()) {
    $classid = required_param('classid', PARAM_INT);
    $DB->get_record('block_playerhud_classes', ['id' => $classid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
    \block_playerhud\game::assign_class($instanceid, $USER->id, $classid);
    redirect(
        new moodle_url($PAGE->url, ['tab' => 'class_select']),
        get_string('class_selected_success', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Logic: Claim Quest Reward.
if ($action === 'claim_quest' && !empty($config->enable_quests) && confirm_sesskey()) {
    $questid  = required_param('questid', PARAM_INT);
    $questurl = new moodle_url($PAGE->url, ['tab' => 'quests']);
    try {
        $rewardstr = \block_playerhud\quest::claim_reward($questid, $USER->id, $instanceid, $courseid);
        redirect(
            $questurl,
            get_string('quest_claimed_success', 'block_playerhud', $rewardstr),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect($questurl, $e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

// Update Last View Timestamp.
$isoptin = ($player->enable_gamification == 0 && !$isteacher);

if (!$isoptin) {
    if ($tab == 'collection') {
        $DB->set_field('block_playerhud_user', 'last_inventory_view', time(), ['id' => $player->id]);
    }
    if ($tab == 'shop') {
        $DB->set_field('block_playerhud_user', 'last_shop_view', time(), ['id' => $player->id]);
    }
}

echo $OUTPUT->header();

// 4. View Render.

if ($isoptin) {
    // Opt-in Screen (Template Based).
    $activateurl = new moodle_url($PAGE->url, ['action' => 'toggle_hud', 'state' => 1, 'sesskey' => sesskey()]);

    $data = [
        'userpicture' => $OUTPUT->user_picture($USER, ['size' => 100]),
        'title' => get_string('optin_hello', 'block_playerhud', fullname($USER)),
        'message' => get_string('optin_message', 'block_playerhud'),
        'url_yes' => $activateurl->out(false),
        'url_no'  => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        'str_yes' => get_string('optin_yes', 'block_playerhud'),
        'str_no' => get_string('optin_no', 'block_playerhud'),
    ];

    echo $OUTPUT->render_from_template('block_playerhud/optin', $data);
} else {
    // Main HUD Interface (Template Based).

    // A. Header Stats.
    $headerhtml = '';
    if (class_exists('\block_playerhud\output\view\header')) {
        $header = new \block_playerhud\output\view\header($config, $player, $USER, $courseid);
        $headerhtml = $OUTPUT->render_from_template(
            'block_playerhud/view_header',
            $header->export_for_template($OUTPUT)
        );
    }

    // B. Tab Content.
    $tabcontenthtml = '';
    switch ($tab) {
        case 'collection':
            if (class_exists('\block_playerhud\output\view\tab_collection')) {
                $render = new \block_playerhud\output\view\tab_collection($config, $player, $instanceid);
                $tabcontenthtml = $OUTPUT->render_from_template(
                    'block_playerhud/view_collection',
                    $render->export_for_template($OUTPUT)
                );
            }
            break;
        case 'class_select':
            if (class_exists('\block_playerhud\output\view\tab_class_select')) {
                $render = new \block_playerhud\output\view\tab_class_select(
                    $config,
                    $player,
                    $instanceid,
                    $courseid
                );
                $tabcontenthtml = $render->display();
            }
            break;
        case 'chapters':
            if (class_exists('\block_playerhud\output\view\tab_chapters')) {
                $render = new \block_playerhud\output\view\tab_chapters($config, $player, $instanceid, $courseid);
                $tabcontenthtml = $render->display();
            }
            break;
        case 'shop':
            if (class_exists('\block_playerhud\output\view\tab_shop')) {
                $render = new \block_playerhud\output\view\tab_shop($config, $player, $instanceid, $courseid);
                $tabcontenthtml = $render->display();
            }
            break;
        case 'ranking':
            if (class_exists('\block_playerhud\output\view\tab_ranking')) {
                $render = new \block_playerhud\output\view\tab_ranking($config, $player, $instanceid, $courseid, $isteacher);
                $tabcontenthtml = $render->display();
            }
            break;
        case 'quests':
            if (class_exists('\block_playerhud\output\view\tab_quests')) {
                $render = new \block_playerhud\output\view\tab_quests($config, $player, $instanceid, $courseid);
                $tabcontenthtml = $render->display();
            }
            break;
        case 'history':
            if (class_exists('\block_playerhud\output\view\tab_history')) {
                $render = new \block_playerhud\output\view\tab_history($config, $player, $instanceid);
                $tabcontenthtml = $render->display();
            }
            break;
        case 'rules':
            if (class_exists('\block_playerhud\output\view\tab_rules')) {
                // Prepare config object for the renderer.
                $cleanconfig = new stdClass();
                // Map the help_content (Moodle strips the 'config_' prefix when saving to DB).
                $cleanconfig->help_content = isset($config->help_content) ?
                    $config->help_content : null;

                $render = new \block_playerhud\output\view\tab_rules($cleanconfig);
                $tabcontenthtml = $render->display();
            }
            break;
    }

    if (empty($tabcontenthtml)) {
        $tabcontenthtml = $OUTPUT->notification(
            get_string('tab_maintenance', 'block_playerhud', ucfirst($tab)),
            'info'
        );
    }

    // C. Navigation Data.
    $tabslist = [];
    $tabsdef = [
        // 1. Collection and commerce (only if items feature is enabled).
        'collection' => (!empty($config->enable_items)) ? [
            'icon' => '🎒',
            'text' => get_string('tab_collection', 'block_playerhud'),
        ] : null,
        'shop' => (!empty($config->enable_items)) ? [
            'icon' => '🛒',
            'text' => get_string('tab_shop', 'block_playerhud'),
        ] : null,

        // 2. Quests tab (only if quests feature is enabled).
        'quests' => (!empty($config->enable_quests)) ? [
            'icon' => '🎯',
            'text' => get_string('tab_quests', 'block_playerhud'),
        ] : null,

        // Story/Chapters tab (only if RPG mode is enabled).
        'chapters' => (!empty($config->enable_rpg)) ? [
            'icon' => '📖',
            'text' => get_string('tab_chapters', 'block_playerhud'),
        ] : null,

        // Ranking (Social - If enabled in configs).
        'ranking' => ($config->enable_ranking) ? [
            'icon' => '🏆',
            'text' => get_string('leaderboard_title', 'block_playerhud'),
        ] : null,

        'history'    => ['icon' => '📜', 'text' => get_string('tab_history', 'block_playerhud')],
        'rules'      => ['icon' => '❓', 'text' => get_string('tab_rules', 'block_playerhud')],
    ];

    foreach ($tabsdef as $key => $def) {
        if ($def) {
            $tabslist[] = [
                'active' => ($tab == $key),
                'url' => (new moodle_url($PAGE->url, ['tab' => $key]))->out(false),
                'icon' => $def['icon'],
                'text' => $def['text'],
            ];
        }
    }

    // D. Render Main Layout.
    $urlmanage = $isteacher ? (new moodle_url('/blocks/playerhud/manage.php', [
        'id' => $courseid,
        'instanceid' => $instanceid,
    ]))->out(false) : '';

    $layoutdata = [
        'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        'str_back_course' => get_string('back_to_course', 'block_playerhud'),
        'is_teacher' => $isteacher,
        'url_manage' => $urlmanage,
        'str_manage' => get_string('master_panel', 'block_playerhud'),
        'header_html' => $headerhtml,
        'tabs' => $tabslist,
        'tab_content_html' => $tabcontenthtml,
        'str_status_active' => get_string('status_active', 'block_playerhud'),
        'url_disable' => (new moodle_url($PAGE->url, [
            'action' => 'toggle_hud',
            'state' => 0,
            'sesskey' => sesskey(),
        ]))->out(false),
        'str_confirm_disable' => get_string('confirm_disable', 'block_playerhud'),
        'str_disable' => get_string('disable_exit', 'block_playerhud'),
    ];

    echo $OUTPUT->render_from_template('block_playerhud/view_layout', $layoutdata);

    // E. Initialize JS.
    $jsvars = [
        'strings' => [
            'confirm_title' => get_string('confirmation', 'admin'),
            'yes' => get_string('yes'),
            'cancel' => get_string('cancel'),
            'no_desc' => get_string('no_description', 'block_playerhud'),
            'last_collected' => get_string('last_collected', 'block_playerhud'),
            'collected' => get_string('collected', 'block_playerhud'),
            'respawntime' => get_string('respawntime', 'block_playerhud'),
        ],
    ];
    $PAGE->requires->js_call_amd('block_playerhud/view', 'init', [$jsvars]);
}

echo $OUTPUT->footer();
