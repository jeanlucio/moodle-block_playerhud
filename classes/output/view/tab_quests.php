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
 * Student quests tab renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use moodle_url;
use block_playerhud\quest;
use block_playerhud\game;

/**
 * Student quests tab renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_quests implements renderable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /** @var \stdClass Player object. */
    protected $player;

    /** @var int Block instance ID. */
    protected $instanceid;

    /** @var int Course ID. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     * @param \stdClass $player Player record.
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
     * Render the quests tab content.
     *
     * @return string HTML content.
     */
    public function display() {
        global $DB, $OUTPUT, $USER;

        // Load enabled quests for this block instance.
        $quests = $DB->get_records(
            'block_playerhud_quests',
            ['blockinstanceid' => $this->instanceid, 'enabled' => 1],
            'timecreated ASC'
        );

        if (empty($quests)) {
            return $OUTPUT->notification(
                get_string('quests_none', 'block_playerhud'),
                'info'
            );
        }

        // Preload claimed quests for the current user (avoid N+1).
        $questids = array_keys($quests);
        [$qinsql, $qinparams] = $DB->get_in_or_equal($questids);
        $claimedrows = $DB->get_records_select(
            'block_playerhud_quest_log',
            "userid = ? AND questid $qinsql",
            array_merge([$USER->id], $qinparams),
            '',
            'questid'
        );
        $claimedids = array_keys($claimedrows);

        // Preload reward item names (avoid N+1).
        $rewarditemids = [];
        foreach ($quests as $q) {
            if ($q->reward_itemid > 0) {
                $rewarditemids[$q->reward_itemid] = $q->reward_itemid;
            }
        }
        $rewarditems = [];
        if (!empty($rewarditemids)) {
            [$rinsql, $rinparams] = $DB->get_in_or_equal(array_values($rewarditemids));
            $rows = $DB->get_records_select(
                'block_playerhud_items',
                "id $rinsql",
                $rinparams,
                '',
                'id, name'
            );
            foreach ($rows as $row) {
                $rewarditems[$row->id] = format_string($row->name);
            }
        }

        // Calculate player level (used by TYPE_LEVEL quest checks).
        $stats       = game::get_game_stats($this->config, $this->instanceid, $this->player->currentxp);
        $playerlevel = $stats['level'];

        // Base URL for claim actions.
        $viewurl = new moodle_url('/blocks/playerhud/view.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'quests',
        ]);

        $questsdata = [];
        foreach ($quests as $q) {
            $isclaimed = in_array($q->id, $claimedids);

            // Delegate status check to the quest service class.
            $status = quest::check_status(
                $q,
                $USER->id,
                $this->courseid,
                $this->player->currentxp,
                $playerlevel
            );

            $canclaim = $status->completed && !$isclaimed;

            // Build reward text.
            $rewardparts = [];
            if ($q->reward_xp > 0) {
                $rewardparts[] = $q->reward_xp . ' XP';
            }
            if ($q->reward_itemid > 0 && isset($rewarditems[$q->reward_itemid])) {
                $rewardparts[] = $rewarditems[$q->reward_itemid];
            }
            $rewardtext = !empty($rewardparts)
                ? implode(get_string('connector_and', 'block_playerhud'), $rewardparts)
                : get_string('quest_no_reward', 'block_playerhud');

            $progresspct = $isclaimed ? 100 : $status->progress;

            $questsdata[] = [
                'id'               => $q->id,
                'name'             => format_string($q->name),
                'description_html' => !empty($q->description)
                    ? format_text($q->description, FORMAT_HTML)
                    : '',
                'image_todo'       => !empty($q->image_todo) ? $q->image_todo : '📋',
                'image_done'       => !empty($q->image_done) ? $q->image_done : '🏅',
                'type_label'       => $this->get_type_label($q->type),
                'is_activity'      => ($q->type == quest::TYPE_ACTIVITY),
                'progress_pct'     => $progresspct,
                'str_progress'     => $progresspct . '%',
                'progress_label'   => $isclaimed
                    ? get_string('quest_status_completed', 'block_playerhud')
                    : $status->label,
                'reward_text'      => $rewardtext,
                'has_reward'       => !empty($rewardparts),
                'is_claimed'       => $isclaimed,
                'can_claim'        => $canclaim,
                'url_claim'        => $canclaim
                    ? (new moodle_url($viewurl, [
                        'action'  => 'claim_quest',
                        'questid' => $q->id,
                        'sesskey' => sesskey(),
                    ]))->out(false)
                    : '',
                'str_claim'        => get_string('quest_claim', 'block_playerhud'),
                'str_pending'      => get_string('quest_status_pending', 'block_playerhud'),
                'str_claimed'      => get_string('quest_status_completed', 'block_playerhud'),
            ];
        }

        $templatedata = [
            'quests'             => $questsdata,
            'str_reward'         => get_string('quest_rewards_hdr', 'block_playerhud'),
            'str_progress_label' => get_string('report_status_completed', 'block_playerhud'),
        ];

        return $OUTPUT->render_from_template('block_playerhud/view_quests', $templatedata);
    }

    /**
     * Return the display label for a given quest type.
     *
     * @param int $type Quest type constant.
     * @return string Localised label.
     */
    protected function get_type_label($type) {
        $map = [
            quest::TYPE_LEVEL         => get_string('quest_type_level', 'block_playerhud'),
            quest::TYPE_XP_TOTAL      => get_string('quest_type_xp_total', 'block_playerhud'),
            quest::TYPE_UNIQUE_ITEMS  => get_string('quest_type_unique_items', 'block_playerhud'),
            quest::TYPE_SPECIFIC_ITEM => get_string('quest_type_specific_item', 'block_playerhud'),
            quest::TYPE_ACTIVITY      => get_string('quest_type_activity', 'block_playerhud'),
        ];
        return $map[$type] ?? '-';
    }
}
