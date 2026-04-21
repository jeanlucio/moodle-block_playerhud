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

namespace block_playerhud\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing story chapters.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_chapter_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform    = $this->_form;
        $chapterid = $this->_customdata['chapterid'];

        $headerlabel = $chapterid
            ? get_string('chapter_edit', 'block_playerhud')
            : get_string('chapter_new', 'block_playerhud');

        $mform->addElement('header', 'general', $headerlabel);

        // Title.
        $mform->addElement('text', 'title', get_string('chapter_title', 'block_playerhud'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        // Intro text.
        $mform->addElement(
            'textarea',
            'intro_text',
            get_string('chapter_intro', 'block_playerhud'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('intro_text', PARAM_TEXT);

        // Unlock date.
        $mform->addElement('date_time_selector', 'unlock_date', get_string('chapter_unlock_date', 'block_playerhud'), [
            'optional' => true,
        ]);
        $mform->setDefault('unlock_date', 0);

        // Required level.
        $mform->addElement('text', 'required_level', get_string('chapter_required_level', 'block_playerhud'), [
            'type' => 'number',
            'min'  => '0',
        ]);
        $mform->setType('required_level', PARAM_INT);
        $mform->setDefault('required_level', 0);

        // Sort order.
        $mform->addElement('text', 'sortorder', get_string('chapter_sortorder', 'block_playerhud'), [
            'type' => 'number',
            'min'  => '1',
        ]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 1);

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'chapterid');
        $mform->setType('chapterid', PARAM_INT);

        $this->add_action_buttons();
    }
}
