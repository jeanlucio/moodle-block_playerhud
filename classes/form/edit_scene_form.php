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
 * Form for creating and editing story scenes (nodes) with their choices.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_scene_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $DB, $OUTPUT;

        $mform       = $this->_form;
        $chapterid   = $this->_customdata['chapterid'];
        $instanceid  = $this->_customdata['instanceid'];
        $currentdata = $this->_customdata['current_data'] ?? [];

        // Scene content.
        $mform->addElement('header', 'gen', get_string('scene_content_hdr', 'block_playerhud'));
        $mform->addElement('editor', 'content', get_string('scene_content', 'block_playerhud'));
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');

        // Start scene flag.
        $hasscenes      = $DB->record_exists('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $defaultisstart = $hasscenes ? 0 : 1;

        $mform->addElement('selectyesno', 'is_start', get_string('scene_is_start', 'block_playerhud'));
        $mform->setDefault('is_start', $defaultisstart);

        // Choices repeater.
        $mform->addElement('header', 'choices_hdr', get_string('choices_hdr', 'block_playerhud'));

        // Build raw option data for scene target select.
        $othernodes  = $DB->get_records(
            'block_playerhud_story_nodes',
            ['chapterid' => $chapterid],
            'id ASC',
            'id, content'
        );
        $targetoptionsraw = [
            0  => get_string('choice_end_chapter', 'block_playerhud'),
            -1 => get_string('choice_new_scene', 'block_playerhud'),
            -2 => get_string('choice_same_as_prev', 'block_playerhud'),
        ];
        foreach ($othernodes as $n) {
            $snippet = substr(strip_tags($n->content), 0, 40) . '...';
            $targetoptionsraw[$n->id] = get_string('scene_number', 'block_playerhud', $n->id) .
                ' (' . $snippet . ')';
        }

        $itemrecords = $DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid],
            'name ASC',
            'id, name'
        );
        $itemoptionsraw = [0 => '--- ' . get_string('none') . ' ---'];
        foreach ($itemrecords as $it) {
            $itemoptionsraw[$it->id] = format_string($it->name);
        }

        $classrecords = $DB->get_records(
            'block_playerhud_classes',
            ['blockinstanceid' => $instanceid],
            'name ASC',
            'id, name'
        );
        $classoptionsraw = [0 => '--- ' . get_string('none') . ' ---'];
        foreach ($classrecords as $cl) {
            $classoptionsraw[$cl->id] = format_string($cl->name);
        }

        // Determine repeater count.
        $dbchoicescount = $this->_customdata['db_choices_count'] ?? 0;
        $repeats = max(3, $dbchoicescount);
        if (optional_param('add_choice_btn', false, PARAM_BOOL)) {
            $repeats++;
        }

        $mform->addElement('hidden', 'repeats');
        $mform->setType('repeats', PARAM_INT);
        $mform->setConstant('repeats', $repeats);

        // Pre-build string map (fetched once, reused per iteration).
        $strmap = [
            'choice_title_prefix' => get_string('choice_text', 'block_playerhud'),
            'str_logic_badge'     => get_string('choice_logic_badge', 'block_playerhud'),
            'str_text'            => get_string('choice_text', 'block_playerhud'),
            'str_target'          => get_string('choice_next', 'block_playerhud'),
            'str_req_hdr'         => get_string('choice_req_hdr', 'block_playerhud'),
            'str_cons_hdr'        => get_string('choice_cons_hdr', 'block_playerhud'),
            'str_req_class'       => get_string('choice_req_class', 'block_playerhud'),
            'str_req_karma'       => get_string('choice_req_karma', 'block_playerhud'),
            'str_karma'           => get_string('choice_karma', 'block_playerhud'),
            'str_set_class'       => get_string('choice_class_label', 'block_playerhud'),
            'str_cost_item'       => get_string('choice_cost', 'block_playerhud'),
            'str_cost_qty'        => get_string('choice_cost_qty', 'block_playerhud'),
        ];

        for ($i = 0; $i < $repeats; $i++) {
            $valtext     = $currentdata["choice_text_$i"] ?? '';
            $valnext     = (int) ($currentdata["choice_next_$i"] ?? 0);
            $valreqclass = (int) ($currentdata["choice_req_class_$i"] ?? 0);
            $valreqkarma = (int) ($currentdata["choice_req_karma_$i"] ?? 0);
            $valkarma    = (int) ($currentdata["choice_karma_$i"] ?? 0);
            $valsetclass = (int) ($currentdata["choice_set_class_$i"] ?? 0);
            $valcost     = (int) ($currentdata["choice_cost_$i"] ?? 0);
            $valqty      = max(1, (int) ($currentdata["choice_cost_qty_$i"] ?? 1));

            $templatedata = array_merge($strmap, [
                'idx'          => $i,
                'choice_title' => $strmap['choice_title_prefix'] . ' #' . ($i + 1),
                'val_text'     => s($valtext),
                'val_req_karma' => $valreqkarma,
                'val_karma'    => $valkarma,
                'val_cost_qty' => $valqty,
                'target_options'    => self::build_options($targetoptionsraw, $valnext),
                'req_class_options' => self::build_options($classoptionsraw, $valreqclass),
                'set_class_options' => self::build_options($classoptionsraw, $valsetclass),
                'cost_item_options' => self::build_options($itemoptionsraw, $valcost),
            ]);
            unset($templatedata['choice_title_prefix']);

            $mform->addElement('html', $OUTPUT->render_from_template(
                'block_playerhud/form_choice_card',
                $templatedata
            ));

            $mform->setType("choice_text_$i", PARAM_TEXT);
            $mform->setType("choice_next_$i", PARAM_INT);
            $mform->setType("choice_req_class_$i", PARAM_INT);
            $mform->setType("choice_req_karma_$i", PARAM_INT);
            $mform->setType("choice_karma_$i", PARAM_INT);
            $mform->setType("choice_set_class_$i", PARAM_INT);
            $mform->setType("choice_cost_$i", PARAM_INT);
            $mform->setType("choice_cost_qty_$i", PARAM_INT);
        }

        $mform->addElement('submit', 'add_choice_btn', get_string('save_and_add', 'block_playerhud'));
        $mform->registerNoSubmitButton('add_choice_btn');

        // Hidden fields.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'instanceid');
        $mform->setType('instanceid', PARAM_INT);

        $mform->addElement('hidden', 'chapterid');
        $mform->setType('chapterid', PARAM_INT);

        $mform->addElement('hidden', 'nodeid');
        $mform->setType('nodeid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Convert a key => label map into a Mustache-friendly options array.
     *
     * @param array $map Associative array of value => label.
     * @param int|string $selected Currently selected value.
     * @return array Array of ['value' => ..., 'label' => ..., 'selected' => bool].
     */
    private static function build_options(array $map, $selected): array {
        $options = [];
        foreach ($map as $value => $label) {
            $options[] = [
                'value'    => (string) $value,
                'label'    => $label,
                'selected' => ((string) $value === (string) $selected),
            ];
        }
        return $options;
    }
}
