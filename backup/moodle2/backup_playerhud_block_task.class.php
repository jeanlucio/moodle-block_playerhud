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
 * Backup task for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/playerhud/backup/moodle2/backup_playerhud_stepslib.php');

/**
 * Backup task for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_playerhud_block_task extends backup_block_task {
    /**
     * Define specific settings for this backup task.
     */
    protected function define_my_settings() {
        // No special settings for now.
    }

    /**
     * Define steps for this backup task.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_playerhud_block_structure_step('playerhud_structure', 'playerhud.xml'));
    }

    /**
     * Get the file areas to be backed up.
     *
     * @return array Array of file areas.
     */
    public function get_fileareas() {
        return ['item_image'];
    }

    /**
     * Get config data attributes that need processing.
     *
     * @return array Array of attributes.
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Encode content links in the backup.
     *
     * @param string $content Content to be encoded.
     * @return string Encoded content.
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
