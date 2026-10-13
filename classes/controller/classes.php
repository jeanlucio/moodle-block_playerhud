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

namespace block_playerhud\controller;

use block_playerhud\local\wizard;
use context_block;
use moodle_url;

/**
 * Controller for RPG class creation and editing.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class classes {
    /**
     * Handles the create/edit form for an RPG class.
     *
     * @return string HTML output.
     */
    public function handle_edit_form(): string {
        global $DB, $PAGE, $OUTPUT;

        $courseid   = required_param('courseid', PARAM_INT);
        $instanceid = required_param('instanceid', PARAM_INT);
        $classid    = optional_param('classid', 0, PARAM_INT);

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

        require_login($course);
        $context = context_block::instance($instanceid);
        require_capability('block/playerhud:manage', $context);
        wizard::require_course_matches_instance($context, $courseid);

        $pageurl = new moodle_url('/blocks/playerhud/edit_class.php', [
            'courseid'   => $courseid,
            'instanceid' => $instanceid,
        ]);
        if ($classid) {
            $pageurl->param('classid', $classid);
        }

        $returnurl = new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'classes',
        ]);

        $PAGE->set_url($pageurl);
        $PAGE->set_context($context);
        $PAGE->set_title(get_string('pluginname', 'block_playerhud'));
        $PAGE->set_heading(format_string($course->fullname));
        $PAGE->set_pagelayout('standard');

        $record = null;
        if ($classid) {
            $record = $DB->get_record(
                'block_playerhud_classes',
                ['id' => $classid, 'blockinstanceid' => $instanceid],
                '*',
                MUST_EXIST
            );
        }

        $mform = new \block_playerhud\form\edit_class_form($pageurl, [
            'context' => $context,
            'classid' => $classid,
        ]);

        if ($mform->is_cancelled()) {
            redirect($returnurl);
        } else if ($data = $mform->get_data()) {
            $classid = $this->save_class($data, $context, $record);
            redirect(
                $returnurl,
                get_string('class_saved', 'block_playerhud'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($record) {
            $formdata = clone $record;

            for ($tier = 1; $tier <= 5; $tier++) {
                $field   = 'image_tier' . $tier;
                $draftid = file_get_submitted_draft_itemid($field);
                file_prepare_draft_area(
                    $draftid,
                    $context->id,
                    'block_playerhud',
                    'class_image_' . $tier,
                    $record->id,
                    ['maxfiles' => 1]
                );
                $formdata->$field = $draftid;
            }

            $formdata->courseid   = $courseid;
            $formdata->instanceid = $instanceid;
            $formdata->classid    = $classid;

            $mform->set_data($formdata);
        } else {
            $mform->set_data([
                'courseid'   => $courseid,
                'instanceid' => $instanceid,
                'classid'    => 0,
            ]);
        }

        $output  = $OUTPUT->header();
        $output .= $mform->render();
        $output .= $OUTPUT->footer();
        return $output;
    }

    /**
     * Persists an RPG class record and its portrait files.
     *
     * @param \stdClass     $data    Form data.
     * @param context_block $context Block context (needed for file API).
     * @param \stdClass|null $record Existing record when editing, null when creating.
     * @return int The class ID (new or existing).
     */
    public function save_class(\stdClass $data, context_block $context, ?\stdClass $record): int {
        global $DB;

        $now = time();

        if ($data->classid && $record) {
            // Re-check the class belongs to the submitted instance (defence in depth).
            $DB->get_record(
                'block_playerhud_classes',
                ['id' => $data->classid, 'blockinstanceid' => $data->instanceid],
                'id',
                MUST_EXIST
            );

            $record->name         = $data->name;
            $record->description  = $data->description;
            $record->timemodified = $now;
            for ($tier = 1; $tier <= 5; $tier++) {
                $field = 'emoji_tier' . $tier;
                $record->$field = trim($data->$field ?? '');
            }
            $DB->update_record('block_playerhud_classes', $record);
            $classid = (int) $record->id;
        } else {
            $newrecord                  = new \stdClass();
            $newrecord->blockinstanceid = $data->instanceid;
            $newrecord->name            = $data->name;
            $newrecord->description     = $data->description;
            $newrecord->base_hp         = 100;
            for ($tier = 1; $tier <= 5; $tier++) {
                $field = 'emoji_tier' . $tier;
                $newrecord->$field = trim($data->$field ?? '');
            }
            $newrecord->timecreated     = $now;
            $newrecord->timemodified    = $now;
            $classid = $DB->insert_record('block_playerhud_classes', $newrecord);
        }

        for ($tier = 1; $tier <= 5; $tier++) {
            $field = 'image_tier' . $tier;
            file_save_draft_area_files(
                $data->$field,
                $context->id,
                'block_playerhud',
                'class_image_' . $tier,
                $classid,
                ['maxfiles' => 1]
            );
        }

        return $classid;
    }

    /**
     * Deletes an RPG class together with all of its tier portrait images, and releases
     * everything that pointed at it.
     *
     * The class must belong to the given block instance. Items and quests restricted to the
     * class lose that restriction (a list left empty becomes public), story choices that
     * required or granted it stop doing so, and the players who had it are left with no class.
     * Without this, those items and paths stayed unreachable for good and a choice could grant
     * a class that no longer existed.
     *
     * @param int $classid The class to delete.
     * @param int $instanceid The owning block instance ID.
     * @param context_block $context The block context holding the image files.
     * @return array{items: int, quests: int, choices: int, players: int} What was released.
     */
    public function delete_class(int $classid, int $instanceid, context_block $context): array {
        global $DB;

        $DB->get_record(
            'block_playerhud_classes',
            ['id' => $classid, 'blockinstanceid' => $instanceid],
            'id',
            MUST_EXIST
        );

        $fs = get_file_storage();
        for ($tier = 1; $tier <= 5; $tier++) {
            $fs->delete_area_files($context->id, 'block_playerhud', 'class_image_' . $tier, $classid);
        }

        $transaction = $DB->start_delegated_transaction();
        $released = [
            'items' => $this->release_class_list('block_playerhud_items', $instanceid, $classid),
            'quests' => $this->release_class_list('block_playerhud_quests', $instanceid, $classid),
            'choices' => $this->release_story_choices($instanceid, $classid),
            'players' => 0,
        ];
        $released['players'] = $DB->count_records(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $instanceid, 'classid' => $classid]
        );
        $DB->set_field(
            'block_playerhud_rpg_progress',
            'classid',
            0,
            ['blockinstanceid' => $instanceid, 'classid' => $classid]
        );
        $DB->delete_records('block_playerhud_classes', ['id' => $classid, 'blockinstanceid' => $instanceid]);
        $transaction->allow_commit();

        return $released;
    }

    /**
     * Takes a class out of the required_class_id list of every item or quest of the instance.
     *
     * @param string $table Either block_playerhud_items or block_playerhud_quests.
     * @param int $instanceid The owning block instance ID.
     * @param int $classid The class being deleted.
     * @return int How many rows changed.
     */
    private function release_class_list(string $table, int $instanceid, int $classid): int {
        global $DB;

        $rows = $DB->get_records_select(
            $table,
            "blockinstanceid = :iid AND required_class_id <> '0' AND required_class_id <> ''",
            ['iid' => $instanceid],
            '',
            'id, required_class_id'
        );

        // Rows are grouped by the list they end up with, so each distinct result is one query.
        $byresult = [];
        foreach ($rows as $row) {
            $new = \block_playerhud\utils::remove_class_from_list((string) $row->required_class_id, $classid);
            if ($new !== (string) $row->required_class_id) {
                $byresult[$new][] = (int) $row->id;
            }
        }
        $changed = 0;
        foreach ($byresult as $new => $ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids);
            $DB->set_field_select($table, 'required_class_id', (string) $new, "id $insql", $inparams);
            $changed += count($ids);
        }

        return $changed;
    }

    /**
     * Clears the class requirement and the class grant of the story choices of the instance.
     *
     * @param int $instanceid The owning block instance ID.
     * @param int $classid The class being deleted.
     * @return int How many choices changed.
     */
    private function release_story_choices(int $instanceid, int $classid): int {
        global $DB;

        $own = "nodeid IN (SELECT n.id
                             FROM {block_playerhud_story_nodes} n
                             JOIN {block_playerhud_chapters} c ON c.id = n.chapterid
                            WHERE c.blockinstanceid = :iid)";
        $params = ['iid' => $instanceid, 'cid1' => $classid, 'cid2' => $classid];
        $changed = $DB->count_records_select(
            'block_playerhud_choices',
            "$own AND (req_class_id = :cid1 OR set_class_id = :cid2)",
            $params
        );
        $DB->set_field_select(
            'block_playerhud_choices',
            'req_class_id',
            0,
            "$own AND req_class_id = :cid1",
            ['iid' => $instanceid, 'cid1' => $classid]
        );
        $DB->set_field_select(
            'block_playerhud_choices',
            'set_class_id',
            0,
            "$own AND set_class_id = :cid1",
            ['iid' => $instanceid, 'cid1' => $classid]
        );

        return $changed;
    }
}
