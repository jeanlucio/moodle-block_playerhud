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
 * Template data of the administrator's usage and diagnostics page.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\admin;

use block_playerhud\local\diagnostics\usage;
use moodle_url;
use renderable;
use templatable;

/**
 * Turns the diagnostics report into cards, the orphan table and the backup list.
 *
 * @package    block_playerhud
 */
class diagnostics implements renderable, templatable {
    /** @var array Report from \block_playerhud\local\diagnostics\report::get(). */
    protected array $report;

    /** @var array Backup files from integrity::list_backups(). */
    protected array $backups;

    /** @var moodle_url Page URL the forms post back to. */
    protected moodle_url $pageurl;

    /**
     * Constructor.
     *
     * @param array $report Diagnostics report.
     * @param array $backups Backup files, newest first.
     * @param moodle_url $pageurl Page URL.
     */
    public function __construct(array $report, array $backups, moodle_url $pageurl) {
        $this->report = $report;
        $this->backups = $backups;
        $this->pageurl = $pageurl;
    }

    /**
     * Export data for the template.
     *
     * @param \renderer_base $output Renderer.
     * @return array Template context.
     */
    public function export_for_template(\renderer_base $output): array {
        $adoption = $this->report['adoption'];
        $engagement = $this->report['engagement'];
        $orphans = $this->report['orphans'];
        $loose = array_filter($this->report['loose']);

        $hascleanup = !empty($orphans) || !empty($loose);
        $window = get_string('diag_lastdays', 'block_playerhud', usage::WINDOWDAYS);

        return [
            'actionurl' => $this->pageurl->out(false),
            'sesskey' => sesskey(),
            'updated' => get_string(
                'diag_updated',
                'block_playerhud',
                userdate($this->report['time'], get_string('strftimedatetimeshort', 'langconfig'))
            ),
            'adoption' => $this->adoption_cards($adoption),
            'engagement' => [
                $this->card($engagement['players'], 'diag_players'),
                $this->card($engagement['optedout'], 'diag_players_optedout'),
                $this->card($engagement['xp'], 'diag_xp_total'),
                $this->card($engagement['active'], 'diag_players_active', $window),
                $this->card($engagement['itemsreceived'], 'diag_items_received', $window),
                $this->card($engagement['questscompleted'], 'diag_quests_completed', $window),
                $this->card($engagement['trades'], 'diag_trades', $window),
                $this->card(
                    array_sum($engagement['airequests']),
                    'diag_ai_requests',
                    implode(' · ', array_filter([$window, $this->providers_summary($engagement['airequests'])]))
                ),
                $this->card($engagement['wizardruns'], 'diag_wizard_runs', $window),
            ],
            'integrity' => [
                $this->card($this->report['orphancount'], 'diag_orphan_instances'),
                $this->card($this->report['orphanplayers'], 'diag_orphan_players'),
                $this->card(array_sum($loose), 'diag_loose_rows'),
            ],
            'hasorphans' => !empty($orphans),
            'orphans' => array_map(fn($orphan) => $this->orphan_row($orphan), $orphans),
            'orphanspartial' => count($orphans) < $this->report['orphancount']
                ? get_string('diag_orphans_partial', 'block_playerhud', (object) [
                    'shown' => count($orphans),
                    'total' => $this->report['orphancount'],
                ])
                : '',
            'hasloose' => !empty($loose),
            'loose' => array_map(
                fn(string $table, int $count): array => ['label' => $this->loose_label($table), 'count' => $count],
                array_keys($loose),
                array_values($loose)
            ),
            'hascleanup' => $hascleanup,
            'hasbackups' => !empty($this->backups),
            'backups' => array_map(fn($backup) => $this->backup_row($backup), $this->backups),
        ];
    }

    /**
     * Builds the adoption cards; the Dashboard card only appears when such blocks exist.
     *
     * @param array $adoption Adoption figures from usage::get_adoption().
     * @return array Card contexts.
     */
    protected function adoption_cards(array $adoption): array {
        $cards = [
            $this->card($adoption['courses'], 'diag_courses'),
            $this->card($adoption['withitems'], 'diag_courses_withitems'),
            $this->card($adoption['playersonly'], 'diag_courses_playersonly'),
            $this->card($adoption['empty'], 'diag_courses_empty'),
            $this->card($adoption['features']['enable_rpg'], 'diag_feature_rpg'),
            $this->card($adoption['features']['enable_quests'], 'diag_feature_quests'),
            $this->card($adoption['features']['enable_ranking'], 'diag_feature_ranking'),
            $this->card($adoption['features']['enable_items'], 'diag_feature_items'),
        ];
        if ($adoption['dashboard'] > 0) {
            $cards[] = $this->card(
                $adoption['dashboard'],
                'diag_dashboard_blocks',
                get_string('diag_dashboard_hint', 'block_playerhud')
            );
        }
        return $cards;
    }

