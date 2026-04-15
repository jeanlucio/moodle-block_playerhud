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
 * Story manager — business logic for chapters, scenes and choices.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

/**
 * Manages story progression, scene loading and choice processing.
 *
 * @package    block_playerhud
 */
class story_manager {
    /**
     * Return the RPG progress record, creating one if it does not exist yet.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @return object Progress record.
     */
    public static function get_or_create_progress(int $instanceid, int $userid): object {
        global $DB;

        $progress = $DB->get_record(
            'block_playerhud_rpg_progress',
            ['blockinstanceid' => $instanceid, 'userid' => $userid]
        );

        if ($progress) {
            return $progress;
        }

        $newprogress = new \stdClass();
        $newprogress->blockinstanceid   = $instanceid;
        $newprogress->userid            = $userid;
        $newprogress->classid           = 0;
        $newprogress->karma             = 0;
        $newprogress->current_nodes     = json_encode([]);
        $newprogress->completed_chapters = json_encode([]);

        try {
            $id = $DB->insert_record('block_playerhud_rpg_progress', $newprogress);
            return $DB->get_record('block_playerhud_rpg_progress', ['id' => $id]);
        } catch (\Exception $e) {
            unset($e);
            return $DB->get_record(
                'block_playerhud_rpg_progress',
                ['blockinstanceid' => $instanceid, 'userid' => $userid]
            );
        }
    }

    /**
     * Load the current (or starting) scene for a chapter.
     *
     * Resumes from the last saved node, or begins at the start node.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @return array Response data for the web service.
     */
    public static function load_scene(int $instanceid, int $userid, int $chapterid): array {
        global $DB;

        $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        $progress = self::get_or_create_progress($instanceid, $userid);
        $savednodesmap = json_decode($progress->current_nodes, true) ?: [];

        $nodeidtoload = 0;
        if (isset($savednodesmap[$chapterid])) {
            $data = $savednodesmap[$chapterid];
            $nodeidtoload = is_array($data) ? (int) end($data) : (int) $data;
        }

        if ($nodeidtoload > 0) {
            $node = $DB->get_record('block_playerhud_story_nodes', ['id' => $nodeidtoload]);
        } else {
            $node = $DB->get_record(
                'block_playerhud_story_nodes',
                ['chapterid' => $chapterid, 'is_start' => 1]
            );

            if ($node) {
                $savednodesmap[$chapterid] = [(int) $node->id];
                $DB->set_field(
                    'block_playerhud_rpg_progress',
                    'current_nodes',
                    json_encode($savednodesmap),
                    ['id' => $progress->id]
                );
            }
        }

        if (!$node) {
            throw new \moodle_exception('story_error_node_not_found', 'block_playerhud');
        }

        $completedarr = json_decode($progress->completed_chapters, true) ?: [];
        $result = [
            'node' => self::prepare_node_data($instanceid, $node, $userid, false),
        ];

        if (in_array((int) $chapterid, array_map('intval', $completedarr))) {
            $result['finished']  = true;
            $result['chapterid'] = $chapterid;
            $result['message']   = get_string('story_chapter_completed', 'block_playerhud');
        }

        return $result;
    }

