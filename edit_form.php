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
        $mform->addHelpButton('config_help_content', 'help_content_label', 'block_playerhud');

        // Reset Checkbox.
        $mform->addElement('checkbox', 'config_reset_help', get_string('help_reset_checkbox', 'block_playerhud'));
        $mform->setType('config_reset_help', PARAM_INT);
        $mform->setDefault('config_reset_help', 0);
    }

    /**
     * Fill in data before displaying the form.
     * Ensures compatibility with Moodle's editor element which strictly requires an array.
     *
     * @param object $defaults Default data loaded from the DB.
     */
    public function set_data($defaults) {
        // Force checkbox to always start unchecked and clear any legacy DB state
        // to prevent parent::set_data from marking it as checked automatically.
        $defaults->config_reset_help = 0;
        if (isset($this->block->config)) {
            unset($this->block->config->reset_help);
            unset($this->block->config->config_reset_help);
        }

        $text = '';
        $format = FORMAT_HTML;

        // Priority 1: From block config (DB).
        if (isset($this->block->config) && isset($this->block->config->help_content)) {
            $content = $this->block->config->help_content;
            if (is_array($content)) {
                $text = $content['text'] ?? '';
                $format = $content['format'] ?? FORMAT_HTML;
            } else if (is_object($content)) {
                $text = $content->text ?? '';
                $format = $content->format ?? FORMAT_HTML;
            } else {
                $text = (string)$content;
            }
        } else if (isset($defaults->config_help_content)) {
            // Priority 2: From passed defaults.
            $content = $defaults->config_help_content;
            if (is_array($content)) {
                $text = $content['text'] ?? '';
                $format = $content['format'] ?? FORMAT_HTML;
            } else {
                $text = (string)$content;
            }
        }

        // Inject default text if DB content is empty.
        if (empty(trim($text))) {
            $text = get_string('help_pagedefault', 'block_playerhud');
        }

        // The Moodle editor element strictly requires an array with 'text' and 'format' keys.
        $editordata = [
            'text' => $text,
            'format' => $format,
        ];

        $defaults->config_help_content = $editordata;

        // CRITICAL: Update block config before parent::set_data maps it,
        // preventing the parent from reverting our injected default back to empty.
        if (isset($this->block->config)) {
            $this->block->config->help_content = $editordata;
        }

        parent::set_data($defaults);
    }

    /**
     * Intercept submitted data before Moodle saves it.
     *
     * @return object|void Processed data.
     */
    public function get_data() {
        $data = parent::get_data();

        if ($data) {
            $submittedtext = '';
            if (isset($data->config_help_content)) {
                $submittedtext = is_array($data->config_help_content)
                    ? $data->config_help_content['text']
                    : $data->config_help_content;
            }

            $defaulttext = get_string('help_pagedefault', 'block_playerhud');

            // If reset is checked OR text matches default: save empty to force dynamic language fetch.
            if (!empty($data->config_reset_help) || trim($submittedtext) === trim($defaulttext)) {
                $data->config_help_content = [
                    'text' => '',
                    'format' => FORMAT_HTML,
                ];
            }

            // Prevent reset checkbox state from saving to the database.
            unset($data->config_reset_help);
        }

        return $data;
    }
}
