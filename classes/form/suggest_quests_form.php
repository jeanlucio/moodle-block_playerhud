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
 * Form for suggesting quests automatically.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_quests_form extends \moodleform {
    /**
     * Definition of the form.
     */
    public function definition() {
        $mform = $this->_form;
        $suggestions = $this->_customdata['suggestions'];

        $mform->addElement('header', 'general', get_string('quest_suggestions', 'block_playerhud'));

        if (empty($suggestions)) {
            $mform->addElement('static', 'nosug', '', get_string('quest_no_suggestions', 'block_playerhud'));
        } else {
            $mform->addElement(
                'static',
                'info',
                '',
                '<div class="alert alert-info border shadow-sm"><i class="fa fa-lightbulb-o me-2" aria-hidden="true"></i>' .
                get_string('quest_sug_info', 'block_playerhud') . '</div>'
            );

            foreach ($suggestions as $sug) {
                $label = '<span aria-hidden="true" class="fs-5 me-2">' . $sug['image_done'] . '</span> ' .
                         '<span class="fw-bold text-dark">' . $sug['name'] . '</span> ' .
                         '<span class="badge bg-success text-white ms-2 shadow-sm">+' . $sug['reward_xp'] . ' XP</span>';

                $mform->addElement('advcheckbox', 'sug_' . $sug['uid'], '', $label, null, [0, 1]);
                $mform->setDefault('sug_' . $sug['uid'], 1);
            }
        }

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHAEXT);

        if (!empty($suggestions)) {
            $this->add_action_buttons(true, get_string('quest_sug_save', 'block_playerhud'));
        } else {
            $mform->addElement('cancel');
        }
    }
}