    /**
     * Process a player's choice and advance the story.
     *
     * Handles item costs, karma adjustments, class changes, and history recording.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $choiceid Choice ID.
     * @return array Response data for the web service.
     */
    public static function make_choice(int $instanceid, int $userid, int $choiceid): array {
        global $DB;

        $choice = $DB->get_record('block_playerhud_choices', ['id' => $choiceid], '*', MUST_EXIST);
        $progress = self::get_or_create_progress($instanceid, $userid);
        $events = [];

        // Item cost: validate and consume.
        if ($choice->cost_itemid > 0) {
            $qtyneeded = max(1, (int) $choice->cost_item_qty);
            $inventory = $DB->get_records(
                'block_playerhud_inventory',
                ['userid' => $userid, 'itemid' => $choice->cost_itemid],
                'timecreated ASC',
                'id',
                0,
                $qtyneeded
            );

            if (count($inventory) < $qtyneeded) {
                $itemname = $DB->get_field('block_playerhud_items', 'name', ['id' => $choice->cost_itemid]);
                throw new \moodle_exception(
                    'story_error_need_item',
                    'block_playerhud',
                    '',
                    $qtyneeded . 'x ' . format_string((string) $itemname)
                );
            }

            $DB->delete_records_list('block_playerhud_inventory', 'id', array_keys($inventory));
            $itemname = $DB->get_field('block_playerhud_items', 'name', ['id' => $choice->cost_itemid]);
            $events[] = [
                'type' => 'item_loss',
                'msg'  => get_string('item_used', 'block_playerhud', format_string((string) $itemname)),
            ];
        }

        // Class assignment.
        if ($choice->set_class_id > 0) {
            $DB->set_field(
                'block_playerhud_rpg_progress',
                'classid',
                $choice->set_class_id,
                ['id' => $progress->id]
            );
            $classname = $DB->get_field('block_playerhud_classes', 'name', ['id' => $choice->set_class_id]);
            $events[] = [
                'type' => 'class',
                'msg'  => get_string('new_class', 'block_playerhud', format_string((string) $classname)),
            ];
        }

        // Karma adjustment.
        if ((int) $choice->karma_delta !== 0) {
            game::adjust_karma($instanceid, $userid, (int) $choice->karma_delta);
            $sign = ((int) $choice->karma_delta > 0) ? '+' : '';
            $events[] = [
                'type' => 'karma',
                'msg'  => get_string('karma_event', 'block_playerhud', $sign . (int) $choice->karma_delta),
            ];
        }

        $currentnode = $DB->get_record(
            'block_playerhud_story_nodes',
            ['id' => $choice->nodeid],
            '*',
            MUST_EXIST
        );
        $chapterid = (int) $currentnode->chapterid;

        $result = ['events' => $events];

        if ((int) $choice->next_nodeid > 0) {
            $savednodesmap = json_decode($progress->current_nodes, true) ?: [];
            $path = isset($savednodesmap[$chapterid]) ? (array) $savednodesmap[$chapterid] : [];
            $path[] = (int) $choice->next_nodeid;
            $savednodesmap[$chapterid] = $path;

            $DB->set_field(
                'block_playerhud_rpg_progress',
                'current_nodes',
                json_encode($savednodesmap),
                ['id' => $progress->id]
            );

            $nextnode = $DB->get_record(
                'block_playerhud_story_nodes',
                ['id' => $choice->next_nodeid],
                '*',
                MUST_EXIST
            );
            $result['node'] = self::prepare_node_data($instanceid, $nextnode, $userid, false);

            // Terminal node: no choices means this path has ended — mark chapter as finished.
            if (empty($result['node']['choices'])) {
                $completedarr = json_decode($progress->completed_chapters, true) ?: [];
                $completedarr = array_map('intval', $completedarr);

                if (!in_array($chapterid, $completedarr)) {
                    $completedarr[] = $chapterid;
                    $DB->set_field(
                        'block_playerhud_rpg_progress',
                        'completed_chapters',
                        json_encode($completedarr),
                        ['id' => $progress->id]
                    );
                }

                $result['finished']  = true;
                $result['chapterid'] = $chapterid;
                $result['message']   = get_string('story_chapter_completed', 'block_playerhud');
            }
        } else {
            $completedarr = json_decode($progress->completed_chapters, true) ?: [];
            $completedarr = array_map('intval', $completedarr);

            if (!in_array($chapterid, $completedarr)) {
                $completedarr[] = $chapterid;
                $DB->set_field(
                    'block_playerhud_rpg_progress',
                    'completed_chapters',
                    json_encode($completedarr),
                    ['id' => $progress->id]
                );
            }

            $result['finished']  = true;
            $result['chapterid'] = $chapterid;
            $result['message']   = get_string('story_chapter_completed', 'block_playerhud');
        }

        return $result;
    }

