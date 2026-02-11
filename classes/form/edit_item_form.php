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
 * Item editing form.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing or creating items.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_item_form extends \moodleform {
    /**
     * Definition of the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;

        // General Header.
        $mform->addElement('header', 'general', get_string('general', 'core'));

        // 1. Name.
        $mform->addElement('text', 'name', get_string('item_name', 'block_playerhud'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // 2. XP.
        $mform->addElement('text', 'xp', get_string('xp', 'block_playerhud'));
        $mform->setType('xp', PARAM_INT);
        $mform->setDefault('xp', 100);
        $mform->addHelpButton('xp', 'xp', 'block_playerhud');

        // 3. Description.
        $mform->addElement('editor', 'description', get_string('item_desc', 'block_playerhud'));
        $mform->setType('description', PARAM_RAW);

        // 4. Emoji (Restored field).
        $mform->addElement('text', 'image', get_string('itemimage_emoji', 'block_playerhud'));
        $mform->setType('image', PARAM_TEXT);
        $mform->setDefault('image', '🎁');
        $mform->addHelpButton('image', 'itemimage_emoji', 'block_playerhud');

        // 5. File Upload.
        $fileoptions = [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.png', '.jpg', '.gif', '.jpeg', '.svg', '.webp'],
        ];
        $mform->addElement(
            'filemanager',
            'image_file',
            get_string('uploadfile', 'block_playerhud'),
            null,
            $fileoptions
        );

        // Visual/Rules Header.
        $mform->addElement('header', 'visual', get_string('visualrules', 'block_playerhud'));

        // 6. Enabled.
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'block_playerhud'));
        $mform->setDefault('enabled', 1);

        // 7. Class Restriction (Disabled for V1.0 launch).
        // Set as '0' (Public) via hidden field to maintain Controller compatibility.
        $mform->addElement('hidden', 'required_class_id');
        $mform->setType('required_class_id', PARAM_INT);
        $mform->setDefault('required_class_id', 0);

        // 8. Secret.
        $mform->addElement(
            'advcheckbox',
            'secret',
            get_string('secret', 'block_playerhud'),
            get_string('secretdesc', 'block_playerhud'),
            [],
            [0, 1]
        );
        $mform->setDefault('secret', 0);
        $mform->addHelpButton('secret', 'secret', 'block_playerhud');

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'blockinstanceid');
        $mform->setType('blockinstanceid', PARAM_INT);

        // Buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate incoming data.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors array.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['name'])) {
            $errors['name'] = get_string('required');
        }
        if (!is_numeric($data['xp'])) {
            $errors['xp'] = get_string('validate_number', 'core');
        }
        return $errors;
    }
}
