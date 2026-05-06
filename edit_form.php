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
 * Block configuration form.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Block configuration form class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        // Section 2: Items and Commerce.
        $mform->addElement('header', 'config_items_hdr', get_string('items_commerce_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_items', get_string('enable_items', 'block_playerhud'));
        $mform->setDefault('config_enable_items', 1);
        $mform->addHelpButton('config_enable_items', 'enable_items', 'block_playerhud');

        // Section 3: Quests.
        $mform->addElement('header', 'config_quests_hdr', get_string('quests_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_quests', get_string('enable_quests', 'block_playerhud'));
        $mform->setDefault('config_enable_quests', 1);
        $mform->addHelpButton('config_enable_quests', 'enable_quests', 'block_playerhud');

        // Section 4: RPG Mode.
        $mform->addElement('header', 'config_rpg_hdr', get_string('rpg_mode_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_rpg', get_string('enable_rpg_mode', 'block_playerhud'));
        $mform->setDefault('config_enable_rpg', 1);
        $mform->addHelpButton('config_enable_rpg', 'enable_rpg_mode', 'block_playerhud');

        // Section 5: Ranking.
        $mform->addElement('header', 'config_ranking_hdr', get_string('ranking_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_ranking', get_string('enable_ranking', 'block_playerhud'));
        $mform->setDefault('config_enable_ranking', 1);
        $mform->addHelpButton('config_enable_ranking', 'enable_ranking', 'block_playerhud');

        $mform->addElement(
            'selectyesno',
            'config_enable_group_ranking',
            get_string('enable_group_ranking', 'block_playerhud')
        );
        $mform->setDefault('config_enable_group_ranking', 1);
        $mform->addHelpButton('config_enable_group_ranking', 'enable_group_ranking', 'block_playerhud');
        $mform->hideIf('config_enable_group_ranking', 'config_enable_ranking', 'eq', 0);

        // Section 6: Help Page Settings.
        $mform->addElement('header', 'config_help_hdr', get_string('help_title', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_use_default_help', get_string('help_use_default', 'block_playerhud'));
        $mform->setDefault('config_use_default_help', 1);

        $notenote = '<div class="alert alert-info py-2 mb-2">' .
            get_string('help_use_default_note', 'block_playerhud') .
            '</div>';
        $mform->addElement(
            'static',
            'help_use_default_note',
            '',
            $notenote
        );
        $mform->hideIf('help_use_default_note', 'config_use_default_help', 'neq', 1);

        // Help Content Editor (always visible so TinyMCE initialises correctly).
        $mform->addElement('editor', 'config_help_content', get_string('help_content_label', 'block_playerhud'));
        $mform->setType('config_help_content', PARAM_RAW);
        $mform->addHelpButton('config_help_content', 'help_content_label', 'block_playerhud');
    }

    /**
     * Fill in data before displaying the form.
     * Ensures compatibility with Moodle's editor element which strictly requires an array.
     *
     * @param object $defaults Default data loaded from the DB.
     */
    public function set_data($defaults) {
        if (isset($this->block->config)) {
            // Remove legacy reset flag if still present in DB.
            unset($this->block->config->reset_help);
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

        // When the editor is empty, pre-populate with the full default template so
        // the teacher has a starting point. Saving this content is harmless because
        // use_default_help controls what students see, not the emptiness of help_content.
        if (empty(trim($text))) {
            global $OUTPUT;
            $allflags = ['show_items' => true, 'show_quests' => true, 'show_rpg' => true, 'show_ranking' => true];
            $text = $OUTPUT->render_from_template('block_playerhud/help_default', $allflags);
            // TinyMCE strips empty inline elements (e.g. Font Awesome <i> tags).
            // A zero-width space inside each empty <i> prevents that.
            $text = preg_replace('/<i\b([^>]*)>\s*<\/i>/i', '<i$1>&#8203;</i>', $text);
        }

        $editordata = ['text' => $text, 'format' => $format];
        $defaults->config_help_content = $editordata;

        if (isset($this->block->config)) {
            $this->block->config->help_content = $editordata;
        }

        parent::set_data($defaults);
    }
}
