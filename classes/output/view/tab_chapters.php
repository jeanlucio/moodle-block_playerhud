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
 * Student story tab — chapter list with modal reader.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;

/**
 * Prepares data for the student story chapter list template.
 *
 * @package    block_playerhud
 */
class tab_chapters implements renderable {
    /** @var object Block configuration. */
    protected $config;
    /** @var object Player record. */
    protected $player;
    /** @var int Block instance ID. */
    protected int $instanceid;
    /** @var int Course ID. */
    protected int $courseid;

    /**
     * Constructor.
     *
     * @param object $config Block configuration.
     * @param object $player Player record.
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     */
    public function __construct($config, $player, int $instanceid, int $courseid = 0) {
        $this->config     = $config;
        $this->player     = $player;
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
    }

    /**
     * Render the tab content.
     *
     * @return string Rendered HTML.
     */
    public function display(): string {
        global $DB, $OUTPUT, $PAGE, $USER;

        $chapters = $DB->get_records(
            'block_playerhud_chapters',
            ['blockinstanceid' => $this->instanceid],
            'sortorder ASC, id ASC'
        );

        $progress = $DB->get_record(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $this->instanceid, 'userid' => $USER->id]
        );

        $completedids = [];
        if ($progress && !empty($progress->completed_chapters)) {
            $completedids = array_map('intval', json_decode($progress->completed_chapters, true) ?: []);
        }

        $now          = time();
        $chaptersdata = [];

        foreach ($chapters as $chap) {
            $iscompleted = in_array((int) $chap->id, $completedids);
            $islocked    = ($chap->unlock_date > 0 && $chap->unlock_date > $now);

            if ($iscompleted) {
                $statusicon  = 'fa-check-circle text-success';
                $itemclasses = 'ph-chapter-item ph-chapter-item--completed';
                $statustext  = get_string('completed', 'block_playerhud');
            } else if ($islocked) {
                $statusicon  = 'fa-lock text-danger';
                $itemclasses = 'ph-chapter-item ph-chapter-item--locked';
                $statustext  = get_string('available', 'block_playerhud') . ': ' . userdate($chap->unlock_date);
            } else {
                $statusicon  = 'fa-book text-primary';
                $itemclasses = 'ph-chapter-item ph-chapter-item--available';
                $statustext  = get_string('click_to_read', 'block_playerhud');
            }

            if ($iscompleted) {
                $chapterstatus = 'completed';
            } else if ($islocked) {
                $chapterstatus = 'locked';
            } else {
                $chapterstatus = 'available';
            }

            $chaptersdata[] = [
                'id'             => (int) $chap->id,
                'title'          => format_string($chap->title),
                'intro_text'     => format_string($chap->intro_text),
                'status_icon'    => $statusicon,
                'item_classes'   => $itemclasses,
                'status_text'    => $statustext,
                'is_available'   => (!$islocked && !$iscompleted),
                'is_completed'   => $iscompleted,
                'is_locked'      => $islocked,
                'chapter_status' => $chapterstatus,
                'str_recap'      => get_string('read_again', 'block_playerhud'),
            ];
        }

        $data = [
            'has_chapters'    => !empty($chaptersdata),
            'chapters'        => $chaptersdata,
            'str_story'       => get_string('story_shortcut', 'block_playerhud'),
            'str_close'       => get_string('close', 'block_playerhud'),
            'str_empty'       => get_string('chapters_empty', 'block_playerhud'),
            'str_loading'     => get_string('story_loading', 'block_playerhud'),
            'str_completed'   => get_string('story_chapter_completed', 'block_playerhud'),
            'str_read_again'  => get_string('read_again', 'block_playerhud'),
            'str_filter_by'   => get_string('story_filter_by', 'block_playerhud'),
            'str_filter_all'  => get_string('story_filter_all', 'block_playerhud'),
            'str_filter_read' => get_string('story_filter_read', 'block_playerhud'),
            'str_filter_unread' => get_string('story_filter_unread', 'block_playerhud'),
        ];

        $PAGE->requires->js_call_amd(
            'block_playerhud/story_player',
            'init',
            [
                $this->instanceid,
                $this->player->courseid ?? 0,
                [
                    'close'      => get_string('close', 'block_playerhud'),
                    'completed'  => get_string('story_chapter_completed', 'block_playerhud'),
                    'readAgain'  => get_string('read_again', 'block_playerhud'),
                    'loading'    => get_string('story_loading', 'block_playerhud'),
                    'testFinish' => get_string('story_test_finished', 'block_playerhud'),
                    'error'      => get_string('error_connection', 'block_playerhud'),
                ],
            ]
        );

        return $OUTPUT->render_from_template('block_playerhud/view_story', $data);
    }
}
