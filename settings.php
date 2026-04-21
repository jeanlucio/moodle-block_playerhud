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
 * Global Admin settings for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // AI Settings Section.
    $settings->add(new admin_setting_heading(
        'block_playerhud/aisettings',
        get_string('api_admin_settings_title', 'block_playerhud'),
        get_string('api_admin_settings_desc', 'block_playerhud')
    ));

    // Gemini Key.
    $settings->add(new admin_setting_configtext(
        'block_playerhud/apikey_gemini',
        get_string('gemini_apikey', 'block_playerhud'),
        get_string('gemini_apikey_desc', 'block_playerhud'),
        '',
        PARAM_TEXT
    ));

    // Groq Key.
    $settings->add(new admin_setting_configtext(
        'block_playerhud/apikey_groq',
        get_string('groq_apikey', 'block_playerhud'),
        get_string('groq_apikey_desc', 'block_playerhud'),
        '',
        PARAM_TEXT
    ));

    // Custom AI (OpenAI-compatible) Section.
    $settings->add(new admin_setting_heading(
        'block_playerhud/openaisettings',
        get_string('openai_settings_title', 'block_playerhud'),
        get_string('openai_settings_desc', 'block_playerhud')
    ));

    // Custom AI Key.
    $settings->add(new admin_setting_configtext(
        'block_playerhud/apikey_openai',
        get_string('openai_apikey', 'block_playerhud'),
        get_string('openai_apikey_desc', 'block_playerhud'),
        '',
        PARAM_TEXT
    ));

    // Custom AI Base URL.
    $settings->add(new admin_setting_configtext(
        'block_playerhud/openai_baseurl',
        get_string('openai_baseurl', 'block_playerhud'),
        get_string('openai_baseurl_desc', 'block_playerhud'),
        '',
        PARAM_URL
    ));

    // Custom AI Model.
    $settings->add(new admin_setting_configtext(
        'block_playerhud/openai_model',
        get_string('openai_model', 'block_playerhud'),
        get_string('openai_model_desc', 'block_playerhud'),
        '',
        PARAM_TEXT
    ));

    // Companion Plugins Section.
    $pluginmanager = \core_plugin_manager::instance();
    $companionplugins = [
        'availability_playerhud' => [
            'name' => get_string('companion_availability_name', 'block_playerhud'),
            'desc' => get_string('companion_availability_desc', 'block_playerhud'),
        ],
        'filter_playerhud' => [
            'name' => get_string('companion_filter_name', 'block_playerhud'),
            'desc' => get_string('companion_filter_desc', 'block_playerhud'),
        ],
    ];

    $companionhtml = \html_writer::start_tag('ul', ['class' => 'list-unstyled mt-2']);
    foreach ($companionplugins as $pluginname => $info) {
        $installed = $pluginmanager->get_plugin_info($pluginname) !== null;
        if ($installed) {
            $statusbadge = \html_writer::tag(
                'span',
                '&#10003; ' . get_string('companion_present', 'block_playerhud'),
                ['class' => 'badge bg-success']
            );
        } else {
            $statusbadge = \html_writer::tag(
                'span',
                '&#10007; ' . get_string('companion_absent', 'block_playerhud'),
                ['class' => 'badge bg-danger']
            );
        }
        $namehtml = \html_writer::tag('strong', $info['name']);
        $deschtml = \html_writer::tag('span', ' &mdash; ' . $info['desc'], ['class' => 'text-muted']);
        $itemcontent = $namehtml . $deschtml . \html_writer::tag('div', $statusbadge, ['class' => 'mt-1']);
        $companionhtml .= \html_writer::tag('li', $itemcontent, ['class' => 'mb-3']);
    }
    $companionhtml .= \html_writer::end_tag('ul');

    $settings->add(new admin_setting_heading(
        'block_playerhud/companionplugins',
        get_string('companion_plugins_title', 'block_playerhud'),
        get_string('companion_plugins_desc', 'block_playerhud') . $companionhtml
    ));
}
