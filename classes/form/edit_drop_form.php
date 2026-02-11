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
 * Form for editing drop locations.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_drop_form extends \moodleform {
    /**
     * Definition of the form.
     */
    public function definition() {
        $mform = $this->_form;

        // Header and Name.
        $mform->addElement(
            'header',
            'general',
            get_string('drop_config_header', 'block_playerhud', $this->_customdata['itemname'])
        );

        $mform->addElement('text', 'name', get_string('drop_name_label', 'block_playerhud'), [
            'placeholder' => get_string('drop_name_default', 'block_playerhud'),
        ]);

        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Collection Rules.
        $mform->addElement('header', 'rules', get_string('drop_rules_header', 'block_playerhud'));

        // Unlimited?
        $mform->addElement(
            'advcheckbox',
            'unlimited',
            get_string('drop_supplies_label', 'block_playerhud'),
            get_string('drop_unlimited_label', 'block_playerhud'),
            [],
            [0, 1]
        );

        // Info Warning.
        $warningmsg = \html_writer::tag('div', get_string('drop_unlimited_xp_warning', 'block_playerhud'), [
            'class' => 'alert alert-info mb-0 mt-2', // Changed to 'info' as it is expected behavior.
        ]);

        $mform->addElement('static', 'warning_infinite', '', $warningmsg);
        $mform->hideIf('warning_infinite', 'unlimited', 'notchecked');

        // Max Quantity (If not unlimited).
        $mform->addElement(
            'text',
            'maxusage',
            get_string('drop_max_qty', 'block_playerhud'),
            ['type' => 'number', 'min' => '1']
        );
        $mform->setType('maxusage', PARAM_INT);
        $mform->setDefault('maxusage', 1);
        $mform->hideIf('maxusage', 'unlimited', 'checked');

        // Respawn Time (Cooldown).
        $mform->addElement(
            'duration',
            'respawntime',
            get_string('drop_interval', 'block_playerhud'),
            ['optional' => false, 'defaultunit' => 60]
        );
        $mform->setDefault('respawntime', 0);
        $mform->addHelpButton('respawntime', 'respawntime', 'block_playerhud');

        // Hidden Fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('drop_save_btn', 'block_playerhud'));
    }
}