    /**
     * Builds one figure card.
     *
     * @param int $value Figure.
     * @param string $labelkey Lang string key of the label.
     * @param string $subtitle Optional detail line.
     * @return array Card context.
     */
    protected function card(int $value, string $labelkey, string $subtitle = ''): array {
        return [
            'value' => format_float($value, 0),
            'label' => get_string($labelkey, 'block_playerhud'),
            'subtitle' => $subtitle,
        ];
    }

    /**
     * Summarises AI requests per provider, busiest first.
     *
     * @param array $providers Request count keyed by provider name.
     * @return string Summary such as "Gemini 12 · Groq 3", or '' when there were none.
     */
    protected function providers_summary(array $providers): string {
        $parts = [];
        foreach ($providers as $provider => $count) {
            $name = ((string) $provider === '') ? '?' : (string) $provider;
            $parts[] = $name . ' ' . $count;
        }
        return implode(' · ', $parts);
    }

    /**
     * Describes a group of loose rows in words instead of its table name.
     *
     * Keys used: diag_loose_story_nodes, diag_loose_choices, diag_loose_inventory, diag_loose_stack,
     * diag_loose_stack_log, diag_loose_quest_log, diag_loose_trade_reqs, diag_loose_trade_rewards,
     * diag_loose_trade_log, diag_loose_wizard_objects, diag_loose_wizard_shortcodes.
     *
     * @param string $table Child table name.
     * @return string Description.
     */
    protected function loose_label(string $table): string {
        return get_string('diag_loose_' . substr($table, strlen('block_playerhud_')), 'block_playerhud');
    }

    /**
     * Builds one row of the orphan instance table.
     *
     * Instances whose course still exists start unselected: the block may have been removed
     * from a course that is still in use, by mistake.
     *
     * @param \stdClass $orphan Orphan instance from integrity::get_orphan_instances().
     * @return array Row context.
     */
    protected function orphan_row(\stdClass $orphan): array {
        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        if ($orphan->courseexists === true) {
            $course = get_string('diag_course_exists', 'block_playerhud', $orphan->courseid);
        } else if ($orphan->courseexists === false) {
            $course = get_string('diag_course_deleted', 'block_playerhud');
        } else {
            $course = get_string('diag_course_unknown', 'block_playerhud');
        }

        $activity = '-';
        if ($orphan->firstactivity > 0) {
            $activity = userdate($orphan->firstactivity, $dateformat);
            if ($orphan->lastactivity > $orphan->firstactivity) {
                $activity .= ' – ' . userdate($orphan->lastactivity, $dateformat);
            }
        }

        return [
            'id' => $orphan->id,
            'players' => $orphan->players,
            'xp' => $orphan->xp,
            'items' => $orphan->items,
            'activity' => $activity,
            'deletedusers' => $orphan->deletedusers,
            'course' => $course,
            'courseexists' => $orphan->courseexists === true,
            'checked' => $orphan->courseexists !== true,
            'selectlabel' => get_string('diag_select_instance', 'block_playerhud', $orphan->id),
        ];
    }

    /**
     * Builds one row of the backup file list.
     *
     * @param \stdClass $backup Backup file from integrity::list_backups().
     * @return array Row context.
     */
    protected function backup_row(\stdClass $backup): array {
        $download = new moodle_url($this->pageurl, [
            'action' => 'download',
            'file' => $backup->name,
            'sesskey' => sesskey(),
        ]);
        return [
            'name' => $backup->name,
            'size' => display_size($backup->size),
            'time' => userdate($backup->time, get_string('strftimedatetimeshort', 'langconfig')),
            'downloadurl' => $download->out(false),
            'downloadlabel' => get_string('diag_backup_download', 'block_playerhud', $backup->name),
            'deletelabel' => get_string('diag_backup_delete', 'block_playerhud', $backup->name),
        ];
    }
}