    /**
     * Load the full story recap HTML for a completed chapter.
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID.
     * @param int $chapterid Chapter ID.
     * @return string Rendered HTML recap.
     */
    public static function load_recap(int $instanceid, int $userid, int $chapterid): string {
        global $DB;

        $progress = self::get_or_create_progress($instanceid, $userid);
        $savednodesmap = json_decode($progress->current_nodes, true) ?: [];

        if (!isset($savednodesmap[$chapterid]) || empty($savednodesmap[$chapterid])) {
            throw new \moodle_exception('story_error_no_history', 'block_playerhud');
        }

        $pathdata = (array) $savednodesmap[$chapterid];

        [$insql, $inparams] = $DB->get_in_or_equal($pathdata);
        $inparams[] = $chapterid;
        $sql = "SELECT * FROM {block_playerhud_story_nodes} WHERE id $insql AND chapterid = ?";
        $nodes = $DB->get_records_sql($sql, $inparams);

        global $OUTPUT;

        $context = \context_block::instance($instanceid);
        $scenesdata = [];
        foreach ($pathdata as $nid) {
            if (isset($nodes[$nid])) {
                $scenesdata[] = [
                    'content' => format_text($nodes[$nid]->content, FORMAT_HTML, ['context' => $context]),
                ];
            }
        }

        return $OUTPUT->render_from_template('block_playerhud/story_recap', [
            'scenes'  => $scenesdata,
            'str_end' => get_string('story_end', 'block_playerhud'),
        ]);
    }

    /**
     * Load the starting node of a chapter for preview (no progress saved).
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID for permission context.
     * @param int $chapterid Chapter ID.
     * @return array Response data for the web service.
     */
    public static function load_preview_start(int $instanceid, int $userid, int $chapterid): array {
        global $DB;

        $DB->get_record(
            'block_playerhud_chapters',
            ['id' => $chapterid, 'blockinstanceid' => $instanceid],
            '*',
            MUST_EXIST
        );

        $node = $DB->get_record('block_playerhud_story_nodes', ['chapterid' => $chapterid, 'is_start' => 1]);

        if (!$node) {
            $node = $DB->get_record_sql(
                'SELECT * FROM {block_playerhud_story_nodes} WHERE chapterid = ? ORDER BY id ASC',
                [$chapterid],
                IGNORE_MULTIPLE
            );
        }

        if (!$node) {
            throw new \moodle_exception('story_error_chapter_empty', 'block_playerhud');
        }

        return ['node' => self::prepare_node_data($instanceid, $node, $userid, true)];
    }

    /**
     * Navigate to the next scene during a preview (no progress saved).
     *
     * @param int $instanceid Block instance ID.
     * @param int $userid User ID for permission context.
     * @param int $choiceid Choice ID.
     * @return array Response data for the web service.
     */
    public static function preview_nav(int $instanceid, int $userid, int $choiceid): array {
        global $DB;

        $choice = $DB->get_record('block_playerhud_choices', ['id' => $choiceid], '*', MUST_EXIST);

        if ((int) $choice->next_nodeid > 0) {
            $nextnode = $DB->get_record(
                'block_playerhud_story_nodes',
                ['id' => $choice->next_nodeid],
                '*',
                MUST_EXIST
            );
            return ['node' => self::prepare_node_data($instanceid, $nextnode, $userid, true)];
        }

        return [
            'finished' => true,
            'message'  => get_string('story_test_finished', 'block_playerhud'),
        ];
    }

