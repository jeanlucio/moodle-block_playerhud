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
 * Teacher management tab for Story Chapters.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use moodle_url;

/**
 * Prepares data for the chapter management tab template.
 *
 * @package    block_playerhud
 */
class tab_chapters implements renderable {
    /** @var int Block instance ID. */
    protected int $instanceid;
    /** @var int Course ID. */
    protected int $courseid;

    /**
     * Constructor.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $sort Sort column (unused, kept for uniform constructor signature).
     * @param string $dir Sort direction (unused).
     */
    public function __construct(int $instanceid, int $courseid, string $sort = '', string $dir = '') {
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
    }

    /**
     * Render the tab content.
     *
     * @return string Rendered HTML.
     */
    public function display(): string {
        global $DB, $OUTPUT, $PAGE;

        $chapters = $DB->get_records(
            'block_playerhud_chapters',
            ['blockinstanceid' => $this->instanceid],
            'sortorder ASC, id ASC'
        );

        // Bulk-fetch scene counts per chapter.
        $scenecounts = [];
        if ($chapters) {
            $chapterids = array_keys($chapters);
            [$insql, $inparams] = $DB->get_in_or_equal($chapterids);
            $sql = "SELECT chapterid, COUNT(id) AS cnt
                      FROM {block_playerhud_story_nodes}
                     WHERE chapterid $insql
                  GROUP BY chapterid";
            foreach ($DB->get_records_sql($sql, $inparams) as $row) {
                $scenecounts[(int) $row->chapterid] = (int) $row->cnt;
            }
        }

        $baseurl    = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
        ]);
        $newchapurl = new moodle_url('/blocks/playerhud/edit_chapter.php', [
            'courseid'   => $this->courseid,
            'instanceid' => $this->instanceid,
        ]);

        $chaptersdata = [];
        foreach ($chapters as $chap) {
            $scenecount = $scenecounts[$chap->id] ?? 0;
            $chaptersdata[] = [
                'id'           => (int) $chap->id,
                'title'        => format_string($chap->title),
                'intro_text'   => format_string($chap->intro_text),
                'unlock_label' => $chap->unlock_date
                    ? userdate($chap->unlock_date)
                    : get_string('drops_immediate', 'block_playerhud'),
                'scene_count'  => $scenecount,
                'has_scenes'   => ($scenecount > 0),
                'url_scenes'   => (new moodle_url('/blocks/playerhud/manage_scenes.php', [
                    'courseid'   => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'chapterid'  => $chap->id,
                ]))->out(false),
                'url_edit'     => (new moodle_url('/blocks/playerhud/edit_chapter.php', [
                    'courseid'   => $this->courseid,
                    'instanceid' => $this->instanceid,
                    'chapterid'  => $chap->id,
                ]))->out(false),
                'url_delete'   => (new moodle_url($baseurl, [
                    'action'    => 'delete_chapter',
                    'chapterid' => $chap->id,
                    'sesskey'   => sesskey(),
                    'tab'       => 'chapters',
                ]))->out(false),
                'confirm_msg'  => s(get_string('chapter_delete_confirm', 'block_playerhud')),
            ];
        }

        // Fetch items for AI story item cost selector.
        $itemrecords = $DB->get_records_menu(
            'block_playerhud_items',
            ['blockinstanceid' => $this->instanceid, 'enabled' => 1],
            'name ASC',
            'id, name'
        );
        $storyitems = [['id' => 0, 'name' => get_string('ai_story_item_none', 'block_playerhud'), 'selected' => true]];
        foreach ($itemrecords as $itemid => $itemname) {
            $storyitems[] = ['id' => $itemid, 'name' => format_string($itemname), 'selected' => false];
        }

        $data = [
            'has_chapters'     => !empty($chaptersdata),
            'chapters'         => $chaptersdata,
            'url_new_chapter'  => $newchapurl->out(false),
            'str_new_chapter'  => get_string('chapter_new', 'block_playerhud'),
            'str_ai_story_btn'            => get_string('ai_story_btn', 'block_playerhud'),
            'str_story_modal_title'       => get_string('ai_story_modal_title', 'block_playerhud'),
            'str_story_theme_label'       => get_string('ai_theme_label', 'block_playerhud'),
            'str_story_theme_placeholder' => get_string('ai_story_theme_placeholder', 'block_playerhud'),
            'str_story_generate'          => get_string('ai_generate_btn', 'block_playerhud'),
            'str_story_mechanics_title'   => get_string('ai_story_mechanics_title', 'block_playerhud'),
            'str_story_karma_gain'        => get_string('ai_story_karma_gain_label', 'block_playerhud'),
            'str_story_karma_loss'        => get_string('ai_story_karma_loss_label', 'block_playerhud'),
            'str_story_item_cost'         => get_string('ai_story_item_cost_label', 'block_playerhud'),
            'str_story_item_qty'          => get_string('ai_story_item_qty_label', 'block_playerhud'),
            'story_items'                 => $storyitems,
            'str_manage_scenes' => get_string('chapter_manage_scenes', 'block_playerhud'),
            'str_test'         => get_string('test', 'block_playerhud'),
            'str_edit'         => get_string('edit'),
            'str_delete'       => get_string('delete'),
            'str_empty'        => get_string('chapters_empty', 'block_playerhud'),
            'str_delete_title' => get_string('confirm_delete', 'block_playerhud'),
            'str_cancel'       => get_string('cancel', 'block_playerhud'),
            'str_close'        => get_string('close', 'block_playerhud'),
            'str_test_title'   => get_string('story_test_mode', 'block_playerhud'),
        ];

        $PAGE->requires->js_call_amd(
            'block_playerhud/manage_story',
            'init',
            [$this->instanceid, $this->courseid, ['close' => get_string('close', 'block_playerhud')]]
        );
        $PAGE->requires->js_call_amd('block_playerhud/ai_story', 'init', [
            $this->instanceid,
            $this->courseid,
            [
                'ai_creating'      => get_string('ai_creating', 'block_playerhud'),
                'validation_theme' => get_string('ai_validation_theme', 'block_playerhud'),
                'story_success'    => get_string('ai_story_success', 'block_playerhud'),
                'ok_reload'        => get_string('ai_ok_reload', 'block_playerhud'),
            ],
        ]);

        return $OUTPUT->render_from_template('block_playerhud/manage_chapters', $data);
    }
}
