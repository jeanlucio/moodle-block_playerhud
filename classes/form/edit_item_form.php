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
 * Item editing form.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing or creating items.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        $itemid = (int) ($this->_customdata['itemid'] ?? 0);
        if ($itemid > 0) {
            $holdercount = $DB->count_records_sql(
                'SELECT COUNT(DISTINCT userid) FROM {block_playerhud_inventory} WHERE itemid = ?',
                [$itemid]
            );
            if ($holdercount > 0) {
                $xpeditwarning = \html_writer::tag(
                    'div',
                    get_string('item_xp_edit_warning', 'block_playerhud', $holdercount),
                    ['class' => 'alert alert-info mb-0 mt-2']
                );
                $mform->addElement('static', 'xp_edit_warning', '', $xpeditwarning);
            }
        }

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

        // 7. Class restriction.
        $instanceid = $this->_customdata['instanceid'] ?? 0;
        $classoptions = [];
        if ($instanceid) {
            $allclasses = $DB->get_records(
                'block_playerhud_classes',
                ['blockinstanceid' => $instanceid],
                'name ASC',
                'id, name'
            );
            foreach ($allclasses as $class) {
                $classoptions[$class->id] = format_string($class->name);
            }
        }
        if (!empty($classoptions)) {
            $mform->addElement(
                'autocomplete',
                'required_class_id',
                get_string('restrict_class', 'block_playerhud'),
                $classoptions,
                ['multiple' => true]
            );
            $mform->setType('required_class_id', PARAM_INT);
            $mform->setDefault('required_class_id', []);
            $mform->addHelpButton('required_class_id', 'restrict_class', 'block_playerhud');
        } else {
            $mform->addElement('hidden', 'required_class_id');
            $mform->setType('required_class_id', PARAM_INT);
            $mform->setDefault('required_class_id', 0);
        }

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

        // Power Header.
        $mform->addElement('header', 'itempower', get_string('item_power', 'block_playerhud'));

        $poweroptions = ['' => get_string('item_power_none', 'block_playerhud')];
        $poweroptions['avatar_profile'] = get_string('item_power_avatar', 'block_playerhud');
        if (class_exists('\local_latepenalty\recalculator')) {
            $poweroptions['deadline_extension'] = get_string('item_power_deadline', 'block_playerhud');
        }
        $mform->addElement('select', 'action_type', get_string('item_power_type', 'block_playerhud'), $poweroptions);
        $mform->setDefault('action_type', '');

        // Avatar note — shown only when action_type = avatar_profile.
        $avatarnote = html_writer::tag(
            'div',
            get_string('item_power_avatar_note', 'block_playerhud'),
            ['class' => 'alert alert-info mb-0 py-2']
        );
        $mform->addElement('static', 'avatar_note', '', $avatarnote);
        $mform->hideIf('avatar_note', 'action_type', 'neq', 'avatar_profile');

        // Deadline fields — only rendered when LP is installed.
        if (class_exists('\local_latepenalty\recalculator')) {
            $mform->addElement('text', 'extension_days', get_string('item_power_extension_days', 'block_playerhud'));
            $mform->setType('extension_days', PARAM_INT);
            $mform->setDefault('extension_days', 1);
            $mform->hideIf('extension_days', 'action_type', 'neq', 'deadline_extension');

            $lpactivities = $this->_customdata['lp_activities'] ?? [];
            $cmidoptions = [0 => get_string('item_power_extension_cmid_any', 'block_playerhud')] + $lpactivities;
            $mform->addElement(
                'select',
                'extension_cmid',
                get_string('item_power_extension_cmid', 'block_playerhud'),
                $cmidoptions
            );
            $mform->setDefault('extension_cmid', 0);
            $mform->hideIf('extension_cmid', 'action_type', 'neq', 'deadline_extension');
        }

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
