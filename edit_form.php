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
 * Block configuration form.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block configuration form class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_playerhud_edit_form extends block_edit_form {
    /**
     * Define specific block settings.
     *
     * @param MoodleQuickForm $mform The form object.
     */
    protected function specific_definition($mform) {

        // Section 1: Level and XP Settings.
        $mform->addElement('header', 'config_level_hdr', get_string('level_settings', 'block_playerhud'));

        // XP per Level.
        $mform->addElement('text', 'config_xp_per_level', get_string('xp_per_level', 'block_playerhud'));
        $mform->setType('config_xp_per_level', PARAM_INT);
        $mform->setDefault('config_xp_per_level', 100);
        $mform->addHelpButton('config_xp_per_level', 'xp_per_level', 'block_playerhud');

        // Max Level.
        $levels = [
            5 => 5,
            10 => 10,
            15 => 15,
            20 => 20,
            50 => 50,
            100 => 100,
        ];
        $mform->addElement('select', 'config_max_levels', get_string('max_levels', 'block_playerhud'), $levels);
        $mform->setDefault('config_max_levels', 20);
        $mform->addHelpButton('config_max_levels', 'max_levels', 'block_playerhud');

        // Section 2: RPG Mode (Hidden).
        $mform->addElement('hidden', 'config_enable_rpg');
        $mform->setType('config_enable_rpg', PARAM_INT);
        $mform->setDefault('config_enable_rpg', 1);

        // Section 3: Ranking.
        $mform->addElement('header', 'config_ranking_hdr', get_string('ranking_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_ranking', get_string('enable_ranking', 'block_playerhud'));
        $mform->setDefault('config_enable_ranking', 1);
        $mform->addHelpButton('config_enable_ranking', 'enable_ranking', 'block_playerhud');

        // Section 4: Help Page Settings (NEW).
        $mform->addElement('header', 'config_help_hdr', get_string('help_title', 'block_playerhud'));

        // Help Content Editor.
        $mform->addElement('editor', 'config_help_content', get_string('help_content_label', 'block_playerhud'));
        $mform->setType('config_help_content', PARAM_RAW); // Allow HTML.
        $mform->addHelpButton('config_help_content', 'help_content_desc', 'block_playerhud');

        // Reset Checkbox.
        $mform->addElement('checkbox', 'config_reset_help', get_string('help_reset_checkbox', 'block_playerhud'));
        $mform->setType('config_reset_help', PARAM_INT);
    }

    /**
     * Set data for the form.
     * Logic: Load default help text if config is empty.
     *
     * @param object $defaults Default data.
     */
    public function set_data($defaults) {
        // Check if help content exists in config.
        // Block config data for editors is usually stored as an array ['text' => ..., 'format' => ...].
        $hascontent = !empty($defaults->config_help_content) &&
                      (is_string($defaults->config_help_content) || !empty($defaults->config_help_content['text']));

        if (!$hascontent) {
            $defaults->config_help_content = [
                'text' => get_string('help_pagedefault', 'block_playerhud'),
                'format' => FORMAT_HTML,
            ];
        }

        parent::set_data($defaults);
    }

    /**
     * Get data from the form.
     * Logic: Handle reset checkbox.
     *
     * @return object|void Data object.
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data) {
            // If reset is checked, force the default text.
            if (!empty($data->config_reset_help)) {
                $data->config_help_content = [
                    'text' => get_string('help_pagedefault', 'block_playerhud'),
                    'format' => FORMAT_HTML,
                ];
                unset($data->config_reset_help); // Don't save the checkbox state.
            }
        }

        return $data;
    }
}

