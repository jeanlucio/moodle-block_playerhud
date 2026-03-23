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
 * Form for editing or creating trades (shop offers).
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_trade_form extends \moodleform {
    /**
     * Definition of the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        $instanceid = $this->_customdata['instanceid'];
        $courseid = $this->_customdata['courseid'];

        // Dynamic counters.
        $repeatsreq = optional_param('repeats_req', 3, PARAM_INT);
        $repeatsgive = optional_param('repeats_give', 3, PARAM_INT);

        if (optional_param('add_req_btn', false, PARAM_BOOL)) {
            $repeatsreq++;
        }
        if (optional_param('add_give_btn', false, PARAM_BOOL)) {
            $repeatsgive++;
        }

        if (!empty($this->_customdata['db_req_count'])) {
            $repeatsreq = max($repeatsreq, $this->_customdata['db_req_count']);
        }
        if (!empty($this->_customdata['db_give_count'])) {
            $repeatsgive = max($repeatsgive, $this->_customdata['db_give_count']);
        }

        $mform->addElement('hidden', 'repeats_req');
        $mform->setType('repeats_req', PARAM_INT);
        $mform->setConstant('repeats_req', $repeatsreq);

        $mform->addElement('hidden', 'repeats_give');
        $mform->setType('repeats_give', PARAM_INT);
        $mform->setConstant('repeats_give', $repeatsgive);

        // Fetch Items for Dropdown.
        $allitems = $DB->get_records_menu(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid, 'enabled' => 1],
            'name ASC',
            'id, name'
        );
        $itemoptions = [0 => '--- ' . get_string('select') . ' ---'] + ($allitems ? $allitems : []);

        // Fetch Groups.
        $groups = groups_get_all_groups($courseid);
        $groupings = groups_get_all_groupings($courseid);

        $groupoptions = [0 => get_string('allparticipants')];
        if ($groups) {
            foreach ($groups as $g) {
                $groupoptions[$g->id] = get_string('group') . ': ' . format_string($g->name);
            }
        }
        if ($groupings) {
            foreach ($groupings as $gp) {
                $groupoptions[-$gp->id] = get_string('grouping', 'group') . ': ' . format_string($gp->name);
            }
        }

        // Section: General Settings.
        $mform->addElement('header', 'general', get_string('trade_config_hdr', 'block_playerhud'));

        $mform->addElement('text', 'name', get_string('trade_name', 'block_playerhud'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('trade_default_name', 'block_playerhud'));

        // Section: Student pays.
        $mform->addElement('header', 'req_header', get_string('trade_req_hdr', 'block_playerhud'));
        $mform->addElement(
            'static',
            'req_desc',
            '',
            \html_writer::tag('small', get_string('trade_req_desc', 'block_playerhud'), ['class' => 'text-muted'])
        );

        for ($i = 0; $i < $repeatsreq; $i++) {
            $group = [];

            // Keep only original SCSS class.
            $previewhtml = '<div class="ph-item-preview me-2" id="preview_req_' . $i . '">' .
                           '<span class="text-muted ph-text-xs" aria-hidden="true">?</span></div>';
            $group[] = $mform->createElement('static', "prev_req_$i", '', $previewhtml);

            // Remove form-select and w-auto classes.
            $group[] = $mform->createElement(
                'select',
                "req_itemid_$i",
                '',
                $itemoptions,
                ['class' => 'ph-item-selector', 'data-target' => "preview_req_$i"]
            );

            // Apply fixed width class to prevent wrapping.
            $group[] = $mform->createElement(
                'text',
                "req_qty_$i",
                '',
                ['type' => 'number', 'min' => 1, 'class' => 'ph-input-qty']
            );
            $mform->setType("req_qty_$i", PARAM_INT);

            $label = get_string('item_n', 'block_playerhud', $i + 1);

            // Adjust separators.
            $mform->addGroup($group, "req_group_$i", $label, [' ', '<span class="mx-2 text-muted fw-bold">x</span>'], false);
            $mform->setDefault("req_qty_$i", 1);
        }

        $mform->addElement(
            'submit',
            'add_req_btn',
            get_string('add_cost_item', 'block_playerhud'),
            ['class' => 'btn-secondary btn-sm w-auto']
        );
        $mform->registerNoSubmitButton('add_req_btn');

        // Section: Student receives.
        $mform->addElement('header', 'give_header', get_string('trade_give_hdr', 'block_playerhud'));
        $mform->addElement(
            'static',
            'give_desc',
            '',
            \html_writer::tag('small', get_string('trade_give_desc', 'block_playerhud'), ['class' => 'text-muted'])
        );

        for ($i = 0; $i < $repeatsgive; $i++) {
            $group = [];

            $previewhtml = '<div class="ph-item-preview me-2" id="preview_give_' . $i . '">' .
                           '<span class="text-muted ph-text-xs" aria-hidden="true">?</span></div>';
            $group[] = $mform->createElement('static', "prev_give_$i", '', $previewhtml);

            $group[] = $mform->createElement(
                'select',
                "give_itemid_$i",
                '',
                $itemoptions,
                ['class' => 'ph-item-selector', 'data-target' => "preview_give_$i"]
            );

            // Apply fixed width class to prevent wrapping.
            $group[] = $mform->createElement(
                'text',
                "give_qty_$i",
                '',
                ['type' => 'number', 'min' => 1, 'class' => 'ph-input-qty']
            );
            $mform->setType("give_qty_$i", PARAM_INT);

            $label = get_string('item_n', 'block_playerhud', $i + 1);
            $mform->addGroup($group, "give_group_$i", $label, [' ', '<span class="mx-2 text-muted fw-bold">x</span>'], false);
            $mform->setDefault("give_qty_$i", 1);
        }

        $mform->addElement(
            'submit',
            'add_give_btn',
            get_string('add_reward_item', 'block_playerhud'),
            ['class' => 'btn-secondary btn-sm w-auto']
        );
        $mform->registerNoSubmitButton('add_give_btn');

        // Rules.
        $mform->addElement('header', 'rules_header', get_string('visualrules', 'block_playerhud'));
        $mform->addElement('select', 'groupid', get_string('restrict_group', 'block_playerhud'), $groupoptions);

        $mform->addElement('selectyesno', 'centralized', get_string('show_in_shop', 'block_playerhud'));
        $mform->setDefault('centralized', 1);

        $mform->addElement('selectyesno', 'onetime', get_string('one_time_trade', 'block_playerhud'));
        $mform->setDefault('onetime', 0);

        // Hidden Fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);
        $mform->addElement('hidden', 'tradeid');
        $mform->setType('tradeid', PARAM_INT);

        $this->add_action_buttons(true, get_string('save_trade', 'block_playerhud'));
    }
}
