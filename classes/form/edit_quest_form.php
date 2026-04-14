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
        $mform      = $this->_form;
        $instanceid = $this->_customdata['instanceid'];
        $courseid   = $this->_customdata['courseid'];

        // Items for reward/accumulator selects.
        $allitems    = $DB->get_records_menu(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid, 'enabled' => 1],
            'name ASC',
            'id, name'
        );
        $nooption    = [0 => '— ' . get_string('none', 'block_playerhud') . ' —'];
        $itemoptions = $nooption + ($allitems ?: []);

        // Trades for specific trade quest.
        $alltrades   = $DB->get_records_menu(
            'block_playerhud_trades',
            ['blockinstanceid' => $instanceid],
            'name ASC',
            'id, name'
        );
        $tradeoptions = $nooption + ($alltrades ?: []);

        // Chapters for story-chapter quest.
        $allchapters = $DB->get_records_menu(
            'block_playerhud_chapters',
            ['blockinstanceid' => $instanceid],
            'sortorder ASC',
            'id, title'
        );
        $chapteroptions = [0 => '— ' . get_string('select') . ' —'] + ($allchapters ?: []);

        // Quest type options.
        $typeoptions = [
            \block_playerhud\quest::TYPE_LEVEL          => get_string('quest_type_level', 'block_playerhud'),
            \block_playerhud\quest::TYPE_XP_TOTAL       => get_string('quest_type_xp_total', 'block_playerhud'),
            \block_playerhud\quest::TYPE_UNIQUE_ITEMS   => get_string('quest_type_unique_items', 'block_playerhud'),
            \block_playerhud\quest::TYPE_TOTAL_ITEMS    => get_string('quest_type_total_items', 'block_playerhud'),
            \block_playerhud\quest::TYPE_TRADES         => get_string('quest_type_trades', 'block_playerhud'),
            \block_playerhud\quest::TYPE_SPECIFIC_ITEM  => get_string('quest_type_specific_item', 'block_playerhud'),
            \block_playerhud\quest::TYPE_SPECIFIC_TRADE => get_string('quest_type_specific_trade', 'block_playerhud'),
            \block_playerhud\quest::TYPE_ACTIVITY       => get_string('quest_type_activity', 'block_playerhud'),
            \block_playerhud\quest::TYPE_CHAPTER        => get_string('quest_type_chapter', 'block_playerhud'),
        ];

        // Course modules with completion enabled.
        $activityoptions = [0 => '— ' . get_string('select') . ' —'];
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->visible && $cm->completion > 0) {
                $activityoptions[$cm->id] = format_string($cm->name) . ' (' . $cm->modname . ')';
            }
        }

        // General header.
        $mform->addElement('header', 'general', get_string('general', 'core'));

        $mform->addElement('text', 'name', get_string('quest_name', 'block_playerhud'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('editor', 'description', get_string('description', 'block_playerhud'));
        $mform->setType('description', PARAM_RAW);

        // Requirements header.
        $mform->addElement('header', 'req_hdr', get_string('quest_requirements_hdr', 'block_playerhud'));
        $mform->setExpanded('req_hdr', true);

        $mform->addElement('select', 'type', get_string('quest_type', 'block_playerhud'), $typeoptions);
        $mform->setType('type', PARAM_INT);
        $mform->setDefault('type', \block_playerhud\quest::TYPE_LEVEL);

        $mform->addElement('text', 'target_value', get_string('quest_target_value', 'block_playerhud'));
        $mform->setType('target_value', PARAM_INT);
        $mform->setDefault('target_value', 1);
        $mform->hideIf('target_value', 'type', 'eq', (string)\block_playerhud\quest::TYPE_ACTIVITY);
        $mform->hideIf('target_value', 'type', 'eq', (string)\block_playerhud\quest::TYPE_CHAPTER);

        $mform->addElement('select', 'req_itemid', get_string('quest_req_item', 'block_playerhud'), $itemoptions);
        $mform->setType('req_itemid', PARAM_INT);
        $mform->setDefault('req_itemid', 0);
        $mform->hideIf('req_itemid', 'type', 'neq', (string)\block_playerhud\quest::TYPE_SPECIFIC_ITEM);

        $mform->addElement('select', 'req_tradeid', get_string('quest_req_trade', 'block_playerhud'), $tradeoptions);
        $mform->setType('req_tradeid', PARAM_INT);
        $mform->setDefault('req_tradeid', 0);
        $mform->hideIf('req_tradeid', 'type', 'neq', (string)\block_playerhud\quest::TYPE_SPECIFIC_TRADE);

        $mform->addElement('select', 'activity_cmid', get_string('quest_activity', 'block_playerhud'), $activityoptions);
        $mform->setType('activity_cmid', PARAM_INT);
        $mform->setDefault('activity_cmid', 0);
        $mform->hideIf('activity_cmid', 'type', 'neq', (string)\block_playerhud\quest::TYPE_ACTIVITY);

        $mform->addElement(
            'select',
            'chapter_id',
            get_string('chapter_quest_label', 'block_playerhud'),
            $chapteroptions
        );
        $mform->setType('chapter_id', PARAM_INT);
        $mform->setDefault('chapter_id', 0);
        $mform->hideIf('chapter_id', 'type', 'neq', (string)\block_playerhud\quest::TYPE_CHAPTER);

        // Rewards header.
        $mform->addElement('header', 'rewards_hdr', get_string('quest_rewards_hdr', 'block_playerhud'));
        $mform->setExpanded('rewards_hdr', true);

        $mform->addElement('text', 'reward_xp', get_string('quest_reward_xp', 'block_playerhud'));
        $mform->setType('reward_xp', PARAM_INT);
        $mform->setDefault('reward_xp', 0);

        $mform->addElement('select', 'reward_itemid', get_string('quest_reward_item', 'block_playerhud'), $itemoptions);
        $mform->setType('reward_itemid', PARAM_INT);
        $mform->setDefault('reward_itemid', 0);

        // Visual identity header.
        $mform->addElement('header', 'visual_hdr', get_string('visualrules', 'block_playerhud'));

        $mform->addElement('text', 'image_todo', get_string('quest_icon_todo', 'block_playerhud'));
        $mform->setType('image_todo', PARAM_TEXT);
        $mform->setDefault('image_todo', '🎯');

        $mform->addElement('text', 'image_done', get_string('quest_icon_done', 'block_playerhud'));
        $mform->setType('image_done', PARAM_TEXT);
        $mform->setDefault('image_done', '🏅');

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'block_playerhud'));
        $mform->setDefault('enabled', 1);

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
        $type   = (int)$data['type'];

        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        $nontarget = [
            \block_playerhud\quest::TYPE_ACTIVITY,
            \block_playerhud\quest::TYPE_CHAPTER,
        ];
        if (!in_array($type, $nontarget)) {
            if (!isset($data['target_value']) || (int)$data['target_value'] < 1) {
                $errors['target_value'] = get_string('quest_validate_target', 'block_playerhud');
            }
        }

        if ($type === \block_playerhud\quest::TYPE_SPECIFIC_ITEM && empty($data['req_itemid'])) {
            $errors['req_itemid'] = get_string('required');
        }

        if ($type === \block_playerhud\quest::TYPE_SPECIFIC_TRADE && empty($data['req_tradeid'])) {
            $errors['req_tradeid'] = get_string('required');
        }

        if ($type === \block_playerhud\quest::TYPE_ACTIVITY && empty($data['activity_cmid'])) {
            $errors['activity_cmid'] = get_string('required');
        }

        if ($type === \block_playerhud\quest::TYPE_CHAPTER && empty($data['chapter_id'])) {
            $errors['chapter_id'] = get_string('required');
        }

        if (!is_numeric($data['reward_xp']) || (int)$data['reward_xp'] < 0) {
            $errors['reward_xp'] = get_string('validate_number', 'core');
        }

        return $errors;
    }
}
