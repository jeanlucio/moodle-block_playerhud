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
$action     = optional_param('action', '', PARAM_ALPHA);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi     = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);
$context = context_block::instance($instanceid);

// Check permissions. 
// Note: We use 'view' capability for students.
require_capability('block/playerhud:view', $context);

// Load Block Configuration.
$config = unserialize(base64_decode($bi->configdata));
if (!$config) {
    $config = new stdClass();
}
// Defaults settings.
$config->enable_rpg = isset($config->enable_rpg) ? $config->enable_rpg : 1;
$config->enable_ranking = isset($config->enable_ranking) ? $config->enable_ranking : 1;

// 2. Page Setup.
$PAGE->set_url('/blocks/playerhud/view.php', ['id' => $courseid, 'instanceid' => $instanceid]);
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// 3. Controller Logic.

// Get Player Data via Game Class.
$player = \block_playerhud\game::get_player($instanceid, $USER->id);
$isteacher = has_capability('block/playerhud:manage', $context);

// Auto-Enable Teacher to avoid confusion (Teachers don't need to opt-in).
if ($isteacher && empty($player->enable_gamification)) {
    \block_playerhud\game::toggle_gamification($instanceid, $USER->id, true);
    $player->enable_gamification = 1;
}

// Logic: Opt-in / Opt-out Actions.
if ($action === 'toggle_hud' && confirm_sesskey()) {
    $target_state = optional_param('state', 0, PARAM_INT); // 1 = On, 0 = Off
    \block_playerhud\game::toggle_gamification($instanceid, $USER->id, (bool)$target_state);
    redirect($PAGE->url);
}

