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

        $orphanplayers = array_sum(array_map(static fn($orphan) => $orphan->players, $orphans));
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
            'adoption' => [
                $this->card($adoption['courses'], 'diag_courses'),
                $this->card($adoption['instances'], 'diag_instances'),
                $this->card($adoption['inuse'], 'diag_instances_inuse'),
                $this->card($adoption['withoutitems'], 'diag_instances_withoutitems'),
                $this->card($adoption['features']['enable_rpg'], 'diag_feature_rpg'),
                $this->card($adoption['features']['enable_quests'], 'diag_feature_quests'),
                $this->card($adoption['features']['enable_ranking'], 'diag_feature_ranking'),
                $this->card($adoption['features']['enable_items'], 'diag_feature_items'),
            ],
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
                $this->card(count($orphans), 'diag_orphan_instances'),
                $this->card($orphanplayers, 'diag_orphan_players'),
                $this->card(array_sum($loose), 'diag_loose_rows'),
            ],
            'hasorphans' => !empty($orphans),
            'orphans' => array_map(fn($orphan) => $this->orphan_row($orphan), $orphans),
            'hasloose' => !empty($loose),
            'loose' => array_map(
                static fn(string $table, int $count): array => ['table' => $table, 'count' => $count],
                array_keys($loose),
                array_values($loose)
            ),
            'hascleanup' => $hascleanup,
            'hasbackups' => !empty($this->backups),
            'backups' => array_map(fn($backup) => $this->backup_row($backup), $this->backups),
        ];
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
