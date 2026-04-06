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
 * Quests tab for the management interface.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use moodle_url;
use block_playerhud\form\edit_quest_form;
use block_playerhud\quest;

/**
 * Quests management tab renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_quests implements renderable {
    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /** @var edit_quest_form|null Active form instance (edit/add mode). */
    protected $mform = null;

    /** @var string Sort column. */
    protected $sort;

    /** @var string Sort direction. */
    protected $dir;

    /**
     * Constructor.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $sort Sort column.
     * @param string $dir Sort direction.
     */
    public function __construct($instanceid, $courseid, $sort = 'timecreated', $dir = 'DESC') {
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
        $this->sort       = $sort ?: 'timecreated';
        $this->dir        = $dir ?: 'DESC';
    }

    /**
     * Handle form submission (add/edit quest).
     */
    public function process() {
        global $DB;

        $action  = optional_param('action', '', PARAM_ALPHA);
        $questid = optional_param('questid', 0, PARAM_INT);

        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'quests',
        ]);

        if ($action !== 'add' && !($action === 'edit' && $questid > 0)) {
            return;
        }

        $actionurl = new moodle_url($baseurl, [
            'action'  => $questid ? 'edit' : 'add',
            'questid' => $questid,
        ]);

        $this->mform = new edit_quest_form($actionurl->out(false), [
            'instanceid' => $this->instanceid,
            'courseid'   => $this->courseid,
        ]);

        if ($this->mform->is_cancelled()) {
            redirect($baseurl);
        }

        if ($data = $this->mform->get_data()) {
            $type = (int)$data->type;

            $record                    = new \stdClass();
            $record->blockinstanceid   = $this->instanceid;
            $record->name              = $data->name;
            $record->description       = $data->description['text'];
            $record->type              = $type;
            $record->enabled           = (int)$data->enabled;
            $record->reward_xp         = max(0, (int)$data->reward_xp);
            $record->reward_itemid     = (int)$data->reward_itemid;
            $record->required_class_id = '0';
            $record->image_todo        = trim($data->image_todo ?? '');
            $record->image_done        = trim($data->image_done ?? '');
            $record->timemodified      = time();

            if ($type === quest::TYPE_ACTIVITY) {
                $record->requirement = (string)(int)$data->activity_cmid;
                $record->req_itemid  = 0;
            } else {
                $record->requirement = (string)(int)$data->target_value;
                $record->req_itemid  = ($type === quest::TYPE_SPECIFIC_ITEM)
                    ? (int)$data->req_itemid
                    : 0;
            }

            if ($questid > 0) {
                $record->id = $questid;
                $DB->update_record('block_playerhud_quests', $record);
            } else {
                $record->timecreated = time();
                $DB->insert_record('block_playerhud_quests', $record);
            }

            redirect(
                $baseurl,
                get_string('changessaved', 'block_playerhud'),
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        // Pre-fill form when editing.
        if ($questid > 0 && !$this->mform->is_submitted()) {
            $quest = $DB->get_record(
                'block_playerhud_quests',
                ['id' => $questid, 'blockinstanceid' => $this->instanceid]
            );
            if ($quest) {
                $formdata                 = (array)$quest;
                $formdata['questid']      = $quest->id;
                $formdata['instanceid']   = $this->instanceid;
                $formdata['courseid']     = $this->courseid;
                $formdata['description']  = ['text' => $quest->description, 'format' => FORMAT_HTML];

                if ($quest->type == quest::TYPE_ACTIVITY) {
                    $formdata['activity_cmid'] = (int)$quest->requirement;
                    $formdata['target_value']  = 1;
                } else {
                    $formdata['target_value']  = (int)$quest->requirement;
                    $formdata['activity_cmid'] = 0;
                }

                $this->mform->set_data($formdata);
            }
        } else if (!$questid && !$this->mform->is_submitted()) {
            $this->mform->set_data([
                'questid'    => 0,
                'instanceid' => $this->instanceid,
                'courseid'   => $this->courseid,
            ]);
        }
    }

    /**
     * Render the tab content.
     *
     * @return string HTML content.
     */
    public function display() {
        if ($this->mform !== null) {
            return $this->render_form();
        }
        return $this->render_list();
    }

    /**
     * Render the quest form.
     *
     * @return string HTML.
     */
    protected function render_form() {
        global $OUTPUT;
        $questid = optional_param('questid', 0, PARAM_INT);
        $title   = $questid
            ? get_string('quest_edit', 'block_playerhud')
            : get_string('quest_new', 'block_playerhud');
        return $OUTPUT->heading($title, 3) . $this->mform->render();
    }

    /**
     * Build sort link data for a column header.
     *
     * @param string $colname Column identifier.
     * @param string $label Display label.
     * @param moodle_url $baseurl Base URL.
     * @return array Sort data for the template.
     */
    protected function get_sort_data(string $colname, string $label, moodle_url $baseurl): array {
        $icon    = 'fa-sort text-muted opacity-25';
        $nextdir = 'ASC';

        if ($this->sort === $colname) {
            if ($this->dir === 'ASC') {
                $icon    = 'fa-sort-asc text-primary';
                $nextdir = 'DESC';
            } else {
                $icon    = 'fa-sort-desc text-primary';
                $nextdir = 'ASC';
            }
        }

        return [
            'url'        => (new moodle_url($baseurl, ['sort' => $colname, 'dir' => $nextdir]))->out(false),
            'label'      => $label,
            'icon_class' => $icon,
        ];
    }

    /**
     * Render the quest list table.
     *
     * @return string HTML.
     */
    protected function render_list() {
        global $DB, $OUTPUT, $PAGE;

        $baseurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'quests',
        ]);

        $allowedsorts = ['name', 'type', 'timecreated'];
        if (!in_array($this->sort, $allowedsorts)) {
            $this->sort = 'timecreated';
        }
        $this->dir = (strtoupper($this->dir) === 'ASC') ? 'ASC' : 'DESC';

        // Load quests for this block instance.
        $quests = $DB->get_records(
            'block_playerhud_quests',
            ['blockinstanceid' => $this->instanceid],
            "{$this->sort} {$this->dir}"
        );

        // Preload reward item names to avoid N+1.
        $itemids = [];
        foreach ($quests as $q) {
            if ($q->reward_itemid > 0) {
                $itemids[$q->reward_itemid] = $q->reward_itemid;
            }
            if ($q->req_itemid > 0) {
                $itemids[$q->req_itemid] = $q->req_itemid;
            }
        }
        $itemnames = [];
        if (!empty($itemids)) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_values($itemids));
            $rows = $DB->get_records_select('block_playerhud_items', "id $insql", $inparams, '', 'id, name');
            foreach ($rows as $row) {
                $itemnames[$row->id] = format_string($row->name);
            }
        }

        // Preload claim counts per quest (avoid N+1).
        $claimcounts = [];
        if (!empty($quests)) {
            $qids = array_keys($quests);
            [$qinsql, $qinparams] = $DB->get_in_or_equal($qids);
            $sql = "SELECT questid, COUNT(id) AS cnt FROM {block_playerhud_quest_log}
                     WHERE questid $qinsql GROUP BY questid";
            $counts = $DB->get_records_sql($sql, $qinparams);
            foreach ($counts as $c) {
                $claimcounts[$c->questid] = (int)$c->cnt;
            }
        }

        $typelabels = [
            quest::TYPE_LEVEL         => get_string('quest_type_level', 'block_playerhud'),
            quest::TYPE_XP_TOTAL      => get_string('quest_type_xp_total', 'block_playerhud'),
            quest::TYPE_UNIQUE_ITEMS  => get_string('quest_type_unique_items', 'block_playerhud'),
            quest::TYPE_SPECIFIC_ITEM => get_string('quest_type_specific_item', 'block_playerhud'),
            quest::TYPE_ACTIVITY      => get_string('quest_type_activity', 'block_playerhud'),
        ];

        $typebadges = [
            quest::TYPE_LEVEL         => 'bg-primary text-white',
            quest::TYPE_XP_TOTAL      => 'bg-success text-white',
            quest::TYPE_UNIQUE_ITEMS  => 'bg-info text-dark',
            quest::TYPE_SPECIFIC_ITEM => 'bg-warning text-dark',
            quest::TYPE_ACTIVITY      => 'bg-danger text-white',
        ];

        $questsdata = [];
        foreach ($quests as $q) {
            $rewardtext = '';
            if ($q->reward_xp > 0) {
                $rewardtext .= $q->reward_xp . ' XP';
            }
            if ($q->reward_itemid > 0 && isset($itemnames[$q->reward_itemid])) {
                $rewardtext .= ($rewardtext ? ' + ' : '') . $itemnames[$q->reward_itemid];
            }

            $requirementtext = '';
            if ($q->type == quest::TYPE_SPECIFIC_ITEM && $q->req_itemid > 0) {
                $requirementtext = ($itemnames[$q->req_itemid] ?? '?') . ' x' . $q->requirement;
            } else if ($q->type !== quest::TYPE_ACTIVITY) {
                $requirementtext = $q->requirement;
            }

            $questsdata[] = [
                'id'               => $q->id,
                'image_todo'       => !empty($q->image_todo) ? $q->image_todo : '📋',
                'name'             => format_string($q->name),
                'type_label'       => $typelabels[$q->type] ?? '-',
                'type_badge_class' => $typebadges[$q->type] ?? 'bg-secondary text-white',
                'requirement_text' => $requirementtext,
                'enabled'          => (bool)$q->enabled,
                'reward_text'      => $rewardtext ?: '—',
                'claims_count'     => $claimcounts[$q->id] ?? 0,
                'url_edit'         => (new moodle_url($baseurl, [
                    'action'  => 'edit',
                    'questid' => $q->id,
                ]))->out(false),
                'url_toggle'       => (new moodle_url($baseurl, [
                    'action'  => 'toggle_quest',
                    'questid' => $q->id,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'url_delete'       => (new moodle_url($baseurl, [
                    'action'  => 'delete_quest',
                    'questid' => $q->id,
                    'sesskey' => sesskey(),
                ]))->out(false),
                'str_toggle'         => $q->enabled
                    ? get_string('click_to_hide', 'block_playerhud')
                    : get_string('click_to_show', 'block_playerhud'),
                'str_delete_confirm' => s(
                    get_string('confirm_delete', 'block_playerhud') . " '" . format_string($q->name) . "'?"
                ),
            ];
        }

        $headers = [
            'name' => $this->get_sort_data('name', get_string('quest_name', 'block_playerhud'), $baseurl),
            'type' => $this->get_sort_data('type', get_string('quest_type', 'block_playerhud'), $baseurl),
            'date' => $this->get_sort_data(
                'timecreated',
                get_string('report_col_date', 'block_playerhud'),
                $baseurl
            ),
        ];

        $jsvars = [
            'strings' => [
                'confirm_title' => get_string('confirmation', 'admin'),
                'yes'           => get_string('yes'),
                'cancel'        => get_string('cancel'),
            ],
        ];
        $PAGE->requires->js_call_amd('block_playerhud/manage_quests', 'init', [$jsvars]);

        $templatedata = [
            'url_add'         => (new moodle_url($baseurl, ['action' => 'add']))->out(false),
            'str_add_quest'   => get_string('quest_new', 'block_playerhud'),
            'str_col_icon'    => get_string('quest_icon_todo', 'block_playerhud'),
            'str_col_reward'  => get_string('quest_rewards_hdr', 'block_playerhud'),
            'str_col_claims'  => get_string('quest_col_claims', 'block_playerhud'),
            'str_col_enabled' => get_string('enabled', 'block_playerhud'),
            'str_col_req'     => get_string('quest_target_value', 'block_playerhud'),
            'str_actions'     => get_string('actions'),
            'str_edit'        => get_string('edit'),
            'str_delete'      => get_string('delete'),
            'str_empty'       => get_string('quests_none', 'block_playerhud'),
            'headers'         => $headers,
            'quests'          => $questsdata,
            'has_quests'      => !empty($questsdata),
        ];

        return $OUTPUT->render_from_template('block_playerhud/manage_quests', $templatedata);
    }
}
