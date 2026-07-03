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
 * Web service that runs a single step of a live gamification wizard run.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use context_block;

/**
 * Runs exactly one step of a wizard run started by {@see wizard_start}, reusing the same
 * `generate_*` methods {@see wizard_generate::execute()} calls all at once.
 *
 * Deliberately does not roll back on failure — the browser-driven loop stops instead, showing
 * the teacher a retry (re-call this same step) or undo (existing `wizard_rollback`, which works
 * against the run's manifest regardless of its `status`) choice. See § 5.9 of the project plan.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_run_step extends external_api {
    /**
     * Define parameters for wizard_run_step.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'runid' => new external_value(PARAM_INT, 'Wizard run ID, from wizard_start'),
            'steptype' => new external_value(PARAM_ALPHANUMEXT, 'Step type identifier, e.g. "items"'),
            'theme' => new external_value(PARAM_TEXT, 'Subject theme'),
            'tone' => new external_value(PARAM_TEXT, 'Narrative tone', VALUE_DEFAULT, ''),
            'tone_key' => new external_value(PARAM_ALPHA, 'Narrative tone key', VALUE_DEFAULT, 'fantasy'),
            'size' => new external_value(PARAM_ALPHA, 'Journey size: short, medium or long', VALUE_DEFAULT, 'short'),
            'item_xp_shares' => new external_multiple_structure(
                new external_value(PARAM_INT, 'XP share'),
                'XP shares from wizard_start, only used by the "items" step',
                VALUE_DEFAULT,
                []
            ),
            'mission_xp_shares' => new external_multiple_structure(
                new external_value(PARAM_INT, 'XP share'),
                'XP shares from wizard_start, only used by the "missions" step',
                VALUE_DEFAULT,
                []
            ),
            'drop_ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Drop ID'),
                'Drop IDs accumulated from earlier steps, only used by the "auto_distribute" step',
                VALUE_DEFAULT,
                []
            ),
            'is_last_step' => new external_value(
                PARAM_BOOL,
                'Whether this is the final step of the plan: finishes the run when true',
                VALUE_DEFAULT,
                false
            ),
            'report_economy' => new external_value(
                PARAM_BOOL,
                'Whether to include the XP economy summary in the response (only meaningful ' .
                    'on the last step of a plan that included Items or Missions)',
                VALUE_DEFAULT,
                false
            ),
            // Kept last: the ordered keys here must match execute()'s own parameter order — core's
            // external API dispatcher calls the method positionally, using this array's key order,
            // not the incoming AJAX call's argument names.
            'arc_beats' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Beat'),
                'Story arc beats returned by the "story_outline" step, only used by "story_chapter_N" steps',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Runs one step of a wizard run.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $runid Wizard run ID, from wizard_start.
     * @param string $steptype Step type identifier.
     * @param string $theme Subject theme.
     * @param string $tone Narrative tone.
     * @param string $tonekey Narrative tone key.
     * @param string $size Journey size: short, medium or long.
     * @param int[] $itemxpshares XP shares from wizard_start.
     * @param int[] $missionxpshares XP shares from wizard_start.
     * @param int[] $dropids Drop IDs accumulated from earlier steps.
     * @param bool $islaststep Whether this is the final step of the plan.
     * @param bool $reporteconomy Whether to include the XP economy summary.
     * @param string[] $arcbeats Story arc beats from the "story_outline" step.
     * @return array Result structure.
     */
    public static function execute(
        int $instanceid,
        int $courseid,
        int $runid,
        string $steptype,
        string $theme,
        string $tone = '',
        string $tonekey = 'fantasy',
        string $size = 'short',
        array $itemxpshares = [],
        array $missionxpshares = [],
        array $dropids = [],
        bool $islaststep = false,
        bool $reporteconomy = false,
        array $arcbeats = []
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'runid' => $runid,
            'steptype' => $steptype,
            'theme' => $theme,
            'tone' => $tone,
            'tone_key' => $tonekey,
            'size' => $size,
            'item_xp_shares' => $itemxpshares,
            'mission_xp_shares' => $missionxpshares,
            'drop_ids' => $dropids,
            'is_last_step' => $islaststep,
            'report_economy' => $reporteconomy,
            'arc_beats' => $arcbeats,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $counts = ['items' => 0, 'quests' => 0, 'trades' => 0, 'chapters' => 0, 'classes' => 0];
        $newdropids = [];
        $message = '';
        $newarcbeats = [];

        // Step types like "story_chapter_3" (one per AI chapter in the arc) are normalized to a
        // single 'story_chapter' switch case here — the index is the only per-call variable, so
        // this avoids one case per possible chapter number.
        $chapterindex = 0;
        $dispatchtype = $params['steptype'];
        if (preg_match('/^story_chapter_(\d+)$/', $dispatchtype, $matches) === 1) {
            $chapterindex = (int) $matches[1];
            $dispatchtype = 'story_chapter';
        }

        try {
            switch ($dispatchtype) {
                case 'story_chapter':
                    self::run_story_chapter_step($chapterindex, $params, $config);
                    $counts['chapters'] = 1;
                    break;

                case 'story_outline':
                    $aichapters = max(
                        0,
                        \block_playerhud\local\xp_budget::compute_chapter_count($params['size']) - 1
                    );
                    $generator = new \block_playerhud\ai\generator($params['instanceid']);
                    $outline = self::generate_with_retry(
                        static fn(): array => $generator->generate_story_outline($params['theme'], $aichapters)
                    );
                    $newarcbeats = $outline['beats'];
                    break;

                case 'items':
                    $result = wizard_generate::generate_items(
                        $params['instanceid'],
                        $config,
                        $params['theme'],
                        $params['tone'],
                        $params['size'],
                        $params['item_xp_shares'],
                        $params['runid']
                    );
                    $counts['items'] = count($result['names']);
                    $newdropids = $result['drop_ids'];
                    break;

                case 'missions':
                    $names = wizard_generate::generate_missions(
                        $params['instanceid'],
                        $params['courseid'],
                        $config,
                        $params['size'],
                        $params['tone_key'],
                        $params['mission_xp_shares'],
                        $params['runid']
                    );
                    $counts['quests'] = count($names);
                    break;

                case 'playercoin':
                    $names = wizard_generate::generate_playercoin(
                        $params['instanceid'],
                        $params['courseid'],
                        $params['runid']
                    );
                    $counts['items'] = count($names);
                    break;

                case 'avatars':
                    $names = wizard_generate::generate_avatars($params['instanceid'], $params['courseid'], $params['runid']);
                    $counts['items'] = count($names);
                    break;

                case 'rpg':
                    $rpgresult = wizard_generate::generate_rpg_classes(
                        $params['instanceid'],
                        $params['tone_key'],
                        $params['runid']
                    );
                    $counts['classes'] = count($rpgresult['class_names']);
                    $counts['chapters'] = $rpgresult['chapter_title'] !== '' ? 1 : 0;
                    break;

                case 'progress_item':
                    $result = wizard_generate::generate_progress_item(
                        $params['instanceid'],
                        $params['tone_key'],
                        $params['runid']
                    );
                    $counts['items'] = count($result['names']);
                    $newdropids = $result['drop_ids'];
                    break;

                case 'next_chapter':
                    $result = wizard_generate::generate_next_chapter(
                        $params['instanceid'],
                        $params['theme'],
                        $params['tone_key'],
                        $params['runid']
                    );
                    $counts['items'] = count($result['created_items']);
                    $counts['chapters'] = 1;
                    break;

                case 'pill':
                    $names = wizard_generate::generate_pill(
                        $params['instanceid'],
                        $params['courseid'],
                        $params['tone_key'],
                        $params['runid']
                    );
                    $counts['items'] = count($names);
                    $pilltrade = wizard_generate::generate_pill_trade($params['instanceid'], $params['runid']);
                    $counts['trades'] += $pilltrade['trade_name'] !== '' ? 1 : 0;
                    $counts['quests'] += $pilltrade['quest_name'] !== '' ? 1 : 0;
                    break;

                case 'latepenalty':
                    $lpresult = wizard_generate::generate_latepenalty($params['instanceid'], $params['runid']);
                    $counts['items'] = count($lpresult['items']);
                    $counts['quests'] += $lpresult['quest_name'] !== '' ? 1 : 0;
                    $lptradename = wizard_generate::generate_latepenalty_trade($params['instanceid'], $params['runid']);
                    $counts['trades'] += $lptradename !== '' ? 1 : 0;
                    break;

                case 'comercio':
                    $names = wizard_generate::generate_comercio($params['instanceid'], $params['runid']);
                    $counts['trades'] = count($names);
                    break;

                case 'secret_drops':
                    $names = wizard_generate::generate_secret_drops(
                        $params['instanceid'],
                        $params['courseid'],
                        $params['tone_key'],
                        $params['runid']
                    );
                    $counts['items'] = count($names);
                    break;

                case 'ranking':
                    wizard_generate::generate_ranking($params['instanceid']);
                    break;

                case 'auto_distribute':
                    $message = wizard_generate::distribute_drops(
                        $params['instanceid'],
                        $params['courseid'],
                        $params['drop_ids'],
                        $params['runid']
                    );
                    break;

                default:
                    throw new \coding_exception('Unknown wizard step type: ' . $params['steptype']);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'counts' => $counts,
                'drop_ids' => $newdropids,
                'economy_message' => '',
                'arc_beats' => [],
            ];
        }

        $economymessage = '';
        if ($params['is_last_step']) {
            \block_playerhud\local\wizard::finish_run($params['runid'], 'done');
            if ($params['report_economy']) {
                [$xpperlevel, $maxlevels] = wizard_generate::resolve_xp_settings($config);
                $health = \block_playerhud\local\analytics::economy_health($params['instanceid'], $xpperlevel, $maxlevels);
                $economymessage = wizard_generate::build_economy_message($health);
            }
        }

        return [
            'success' => true,
            'message' => $message,
            'counts' => $counts,
            'drop_ids' => $newdropids,
            'economy_message' => $economymessage,
            'arc_beats' => $newarcbeats,
        ];
    }

    /**
     * Runs a single AI-backed callable, retrying it exactly once on failure — AI generation
     * fails more often than the deterministic modules, and a chapter/outline step never writes
     * anything before this succeeds, so retrying in place is safe (see § 5.9 of the project plan:
     * this is a per-step, in-request retry, distinct from the browser's own "try again" button,
     * which resumes the whole failed step from scratch after a click).
     *
     * @param callable $callable Callable with no arguments, returning the generation result.
     * @return mixed The callable's return value.
     * @throws \Exception The second attempt's exception, if both attempts fail.
     */
    protected static function generate_with_retry(callable $callable) {
        try {
            return $callable();
        } catch (\Exception $e) {
            return $callable();
        }
    }

    /**
     * Runs one story-arc chapter step: reads the previous chapter's real text from the database
     * (never from the client — see § 5.9's "contexto vem do servidor, não do cliente"), generates
     * the next chapter against the full arc + this chapter's own beat + that previous text, and
     * records the new chapter/nodes/choices in the run's manifest.
     *
     * @param int $chapterindex 1-based AI chapter number within the arc (chapter 1 is the fixed
     *     RPG chapter, so AI chapter 1 here is the story's chapter 2 overall).
     * @param array $params Validated wizard_run_step params.
     * @param \stdClass $config Block configuration.
     * @return void
     */
    protected static function run_story_chapter_step(int $chapterindex, array $params, \stdClass $config): void {
        $beat = $params['arc_beats'][$chapterindex - 1] ?? '';
        $arcsummary = implode("\n", array_map(
            static fn(int $i, string $b): string => ($i + 1) . '. ' . $b,
            array_keys($params['arc_beats']),
            $params['arc_beats']
        ));

        $generator = new \block_playerhud\ai\generator($params['instanceid']);
        $result = self::generate_with_retry(static fn(): array => $generator->generate_story($params['theme'], [
            'item_id' => wizard_generate::resolve_or_create_progress_item(
                $params['instanceid'],
                $params['tone_key'],
                $params['runid']
            ),
            'item_qty' => wizard_generate::CHAPTER_ITEM_COST,
            'beat' => $beat,
            'arc_summary' => $arcsummary,
            'previous_context' => wizard_generate::resolve_previous_chapter_context($params['instanceid']),
        ]));

        \block_playerhud\local\wizard::record_objects($params['runid'], 'block_playerhud_chapters', [$result['chapter_id']]);
        if (!empty($result['node_ids'])) {
            \block_playerhud\local\wizard::record_objects($params['runid'], 'block_playerhud_story_nodes', $result['node_ids']);
        }
        if (!empty($result['choice_ids'])) {
            \block_playerhud\local\wizard::record_objects($params['runid'], 'block_playerhud_choices', $result['choice_ids']);
        }
    }

    /**
     * Return structure for wizard_run_step.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether this step succeeded'),
            'message' => new external_value(PARAM_RAW, 'Error, or a note such as "no activities yet"', VALUE_OPTIONAL),
            'counts' => new external_single_structure([
                'items' => new external_value(PARAM_INT, 'Items created this step'),
                'quests' => new external_value(PARAM_INT, 'Quests created this step'),
                'trades' => new external_value(PARAM_INT, 'Trades created this step'),
                'chapters' => new external_value(PARAM_INT, 'Story chapters created this step'),
                'classes' => new external_value(PARAM_INT, 'RPG classes created this step'),
            ]),
            'drop_ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Drop ID'),
                'Drop IDs created this step, to accumulate for a later auto_distribute step'
            ),
            'economy_message' => new external_value(
                PARAM_TEXT,
                'XP economy summary, only present on the last step when requested',
                VALUE_OPTIONAL
            ),
            'arc_beats' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Beat'),
                'Story arc beats, only present after the "story_outline" step'
            ),
        ]);
    }
}
