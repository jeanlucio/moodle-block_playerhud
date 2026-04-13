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
 * RPG Classes tab renderer for the management panel.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use renderable;
use moodle_url;

/**
 * Class tab_classes
 *
 * Prepares data for the RPG Classes management tab.
 *
 * @package    block_playerhud
 */
class tab_classes implements renderable {
    /** @var int Block instance ID */
    protected $instanceid;

    /** @var int Course ID */
    protected $courseid;

    /**
     * Constructor. Signature must match manage.php controller: ($instanceid, $courseid, $sort, $dir).
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $sort Unused — kept for interface compatibility.
     * @param string $dir Unused — kept for interface compatibility.
     */
    public function __construct($instanceid, $courseid, $sort = '', $dir = '') {
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
    }

    /**
     * Render the tab HTML via Mustache template.
     *
     * @return string Rendered HTML.
     */
    public function display(): string {
        global $DB, $OUTPUT, $PAGE;

        $context = \context_block::instance($this->instanceid);
        $classes = $DB->get_records(
            'block_playerhud_classes',
            ['blockinstanceid' => $this->instanceid],
            'name ASC'
        );

        // Bulk-fetch tier-1 portraits to avoid N+1 queries.
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

        $basemanageurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'classes',
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

            $deleteurl = new moodle_url($basemanageurl, [
                'action'  => 'delete_class',
                'classid' => $class->id,
                'sesskey' => sesskey(),
            ]);

            $editurl = new moodle_url('/blocks/playerhud/edit_class.php', [
                'courseid'   => $this->courseid,
                'instanceid' => $this->instanceid,
                'classid'    => $class->id,
            ]);

            $classesdata[] = [
                'id'          => $class->id,
                'name'        => format_string($class->name),
                'description' => !empty($class->description)
                    ? format_text($class->description, FORMAT_HTML, ['noclean' => false])
                    : '',
                'base_hp'     => (int)$class->base_hp,
                'portrait'    => $portrait,
                'has_portrait' => !empty($portrait),
                'url_edit'    => $editurl->out(false),
                'url_delete'  => $deleteurl->out(false),
                'confirm_msg' => s(get_string('class_delete_confirm', 'block_playerhud')),
            ];
        }

        $newclassurl = new moodle_url('/blocks/playerhud/edit_class.php', [
            'courseid'   => $this->courseid,
            'instanceid' => $this->instanceid,
        ]);

        $data = [
            'classes'      => $classesdata,
            'has_classes'  => !empty($classesdata),
            'url_new'      => $newclassurl->out(false),
            'str_new'      => get_string('class_new', 'block_playerhud'),
            'str_edit'     => get_string('class_edit', 'block_playerhud'),
            'str_delete'   => get_string('delete', 'block_playerhud'),
            'str_empty'    => get_string('class_empty', 'block_playerhud'),
            'str_hp'       => get_string('class_hp_label', 'block_playerhud'),
            'str_confirm_delete' => get_string('confirm_delete', 'block_playerhud'),
            'str_cancel'   => get_string('cancel', 'block_playerhud'),
            'str_close'    => get_string('close', 'block_playerhud'),
        ];

        $PAGE->requires->js_call_amd('block_playerhud/manage_classes', 'init');

        return $OUTPUT->render_from_template('block_playerhud/manage_classes', $data);
    }
}
