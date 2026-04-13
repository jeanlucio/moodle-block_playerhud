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

namespace block_playerhud\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing RPG classes.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_class_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $classid = $this->_customdata['classid'];

        $headerlabel = $classid
            ? get_string('class_edit', 'block_playerhud')
            : get_string('class_new', 'block_playerhud');

        $mform->addElement('header', 'general', $headerlabel);

        // Class name.
        $mform->addElement('text', 'name', get_string('class_name', 'block_playerhud'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description.
        $mform->addElement('textarea', 'description', get_string('class_desc', 'block_playerhud'), [
            'rows' => 4,
            'cols' => 60,
        ]);
        $mform->setType('description', PARAM_CLEANHTML);

        // Base HP.
        $mform->addElement('text', 'base_hp', get_string('class_base_hp', 'block_playerhud'), [
            'type' => 'number',
            'min' => '1',
        ]);
        $mform->setType('base_hp', PARAM_INT);
        $mform->setDefault('base_hp', 100);
        $mform->addRule('base_hp', null, 'required', null, 'client');

        // Evolution images header.
        $mform->addElement('header', 'images_hdr', get_string('class_images_hdr', 'block_playerhud'));
        $mform->setExpanded('images_hdr');

        $imghelp = \html_writer::tag(
            'p',
            get_string('class_img_help', 'block_playerhud'),
            ['class' => 'text-muted small']
        );
        $mform->addElement('static', 'img_help_intro', '', $imghelp);

        $tierlabels = [
            1 => get_string('tier_1', 'block_playerhud'),
            2 => get_string('tier_2', 'block_playerhud'),
            3 => get_string('tier_3', 'block_playerhud'),
            4 => get_string('tier_4', 'block_playerhud'),
            5 => get_string('tier_5', 'block_playerhud'),
        ];

        $fileoptions = [
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp'],
        ];

        for ($tier = 1; $tier <= 5; $tier++) {
            $mform->addElement('filemanager', 'image_tier' . $tier, $tierlabels[$tier], null, $fileoptions);
        }

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'classid');
        $mform->setType('classid', PARAM_INT);

        $this->add_action_buttons();
    }
}
