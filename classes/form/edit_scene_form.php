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
 * Form for creating and editing story scenes (nodes) with their choices.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_scene_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $DB;

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
        $hasscenes     = $DB->record_exists('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $defaultisstart = $hasscenes ? 0 : 1;

        $mform->addElement('selectyesno', 'is_start', get_string('scene_is_start', 'block_playerhud'));
        $mform->setDefault('is_start', $defaultisstart);

        // Choices repeater.
        $mform->addElement('header', 'choices_hdr', get_string('choices_hdr', 'block_playerhud'));

        // Build option lists for selects.
        $othernodes = $DB->get_records(
            'block_playerhud_story_nodes',
            ['chapterid' => $chapterid],
            'id ASC',
            'id, content'
        );
        $nodeoptions = [
            0  => get_string('choice_end_chapter', 'block_playerhud'),
            -1 => get_string('choice_new_scene', 'block_playerhud'),
            -2 => get_string('choice_same_as_prev', 'block_playerhud'),
        ];
        foreach ($othernodes as $n) {
            $snippet = substr(strip_tags($n->content), 0, 40) . '...';
            $nodeoptions[$n->id] = 'Scene #' . $n->id . ' (' . $snippet . ')';
        }

        $itemrecords   = $DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $instanceid],
            'name ASC',
            'id, name'
        );
        $itemoptions   = [0 => '--- ' . get_string('none') . ' ---'];
        foreach ($itemrecords as $it) {
            $itemoptions[$it->id] = format_string($it->name);
        }

        $classrecords  = $DB->get_records(
            'block_playerhud_classes',
            ['blockinstanceid' => $instanceid],
            'name ASC',
            'id, name'
        );
        $classoptions  = [0 => '--- ' . get_string('none') . ' ---'];
        foreach ($classrecords as $cl) {
            $classoptions[$cl->id] = format_string($cl->name);
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

        for ($i = 0; $i < $repeats; $i++) {
            $valtext = $currentdata["choice_text_$i"] ?? '';
            $valnext = $currentdata["choice_next_$i"] ?? 0;
            $valreqclass = $currentdata["choice_req_class_$i"] ?? 0;
            $valreqkarma = $currentdata["choice_req_karma_$i"] ?? 0;
            $valkarma = $currentdata["choice_karma_$i"] ?? 0;
            $valsetclass = $currentdata["choice_set_class_$i"] ?? 0;
            $valcost = $currentdata["choice_cost_$i"] ?? 0;
            $valqty = $currentdata["choice_cost_qty_$i"] ?? 1;

            $choicetitle    = get_string('choice_text', 'block_playerhud') . ' #' . ($i + 1);
            $strtextlabel   = get_string('choice_text', 'block_playerhud');
            $strtargetlabel = get_string('choice_next', 'block_playerhud');
            $strreqhdr      = get_string('choice_req_hdr', 'block_playerhud');
            $strconshdr     = get_string('choice_cons_hdr', 'block_playerhud');
            $strreqclass    = get_string('choice_req_class', 'block_playerhud');
            $strreqkarma    = get_string('choice_req_karma', 'block_playerhud');
            $strkarmalabel  = get_string('choice_karma', 'block_playerhud');
            $strclasslabel  = get_string('choice_class_label', 'block_playerhud');
            $strpayitem     = get_string('choice_cost', 'block_playerhud');
            $strqty         = get_string('choice_cost_qty', 'block_playerhud');

            $targetselect   = \html_writer::select(
                $nodeoptions,
                'choice_next_' . $i,
                $valnext,
                null,
                ['class' => 'form-select']
            );
            $reqclassselect = \html_writer::select(
                $classoptions,
                'choice_req_class_' . $i,
                $valreqclass,
                null,
                ['class' => 'form-select form-select-sm']
            );
            $setclassselect = \html_writer::select(
                $classoptions,
                'choice_set_class_' . $i,
                $valsetclass,
                null,
                ['class' => 'form-select form-select-sm']
            );
            $payitemselect  = \html_writer::select(
                $itemoptions,
                'choice_cost_' . $i,
                $valcost,
                null,
                ['class' => 'form-select form-select-sm']
            );

            $html = '
            <div class="card ph-choice-card p-3 mb-3 border-start border-5 border-primary">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-primary m-0">' . $choicetitle . '</h6>
                    <span class="badge bg-light text-dark border">Logic</span>
                </div>
                <div class="row g-2">
                    <div class="col-md-7 mb-2">
                        <label class="small fw-bold text-muted">' . $strtextlabel . '</label>
                        <input type="text" name="choice_text_' . $i . '" value="' . s($valtext) . '"
                               class="form-control" placeholder="...">
                    </div>
                    <div class="col-md-5 mb-2">
                        <label class="small fw-bold text-muted">' . $strtargetlabel . '</label>
                        ' . $targetselect . '
                    </div>
                </div>
                <div class="row border-top pt-2 mt-1">
                    <div class="col-md-5 border-end">
                        <small class="ph-choice-section-label text-uppercase text-danger fw-bold">'
                            . $strreqhdr . '</small>
                        <div class="mb-1 mt-1">
                            <label class="small text-muted mb-0">' . $strreqclass . '</label>
                            ' . $reqclassselect . '
                        </div>
                        <div class="mb-0">
                            <label class="small text-muted mb-0">' . $strreqkarma . '</label>
                            <input type="number" name="choice_req_karma_' . $i . '"
                                   value="' . (int) $valreqkarma . '"
                                   class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="col-md-7 ps-3">
                        <small class="ph-choice-section-label text-uppercase text-success fw-bold">'
                            . $strconshdr . '</small>
                        <div class="row g-2 mt-1">
                            <div class="col-4">
                                <label class="small text-muted mb-0">' . $strkarmalabel . '</label>
                                <input type="number" name="choice_karma_' . $i . '"
                                       value="' . (int) $valkarma . '"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-8">
                                <label class="small text-muted mb-0">' . $strclasslabel . '</label>
                                ' . $setclassselect . '
                            </div>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-8">
                                <label class="small text-muted mb-0">' . $strpayitem . '</label>
                                ' . $payitemselect . '
                            </div>
                            <div class="col-4">
                                <label class="small text-muted mb-0">' . $strqty . '</label>
                                <input type="number" name="choice_cost_qty_' . $i . '"
                                       value="' . max(1, (int) $valqty) . '"
                                       class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

            $mform->addElement('html', $html);

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
}