// Logic: Privacy Toggle (Leaderboard visibility).
if ($tab == 'toggle_ranking_pref' && confirm_sesskey()) {
    $newvis = ($player->ranking_visibility == 1) ? 0 : 1;
    \block_playerhud\game::toggle_ranking_visibility($instanceid, $USER->id, $newvis);
    redirect(
        new moodle_url('/blocks/playerhud/view.php', ['id' => $courseid, 'instanceid' => $instanceid, 'tab' => 'ranking']),
        get_string('privacy_updated', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Update Last View Timestamp (only if opted-in).
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
    // --- OPT-IN SCREEN (Welcome Screen) ---
    // This is shown if the user has NOT accepted gamification yet.
    
    $activateurl = new moodle_url($PAGE->url, ['action' => 'toggle_hud', 'state' => 1, 'sesskey' => sesskey()]);
    $stryes = get_string('optin_yes', 'block_playerhud');
    $strno = get_string('optin_no', 'block_playerhud');
    $strhello = get_string('optin_hello', 'block_playerhud', fullname($USER));
    $strmsg = get_string('optin_message', 'block_playerhud');

    echo '
    <div class="container text-center py-5 animate__animated animate__fadeIn">
        <div class="card shadow-lg mx-auto" style="max-width: 500px; border-radius: 15px; border: none;">
            <div class="card-body p-5">
                <div class="mb-4">' . $OUTPUT->user_picture($USER, ['size' => 100]) . '</div>
                <h2 class="mb-3">' . $strhello . '</h2>
                <p class="text-muted mb-4" style="font-size: 1.1rem;">' . $strmsg . '</p>
                <div class="d-grid gap-2">
                    <a href="' . $activateurl->out() . '" class="btn btn-primary btn-lg btn-block shadow-sm">
                        ' . $stryes . '
                    </a>
                    <button class="btn btn-link text-muted btn-sm mt-3" onclick="history.back()">
                        ' . $strno . '
                    </button>
                </div>
            </div>
        </div>
    </div>';

} else {
    // --- MAIN HUD INTERFACE (Logged in and Playing) ---
    echo '<div class="playerhud-container">';

echo '<div class="playerhud-container">';

    // Botão Voltar ao Curso (Novo)
    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    echo '<div class="mb-3 d-flex justify-content-between align-items-center">';
    echo \html_writer::link(
        $courseurl,
        '<i class="fa fa-arrow-left"></i> ' . get_string('back_to_course', 'block_playerhud'),
        ['class' => 'btn btn-outline-secondary btn-sm shadow-sm']
    );
    
    // Botão Admin (Se for professor) - Mantivemos a lógica existente, só ajustamos o layout
    if ($isteacher) {
        $manageurl = new moodle_url('/blocks/playerhud/manage.php', ['id' => $courseid, 'instanceid' => $instanceid]);
        echo \html_writer::link(
            $manageurl,
            '<i class="fa fa-cogs"></i> ' . get_string('master_panel', 'block_playerhud'),
            ['class' => 'btn btn-primary btn-sm shadow-sm']
        );
    }
    echo '</div>'; // Fim da barra de topo

    // Render Header (XP Bar, Level).
    // Uses the class provided by the block structure.
    if (class_exists('\block_playerhud\output\view\header')) {
        $header = new \block_playerhud\output\view\header($config, $player, $USER);
        echo $header->display();
    } else {
        // Fallback header (Development safety).
        $stats = \block_playerhud\game::get_game_stats($config, $instanceid, $player->currentxp);
        echo $OUTPUT->box(
            html_writer::tag('h3', 'Level ' . $stats['level'] . ' - ' . $player->currentxp . ' XP') .
            '<div class="progress" style="height: 20px;">
                <div class="progress-bar bg-info" style="width: ' . $stats['progress'] . '%"></div>
             </div>',
            'generalbox mb-4 p-3'
        );
    }

    // Navigation Tabs.
    echo '<ul class="nav nav-pills mb-4 ph-nav-pills" id="ph-student-tabs">';
    $tabslist = [];

    $tabslist['collection'] = [
        'icon' => '🎒',
        'text' => get_string('tab_collection', 'block_playerhud'),
    ];

    if (!empty($config->enable_rpg)) {
        $tabslist['chapters'] = [
            'icon' => '📖',
            'text' => get_string('tab_chapters', 'block_playerhud'),
        ];
    }

    $tabslist['shop'] = [
        'icon' => '⚖️',
        'text' => get_string('tab_shop', 'block_playerhud'),
    ];

    if (!empty($config->enable_ranking)) {
        $tabslist['ranking'] = [
            'icon' => '🏆',
            'text' => get_string('leaderboard_title', 'block_playerhud'),
        ];
    }

    $tabslist['quests'] = [
        'icon' => '📜',
        'text' => get_string('tab_quests', 'block_playerhud'),
    ];

    foreach ($tabslist as $key => $data) {
        $active = ($tab == $key) ? 'active' : '';
        $url = new moodle_url($PAGE->url, ['tab' => $key]);
        echo '<li class="nav-item">
                <a class="nav-link ' . $active . '" href="' . $url->out() . '">
                    <span aria-hidden="true" class="me-2">' . $data['icon'] . '</span> ' . $data['text'] . '
                </a>
              </li>';
    }
    echo '</ul>';

    // Render Tab Content.
    echo '<div class="tab-content bg-white p-3 rounded shadow-sm" style="min-height: 300px;">';
    
    // We use class_exists to ensure the page loads even if some tab classes are missing during migration.
    switch ($tab) {
        case 'collection':
            if (class_exists('\block_playerhud\output\view\tab_collection')) {
                $render = new \block_playerhud\output\view\tab_collection($config, $player, $instanceid);
                echo $render->display();
            } else {
                echo $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', 'Collection'), 'info');
            }
            break;
        case 'chapters':
            if (class_exists('\block_playerhud\output\view\tab_chapters')) {
                $render = new \block_playerhud\output\view\tab_chapters($config, $player, $instanceid);
                echo $render->display();
            } else {
                echo $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', 'Chapters'), 'info');
            }
            break;
        case 'shop':
            if (class_exists('\block_playerhud\output\view\tab_shop')) {
                $render = new \block_playerhud\output\view\tab_shop($config, $player, $instanceid, $courseid);
                echo $render->display();
            } else {
                echo $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', 'Shop'), 'info');
            }
            break;
        case 'ranking':
            if (class_exists('\block_playerhud\output\view\tab_ranking')) {
                $render = new \block_playerhud\output\view\tab_ranking(
                    $config,
                    $player,
                    $instanceid,
                    $courseid,
                    $isteacher
                );
                echo $render->display();
            } else {
                echo $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', 'Ranking'), 'info');
            }
            break;
        case 'quests':
            if (class_exists('\block_playerhud\output\view\tab_quests')) {
                $render = new \block_playerhud\output\view\tab_quests($config, $player, $instanceid, $courseid);
                echo $render->display();
            } else {
                echo $OUTPUT->notification(get_string('tab_maintenance', 'block_playerhud', 'Quests'), 'info');
            }
            break;
    }
    echo '</div>'; // End Tab Content

    // Disable HUD Link (Footer).
    $disableurl = new moodle_url($PAGE->url, ['action' => 'toggle_hud', 'state' => 0, 'sesskey' => sesskey()]);
    $confirmmsg = get_string('confirm_disable', 'block_playerhud');
    
    // We pass the raw string to JS via data attribute.
    echo '<div class="text-center mt-4 mb-3 pt-3 border-top text-muted small">
            ' . get_string('status_active', 'block_playerhud') . '
            <a href="' . $disableurl->out() . '" class="text-danger ms-2 js-disable-hud"
               style="text-decoration: underline;"
               data-confirm-msg="' . s($confirmmsg) . '">
                ' . get_string('disable_exit', 'block_playerhud') . '
            </a>
          </div>';

    echo '</div>'; // End container.

    // --- HTML FOR MODALS (Hidden by default) ---
    // The JavaScript will fill these containers with data when an item is clicked.
    // We keep the HTML here so PHP controls the structure/strings.
    
    $strdetails = get_string('details', 'block_playerhud');
    $strclose = get_string('close', 'block_playerhud');

    echo '
    <div class="modal fade" id="phItemModalView" tabindex="-1" aria-hidden="true" style="z-index: 10500;">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header d-flex justify-content-between align-items-center">
            <h5 class="modal-title fw-bold m-0" id="phModalTitleView">' . $strdetails . '</h5>
            <button type="button" class="btn-close ph-modal-close-view ms-auto"
                    data-bs-dismiss="modal" aria-label="' . $strclose . '"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex align-items-start">
                <div id="phModalImageContainerView" class="me-4 text-center" style="min-width: 100px;"></div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap mb-3">
                        <h4 id="phModalNameView" class="m-0 me-2" style="font-weight: bold;"></h4>
                        <span id="phModalCountBadgeView" class="badge bg-dark rounded-pill me-2 ph-badge-count"
                              style="display:none;">x0</span>
                        <span id="phModalXPView" class="badge bg-info text-dark ph-badge-count">XP</span>
                    </div>
                    <div id="phModalDescView" class="text-muted text-break"></div>
                   <div id="phModalDateView" class="mt-3 small text-success fw-bold border-top pt-2"
                       style="display:none;">
                        <i class="fa fa-calendar-check-o" aria-hidden="true"></i> <span></span>
                    </div>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary ph-modal-close-view"
                    data-bs-dismiss="modal">' . $strclose . '</button>
          </div>
        </div>
      </div>
    </div>';

    // --- JAVASCRIPT CALL (The Moodle Way) ---
    // This replaces all inline <script> tags.
    // Ensure you have run "grunt amd" to build blocks/playerhud/amd/src/view.js
    
    $jsvars = [
        'strings' => [
            'confirm_title' => get_string('confirmation', 'admin'),
            'yes' => get_string('yes'),
            'cancel' => get_string('cancel'),
            'no_desc' => get_string('no_description', 'block_playerhud')
        ]
    ];
    
    // Call the AMD module.
    $PAGE->requires->js_call_amd('block_playerhud/view', 'init', [$jsvars]);
}

echo $OUTPUT->footer();
