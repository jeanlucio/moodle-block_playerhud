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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Ranking tab output renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_ranking implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /** @var bool Is the user a teacher? */
    protected $isteacher;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player object.
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param bool $isteacher Is user teacher?
     */
    public function __construct($config, $player, $instanceid, $courseid, $isteacher) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
        $this->isteacher = $isteacher;
    }

    /**
     * Display method called by view.php.
     * (Maintaining compatibility with the original view.php switch case).
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/view_ranking', $this->export_for_template($OUTPUT));
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return array Data for the template.
     */
    public function export_for_template(renderer_base $output) {
        // 1. Global Configuration Checks.
        if (empty($this->config->enable_ranking)) {
            return ['is_disabled' => true, 'str_disabled' => get_string('ranking_disabled', 'block_playerhud')];
        }

        // 2. User Visibility State (Student).
        $isvisible = ($this->player->ranking_visibility == 1);

        // URL for student to toggle privacy.
        $urltoggleprivacy = new moodle_url('/blocks/playerhud/view.php', [
            'id' => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab' => 'toggle_ranking_pref',
            'sesskey' => sesskey(),
        ]);

        // 3. Privacy Button Configuration (Student).
        if ($isvisible) {
            $btnlabel = get_string('ranking_disable', 'block_playerhud');
            $btnicon = 'fa-eye-slash';
            $btnclass = 'btn-outline-danger';
        } else {
            $btnlabel = get_string('enable_ranking', 'block_playerhud');
            $btnicon = 'fa-eye';
            $btnclass = 'btn-success text-white shadow-sm';
        }

        // 4. Fetch Data (ONLY if visible or if teacher).
        $individual = [];
        $groups = [];
        $hasgroups = false;
        $hasplayers = false;
        $showcontent = false;

        // Teacher Filter Logic.
        $teacherfilteractive = false;
        $urlteacherfilter = '';
        $strteacherfilter = '';
        $btnteacherclass = '';

        if ($this->isteacher) {
            // Check if teacher asked to hide ghosts.
            $hideghosts = optional_param('hide_ghosts', 0, PARAM_INT);
            $teacherfilteractive = true;

            $urlteacherfilter = new moodle_url('/blocks/playerhud/view.php', [
                'id' => $this->courseid,
                'instanceid' => $this->instanceid,
                'tab' => 'ranking',
                'hide_ghosts' => $hideghosts ? 0 : 1,
            ]);

            if ($hideghosts) {
                // Correction: Using get_string.
                $strteacherfilter = get_string('ranking_filter_show', 'block_playerhud');
                $btnteacherclass = 'btn-outline-secondary';
            } else {
                // Correction: Using get_string.
                $strteacherfilter = get_string('ranking_filter_hide', 'block_playerhud');
                $btnteacherclass = 'btn-outline-primary';
            }
        }

        if ($isvisible || $this->isteacher) {
            $showcontent = true;
            $data = \block_playerhud\game::get_leaderboard(
                $this->instanceid,
                $this->courseid,
                $this->player->userid,
                $this->isteacher
            );

            $individual = $data['individual'];
            $groups = $data['groups'];

            // Apply filter to list if teacher requested.
            if ($this->isteacher && !empty($hideghosts)) {
                $individual = array_filter($individual, function ($user) {
                    // Keep only if it has a rank (not '-').
                    return $user->rank !== '-';
                });
                // Re-index array for mustache.
                $individual = array_values($individual);
            }

            $hasgroups = !empty($groups);
            $hasplayers = !empty($individual);
        }

        // 5. Return.
        return [
            'is_disabled' => false,
            'privacy_visible' => $isvisible,
            'show_content' => $showcontent,
            'is_teacher' => $this->isteacher, // To display extra controls.

            // Student Control.
            'url_toggle_privacy' => $urltoggleprivacy->out(false),
            'str_btn_toggle' => $btnlabel,
            'btn_toggle_icon' => $btnicon,
            'btn_toggle_class' => $btnclass,

            // Teacher Control.
            'teacher_filter_active' => $teacherfilteractive,
            'url_teacher_filter' => $urlteacherfilter ? $urlteacherfilter->out(false) : '',
            'str_teacher_filter' => $strteacherfilter,
            'btn_teacher_class' => $btnteacherclass,

            'str_privacy_title' => get_string('my_visibility', 'block_playerhud'),
            'str_visible' => get_string('visible', 'block_playerhud'),
            'str_hidden' => get_string('hidden', 'block_playerhud'),
            'str_visible_desc' => get_string('visible_desc', 'block_playerhud'),
            'str_hidden_help' => get_string('ranking_hidden_help', 'block_playerhud', $btnlabel),
            'str_hidden_desc' => get_string('hidden_desc', 'block_playerhud'),

            'individual' => $individual,
            'groups' => $groups,
            'has_groups' => $hasgroups,
            'has_players' => $hasplayers,
            'no_ranking_data' => get_string('no_ranking_data', 'block_playerhud'),

            'str_tab_individual' => get_string('rank_individual', 'block_playerhud'),
            'str_tab_groups' => get_string('rank_groups', 'block_playerhud'),
            'str_col_rank' => '#',
            'str_col_player' => get_string('student', 'block_playerhud'),
            'str_col_level' => get_string('level', 'block_playerhud'),
            'str_col_xp' => get_string('xp', 'block_playerhud'),
            'str_col_group' => get_string('group', 'group'),
            'str_col_members' => get_string('members', 'block_playerhud'),
            'str_col_avg' => get_string('average', 'block_playerhud'),
            'str_col_date' => get_string('str_col_date', 'block_playerhud'),
        ];
    }
}
