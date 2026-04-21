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
 * RPG class selection tab for the student view.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use moodle_url;

/**
 * Class tab_class_select
 *
 * Prepares data for the student class selection screen.
 *
 * @package    block_playerhud
 */
class tab_class_select implements renderable {
    /** @var object Block configuration */
    protected $config;
    /** @var object Player record */
    protected $player;
    /** @var int Block instance ID */
    protected $instanceid;
    /** @var int Course ID */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param object $config Block configuration.
     * @param object $player Player record.
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     */
    public function __construct($config, $player, $instanceid, $courseid) {
        $this->config     = $config;
        $this->player     = $player;
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
    }

    /**
     * Render the class selection screen.
     *
     * @return string Rendered HTML.
     */
    public function display(): string {
        global $OUTPUT, $USER;

        $context = \context_block::instance($this->instanceid);
        $classes = \block_playerhud\game::get_all_classes($this->instanceid);

        // Detect currently selected class.
        $progress = \block_playerhud\game::get_player_class($this->instanceid, $USER->id);
        $currentclassid = ($progress && $progress->classid > 0) ? (int)$progress->classid : 0;

        // Bulk-fetch tier-1 portraits.
        $fs = get_file_storage();
        $tier1files = $fs->get_area_files(
            $context->id,
            'block_playerhud',
            'class_image_1',
            false,
            'itemid, sortorder',
            false
        );

        $portraitbyclass = [];
        foreach ($tier1files as $f) {
            if ($f->get_filesize() > 0 && !isset($portraitbyclass[$f->get_itemid()])) {
                $portraitbyclass[$f->get_itemid()] = $f;
            }
        }

        $actionurl = new moodle_url('/blocks/playerhud/view.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'action'     => 'select_class',
            'sesskey'    => sesskey(),
        ]);

        $classesdata = [];
        foreach ($classes as $class) {
            $portrait = null;
            if (isset($portraitbyclass[$class->id])) {
                $f = $portraitbyclass[$class->id];
                $portrait = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'block_playerhud',
                    'class_image_1',
                    $class->id,
                    $f->get_filepath(),
                    $f->get_filename()
                )->out();
            }

            $classesdata[] = [
                'id'           => $class->id,
                'name'         => format_string($class->name),
                'description'  => !empty($class->description)
                    ? format_text($class->description, FORMAT_HTML, ['noclean' => false])
                    : '',
                'base_hp'      => (int)$class->base_hp,
                'portrait'     => $portrait,
                'has_portrait' => !empty($portrait),
                'is_selected'  => ((int)$class->id === $currentclassid),
            ];
        }

        $data = [
            'classes'         => $classesdata,
            'has_classes'     => !empty($classesdata),
            'action_url'      => $actionurl->out(false),
            'has_selection'   => ($currentclassid > 0),
            'str_title'       => get_string('class_select_title', 'block_playerhud'),
            'str_intro'       => get_string('class_select_intro', 'block_playerhud'),
            'str_warning'     => get_string('class_select_warning', 'block_playerhud'),
            'str_select_btn'  => get_string('class_select_btn', 'block_playerhud'),
            'str_selected'    => get_string('class_selected_badge', 'block_playerhud'),
            'str_empty'       => get_string('class_empty', 'block_playerhud'),
        ];

        return $OUTPUT->render_from_template('block_playerhud/view_class_select', $data);
    }
}
