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
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_playerhud_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        // --- Seção 1: Configurações de Nível e XP ---
        $mform->addElement('header', 'config_level_hdr', get_string('level_settings', 'block_playerhud'));

        // XP por Nível (Padrão 100)
        $mform->addElement('text', 'config_xp_per_level', get_string('xp_per_level', 'block_playerhud'));
        $mform->setType('config_xp_per_level', PARAM_INT);
        $mform->setDefault('config_xp_per_level', 100);
        $mform->addHelpButton('config_xp_per_level', 'xp_per_level', 'block_playerhud');

        // Nível Máximo (Dropdown 5, 10, 15, 20, 50, 99)
        $levels = [
            5 => 5,
            10 => 10,
            15 => 15,
            20 => 20,
            50 => 50,
            99 => 99
        ];
        $mform->addElement('select', 'config_max_levels', get_string('max_levels', 'block_playerhud'), $levels);
        $mform->setDefault('config_max_levels', 20);
        $mform->addHelpButton('config_max_levels', 'max_levels', 'block_playerhud');

        // --- Seção 2: Modo RPG e História ---
        $mform->addElement('header', 'config_rpg_hdr', get_string('rpg_settings', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_rpg', get_string('enable_rpg', 'block_playerhud'));
        $mform->setDefault('config_enable_rpg', 1);
        $mform->addHelpButton('config_enable_rpg', 'enable_rpg', 'block_playerhud');

        // --- Seção 3: Ranking ---
        $mform->addElement('header', 'config_ranking_hdr', get_string('ranking_hdr', 'block_playerhud'));

        $mform->addElement('selectyesno', 'config_enable_ranking', get_string('enable_ranking', 'block_playerhud'));
        $mform->setDefault('config_enable_ranking', 1);
        $mform->addHelpButton('config_enable_ranking', 'enable_ranking', 'block_playerhud');
    }
}
