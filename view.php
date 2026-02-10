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
 * Main student view script for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$config->enable_rpg = isset($config->enable_rpg) ? $config->enable_rpg : 1;
$config->enable_ranking = isset($config->enable_ranking) ? $config->enable_ranking : 1;

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
    $target_state = optional_param('state', 0, PARAM_INT);
    $returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
    
    \block_playerhud\game::toggle_gamification($instanceid, $USER->id, (bool)$target_state);
    
    // Redireciona para a URL de origem se fornecida, senão recarrega a view atual
    redirect($returnurl ? new moodle_url($returnurl) : $PAGE->url);
}

// Logic: Privacy Toggle.
if ($tab == 'toggle_ranking_pref' && confirm_sesskey()) {
    $newvis = ($player->ranking_visibility == 1) ? 0 : 1;
    \block_playerhud\game::toggle_ranking_visibility($instanceid, $USER->id, $newvis);
    redirect(
        new moodle_url('/blocks/playerhud/view.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'ranking']),
        get_string('privacy_updated', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
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
    // --- OPT-IN SCREEN (Template Based) ---
    $activateurl = new moodle_url($PAGE->url, ['action' => 'toggle_hud', 'state' => 1, 'sesskey' => sesskey()]);
    
    $data = [
        'userpicture' => $OUTPUT->user_picture($USER, ['size' => 100]),
        'title' => get_string('optin_hello', 'block_playerhud', fullname($USER)),
        'message' => get_string('optin_message', 'block_playerhud'),
        'url_yes' => $activateurl->out(false),
        'str_yes' => get_string('optin_yes', 'block_playerhud'),
        'str_no' => get_string('optin_no', 'block_playerhud')
    ];
    
    echo $OUTPUT->render_from_template('block_playerhud/optin', $data);

} else {
    // --- MAIN HUD INTERFACE (Template Based) ---
    
    // A. Header Stats
    $header_html = '';
    if (class_exists('\block_playerhud\output\view\header')) {
        $header = new \block_playerhud\output\view\header($config, $player, $USER);
        $header_html = $OUTPUT->render_from_template('block_playerhud/view_header', $header->export_for_template($OUTPUT));
    }

    // B. Tab Content
    $tab_content_html = '';
    switch ($tab) {
        case 'collection':
            if (class_exists('\block_playerhud\output\view\tab_collection')) {
                $render = new \block_playerhud\output\view\tab_collection($config, $player, $instanceid);
                $tab_content_html = $OUTPUT->render_from_template('block_playerhud/view_collection', $render->export_for_template($OUTPUT));
            }
            break;
        case 'chapters':
            if (class_exists('\block_playerhud\output\view\tab_chapters')) {
                $render = new \block_playerhud\output\view\tab_chapters($config, $player, $instanceid);
                $tab_content_html = $render->display(); // Refactor this class next!
            }
            break;
        case 'shop':
            if (class_exists('\block_playerhud\output\view\tab_shop')) {
                $render = new \block_playerhud\output\view\tab_shop($config, $player, $instanceid, $courseid);
                $tab_content_html = $render->display();
            }
            break;
        case 'ranking':
            if (class_exists('\block_playerhud\output\view\tab_ranking')) {
                $render = new \block_playerhud\output\view\tab_ranking($config, $player, $instanceid, $courseid, $isteacher);
                $tab_content_html = $render->display();
            }
            break;
        case 'quests':
            if (class_exists('\block_playerhud\output\view\tab_quests')) {
                $render = new \block_playerhud\output\view\tab_quests($config, $player, $instanceid, $courseid);
                $tab_content_html = $render->display();
            }
            break;
    }

    if (empty($tab_content_html)) {
        $tab_content_html = $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', ucfirst($tab)), 'info');
    }

// C. Navigation Data (Reordenado: Coleção > Loja > Missões > História > Ranking)
    $tabslist = [];
    $tabs_def = [
        // 1. Coleção (Base)
        'collection' => ['icon' => '🎒', 'text' => get_string('tab_collection', 'block_playerhud')],
        
        // 2. Loja (Economia)
        'shop' => ['icon' => '⚖️', 'text' => get_string('tab_shop', 'block_playerhud')],
        
        // 3. Missões (Objetivos)
        'quests' => ['icon' => '📜', 'text' => get_string('tab_quests', 'block_playerhud')],
        
        // 4. História (Narrativa - Se RPG ativado)
        'chapters' => ($config->enable_rpg) ? ['icon' => '📖', 'text' => get_string('tab_chapters', 'block_playerhud')] : null,
        
        // 5. Ranking (Social - Se ativado)
        'ranking' => ($config->enable_ranking) ? ['icon' => '🏆', 'text' => get_string('leaderboard_title', 'block_playerhud')] : null,
    ];

    foreach ($tabs_def as $key => $def) {
        if ($def) {
            $tabslist[] = [
                'active' => ($tab == $key),
                'url' => (new moodle_url($PAGE->url, ['tab' => $key]))->out(false),
                'icon' => $def['icon'],
                'text' => $def['text']
            ];
        }
    }

    // D. Render Main Layout
    $layout_data = [
        'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        'str_back_course' => get_string('back_to_course', 'block_playerhud'),
        'is_teacher' => $isteacher,
        'url_manage' => $isteacher ? (new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid]))->out(false) : '',
        'str_manage' => get_string('master_panel', 'block_playerhud'),
        'header_html' => $header_html,
        'tabs' => $tabslist,
        'tab_content_html' => $tab_content_html,
        'str_status_active' => get_string('status_active', 'block_playerhud'),
        'url_disable' => (new moodle_url($PAGE->url, ['action' => 'toggle_hud', 'state' => 0, 'sesskey' => sesskey()]))->out(false),
        'str_confirm_disable' => get_string('confirm_disable', 'block_playerhud'),
        'str_disable' => get_string('disable_exit', 'block_playerhud')
    ];

    echo $OUTPUT->render_from_template('block_playerhud/view_layout', $layout_data);

    // E. Initialize JS
    $jsvars = [
        'strings' => [
            'confirm_title' => get_string('confirmation', 'admin'),
            'yes' => get_string('yes'),
            'cancel' => get_string('cancel'),
            'no_desc' => get_string('no_description', 'block_playerhud'),
            'last_collected' => get_string('last_collected', 'block_playerhud')
        ]
    ];
    $PAGE->requires->js_call_amd('block_playerhud/view', 'init', [$jsvars]);
}

echo $OUTPUT->footer();
