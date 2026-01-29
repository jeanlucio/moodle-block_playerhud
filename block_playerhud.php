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
 * PlayerHUD Block main class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * block_playerhud class.
 */
class block_playerhud extends block_base {

    /**
     * Initialize block title and properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_playerhud');
    }

    /**
     * Get block content for display.
     *
     * @return stdClass|string
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $USER, $COURSE, $OUTPUT;

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Only show content if user is logged in and not a guest.
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // 1. Load user data via the refactored game class.
        // In blocks, instance->id is the unique identifier for specific settings.
        try {
            $player = \block_playerhud\game::get_player($this->instance->id, $USER->id);
            
            // 2. Prepare data for the template.
            $renderdata = [
                'username' => fullname($USER),
                'xp'       => $player->currentxp ?? 0,
                'viewurl'  => new moodle_url('/blocks/playerhud/view.php', [
                    'id' => $COURSE->id, 
                    'instanceid' => $this->instance->id
                ])
            ];

            // 3. Render the sidebar mustache template.
            $this->content->text = $OUTPUT->render_from_template('block_playerhud/sidebar_view', $renderdata);

        } catch (\Exception $e) {
            // Silently fail or log if the game class is not yet fully migrated.
            $this->content->text = get_string('error_loading_player', 'block_playerhud');
        }

        return $this->content;
    }

    /**
     * Allow multiple instances of the block in the same course.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Define where this block can be added.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'my' => true
        ];
    }
    
    /**
     * Enable block configuration.
     */
    public function has_config() {
        return true;
    }
}
