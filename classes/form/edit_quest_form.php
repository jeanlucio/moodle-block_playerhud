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
 * Quest editing form.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing or creating quests.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_quest_form extends \moodleform {
    /**
     * Definition of the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        $instanceid = $this->_customdata['instanceid'];
        $courseid   = $this->_customdata['courseid'];

        // Fetch items for reward/requirement selects.
        $allitems = $DB->get_records_menu(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid, 'enabled' => 1],
            'name ASC',
            'id, name'
        );
        $nooption   = [0 => '— ' . get_string('none', 'block_playerhud') . ' —'];
        $itemoptions = $nooption + ($allitems ?: []);

        // Quest type options.
        $typeoptions = [
            1 => get_string('quest_type_manual', 'block_playerhud'),
            2 => get_string('quest_type_activity', 'block_playerhud'),
        ];

        // Course modules with completion enabled (for activity-type quests).
        $modoptions = [0 => '— ' . get_string('select') . ' —'];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->visible && $cm->completion > 0) {
                $modoptions[$cm->id] = format_string($cm->name) . ' (' . $cm->modname . ')';
            }
        }

        // General header.
        $mform->addElement('header', 'general', get_string('general', 'core'));

        // 1. Name.
        $mform->addElement('text', 'name', get_string('quest_name', 'block_playerhud'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // 2. Description.
        $mform->addElement('editor', 'description', get_string('description', 'block_playerhud'));
        $mform->setType('description', PARAM_RAW);

        // 3. Type.
        $mform->addElement('select', 'type', get_string('quest_type', 'block_playerhud'), $typeoptions);
        $mform->setType('type', PARAM_INT);
        $mform->setDefault('type', 1);

        // 4. Requirement text (type = manual).
        $mform->addElement(
            'text',
            'requirement',
            get_string('quest_requirement', 'block_playerhud')
        );
        $mform->setType('requirement', PARAM_TEXT);
        $mform->addHelpButton('requirement', 'quest_requirement', 'block_playerhud');
        $mform->hideIf('requirement', 'type', 'neq', '1');

        // 5. Activity CMID selector (type = activity).
        $mform->addElement(
            'select',
            'requirement_cmid',
            get_string('quest_activity', 'block_playerhud'),
            $modoptions
        );
        $mform->setType('requirement_cmid', PARAM_INT);
        $mform->setDefault('requirement_cmid', 0);
        $mform->hideIf('requirement_cmid', 'type', 'neq', '2');

        // Rewards header.
        $mform->addElement('header', 'rewards_hdr', get_string('quest_rewards_hdr', 'block_playerhud'));
        $mform->setExpanded('rewards_hdr', true);

        // 6. XP Reward.
        $mform->addElement('text', 'reward_xp', get_string('quest_reward_xp', 'block_playerhud'));
        $mform->setType('reward_xp', PARAM_INT);
        $mform->setDefault('reward_xp', 0);

        // 7. Item Reward.
        $mform->addElement(
            'select',
            'reward_itemid',
            get_string('quest_reward_item', 'block_playerhud'),
            $itemoptions
        );
        $mform->setType('reward_itemid', PARAM_INT);
        $mform->setDefault('reward_itemid', 0);

        // Rules header.
        $mform->addElement('header', 'rules_hdr', get_string('visualrules', 'block_playerhud'));

        // 8. Enabled.
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'block_playerhud'));
        $mform->setDefault('enabled', 1);

        // 9. Class Restriction — reserved for when Classes system is implemented (Etapa 1).
        $mform->addElement('hidden', 'required_class_id');
        $mform->setType('required_class_id', PARAM_TEXT);
        $mform->setDefault('required_class_id', '0');

        // Hidden fields.
        $mform->addElement('hidden', 'questid');
        $mform->setType('questid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

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

        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        if ((int)$data['type'] === 1 && empty(trim($data['requirement'] ?? ''))) {
            $errors['requirement'] = get_string('required');
        }

        if ((int)$data['type'] === 2 && empty($data['requirement_cmid'])) {
            $errors['requirement_cmid'] = get_string('required');
        }

        if (!is_numeric($data['reward_xp']) || (int)$data['reward_xp'] < 0) {
            $errors['reward_xp'] = get_string('validate_number', 'core');
        }

        return $errors;
    }
}
