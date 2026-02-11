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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/playerhud/backup/moodle2/restore_playerhud_stepslib.php');

/**
 * Restore task for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_playerhud_block_task extends restore_block_task {
    /**
     * Define the settings for this restore task.
     */
    protected function define_my_settings() {
        // No special settings.
    }

    /**
     * Define the steps for this restore task.
     */
    protected function define_my_steps() {
        // Add the step that reads the XML and writes to the DB.
        $this->add_step(new restore_playerhud_block_structure_step('playerhud_structure', 'playerhud.xml'));
    }

    /**
     * Get the file areas to be restored.
     *
     * @return array The file areas.
     */
    public function get_fileareas() {
        return ['item_image'];
    }

    /**
     * Define the configdata attributes that need processing.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Define the decode contents.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define the decode rules.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }
}