    /**
     * Prepare node data for a JSON web service response.
     *
     * Bulk-fetches all referenced classes and items to avoid N+1 queries.
     *
     * @param int $instanceid Block instance ID.
     * @param object $node The story node record.
     * @param int $userid User ID.
     * @param bool $ispreview True to show requirement labels without enforcing them.
     * @return array Node data with content HTML and choices array.
     */
    public static function prepare_node_data(
        int $instanceid,
        object $node,
        int $userid,
        bool $ispreview
    ): array {
        global $DB;

        $context = \context_block::instance($instanceid);
        $content = format_text($node->content, FORMAT_HTML, ['context' => $context]);

        $choicesraw = $DB->get_records('block_playerhud_choices', ['nodeid' => $node->id], 'id ASC');

        if (empty($choicesraw)) {
            return ['content' => $content, 'choices' => []];
        }

        // Collect all referenced class and item IDs for bulk fetching.
        $classids = [];
        $itemids  = [];
        foreach ($choicesraw as $ch) {
            if ((int) $ch->req_class_id > 0) {
                $classids[(int) $ch->req_class_id] = (int) $ch->req_class_id;
            }
            if ((int) $ch->cost_itemid > 0) {
                $itemids[(int) $ch->cost_itemid] = (int) $ch->cost_itemid;
            }
        }

        $classes = !empty($classids)
            ? $DB->get_records_list('block_playerhud_classes', 'id', $classids)
            : [];
        $items = !empty($itemids)
            ? $DB->get_records_list('block_playerhud_items', 'id', $itemids)
            : [];

        // Bulk-fetch inventory counts for cost items (one query, not N+1).
        $invcounts = [];
        if (!$ispreview && !empty($itemids)) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_values($itemids));
            $inparams[] = $userid;
            $sql = "SELECT itemid, COUNT(id) AS cnt
                      FROM {block_playerhud_inventory}
                     WHERE itemid $insql AND userid = ?
                  GROUP BY itemid";
            foreach ($DB->get_records_sql($sql, $inparams) as $row) {
                $invcounts[(int) $row->itemid] = (int) $row->cnt;
            }
        }

        // Player class and karma (one query, not per-choice).
        $myclass = 0;
        $mykarma = 0;
        if (!$ispreview) {
            $prog = $DB->get_record(
                'block_playerhud_rpg_progress',
                ['blockinstanceid' => $instanceid, 'userid' => $userid]
            );
            if ($prog) {
                $myclass = (int) $prog->classid;
                $mykarma = (int) $prog->karma;
            }
        }

        $choices = [];
        foreach ($choicesraw as $ch) {
            $btnclass    = 'btn-primary';
            $disabled    = false;
            $reqclassname = '';
            $reqclassmet  = true;
            $reqkarmamin  = 0;
            $reqkarmamet  = true;
            $costitemname = '';
            $costitemqty  = 0;
            $costitemmet  = true;

            // Requirement: class.
            if ((int) $ch->req_class_id > 0) {
                $reqclassname = isset($classes[$ch->req_class_id])
                    ? format_string($classes[$ch->req_class_id]->name)
                    : '?';

                if (!$ispreview && (int) $ch->req_class_id !== $myclass) {
                    $reqclassmet = false;
                    $disabled    = true;
                    $btnclass    = 'btn-outline-secondary';
                }
            }

            // Requirement: karma minimum.
            if ((int) $ch->req_karma_min !== 0) {
                $reqkarmamin = (int) $ch->req_karma_min;
                if (!$ispreview && $mykarma < $reqkarmamin) {
                    $reqkarmamet = false;
                    $disabled    = true;
                    $btnclass    = 'btn-outline-secondary';
                }
            }

            // Cost: item.
            if ((int) $ch->cost_itemid > 0) {
                $costitemqty  = max(1, (int) $ch->cost_item_qty);
                $costitemname = isset($items[$ch->cost_itemid])
                    ? format_string($items[$ch->cost_itemid]->name)
                    : '?';

                if (!$ispreview) {
                    $invcount = $invcounts[(int) $ch->cost_itemid] ?? 0;
                    if ($invcount < $costitemqty) {
                        $costitemmet = false;
                        $disabled    = true;
                        $btnclass    = 'btn-secondary';
                    }
                }
            }

            $choices[] = [
                'id'              => (int) $ch->id,
                'text'            => format_string($ch->text),
                'btnclass'        => $btnclass,
                'disabled'        => $disabled,
                'req_class_name'  => $reqclassname,
                'req_class_met'   => $reqclassmet,
                'req_karma_min'   => $reqkarmamin,
                'req_karma_met'   => $reqkarmamet,
                'cost_item_name'  => $costitemname,
                'cost_item_qty'   => $costitemqty,
                'cost_item_met'   => $costitemmet,
                'str_req_class'   => $reqclassname !== ''
                    ? get_string('req_class_label', 'block_playerhud', $reqclassname)
                    : '',
                'str_req_karma'   => $reqkarmamin !== 0
                    ? get_string('req_karma_label', 'block_playerhud', $reqkarmamin)
                    : '',
                'str_low_karma'   => get_string('low_karma', 'block_playerhud'),
                'str_cost_item'   => $costitemname !== ''
                    ? get_string('cost_item_label', 'block_playerhud', $costitemqty . 'x ' . $costitemname)
                    : '',
                'str_missing_item' => ($costitemname !== '' && !$costitemmet)
                    ? get_string('missing_item', 'block_playerhud', $costitemqty . 'x ' . $costitemname)
                    : '',
                'is_preview'      => $ispreview,
            ];
        }

        return ['content' => $content, 'choices' => $choices];
    }
}
