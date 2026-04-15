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
 * Global Admin settings for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // AI Settings Section.
    $settings->add(new admin_setting_heading(
        'block_playerhud/aisettings',
        get_string('api_settings_title', 'block_playerhud'),
        get_string('api_settings_desc', 'block_playerhud')
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
}
