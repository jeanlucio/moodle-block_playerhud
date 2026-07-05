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
 * Web service that starts a live, step-by-step gamification wizard run.
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
 * Creates a wizard run and returns its step plan, for the browser-driven live progress bar.
 *
 * Mirrors {@see wizard_generate::execute_parameters()} exactly (same `include_*` flags), but
 * instead of running every selected module in one request, this only creates the run and
 * computes the ordered plan of steps — one per module, plus one for auto-distribute when
 * selected. The browser then drives {@see wizard_run_step} once per step, updating a single
 * progress bar live, ending in a quantity report instead of a name list. See § 5.9 of the
 * project plan for the full design.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_start extends external_api {
    /**
     * Define parameters for wizard_start.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return wizard_generate::execute_parameters();
    }

    /**
     * Creates a wizard run and builds its step plan.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $theme Subject theme.
     * @param string $tone Narrative tone.
     * @param string $size Journey size: short, medium or long.
     * @param bool $includeitems Whether to generate the Items & Trade module.
     * @param bool $includemissions Whether to generate heuristic Mission suggestions.
     * @param bool $includeplayercoin Whether to create the PlayerCoin item.
     * @param bool $includeavatars Whether to create the pre-defined avatar item pack.
     * @param bool $includerpg Whether to create the RPG class pack and fixed Chapter 1.
     * @param string $tonekey Narrative tone key for RPG content and the progress item.
     * @param bool $distributeitems Whether to insert the Items module's drops into activities.
     * @param bool $includeprogressitem Whether to create the themed progress item.
     * @param bool $includenextchapter Whether to generate a new AI story chapter.
     * @param bool $includecomercio Whether to wire PlayerCoin<->Avatar Pack trades.
     * @param bool $includepill Whether to create the Knowledge Pill and Book items.
     * @param bool $includelatepenalty Whether to create the Deadline Extension item.
     * @param bool $includesecretdrops Whether to create the Secret Drops collectible.
     * @param bool $includeranking Whether to turn on the block's ranking, if not already on.
     * @param bool $distributeprogressitem Whether to insert the RPG item's drop into an activity.
     * @param bool $distributeplayercoin Whether to auto-insert the PlayerCoin drop into the news forum.
     * @param bool $distributepill Whether to auto-spread the Knowledge Pill drops across activities.
     * @param bool $distributesecret Whether to auto-spread the Secret Drops collectible across activities.
     * @return array Result structure.
     */
    public static function execute(
        int $instanceid,
        int $courseid,
        string $theme,
        string $tone = '',
        string $size = 'short',
        bool $includeitems = true,
        bool $includemissions = false,
        bool $includeplayercoin = false,
        bool $includeavatars = false,
        bool $includerpg = false,
        string $tonekey = 'fantasy',
        bool $distributeitems = true,
        bool $includeprogressitem = false,
        bool $includenextchapter = false,
        bool $includecomercio = false,
        bool $includepill = false,
        bool $includelatepenalty = false,
        bool $includesecretdrops = false,
        bool $includeranking = false,
        bool $distributeprogressitem = true,
        bool $distributeplayercoin = true,
        bool $distributepill = true,
        bool $distributesecret = true
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid' => $courseid,
            'theme' => $theme,
            'tone' => $tone,
            'size' => $size,
            'include_items' => $includeitems,
            'include_missions' => $includemissions,
            'include_playercoin' => $includeplayercoin,
            'include_avatars' => $includeavatars,
            'include_rpg' => $includerpg,
            'tone_key' => $tonekey,
            'distribute_items' => $distributeitems,
            'include_progress_item' => $includeprogressitem,
            'include_next_chapter' => $includenextchapter,
            'include_comercio' => $includecomercio,
            'include_pill' => $includepill,
            'include_latepenalty' => $includelatepenalty,
            'include_secret_drops' => $includesecretdrops,
            'include_ranking' => $includeranking,
            'distribute_progress_item' => $distributeprogressitem,
            'distribute_playercoin' => $distributeplayercoin,
            'distribute_pill' => $distributepill,
            'distribute_secret' => $distributesecret,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $steptypes = wizard_generate::build_step_types($params);
        // The run's own manifest stores the logical module list (unexpanded) — a human reading
        // the run history should see "next_chapter", not 6 individual "story_chapter_N" entries.
        $runid = \block_playerhud\local\wizard::start_run($params['instanceid'], (int) $USER->id, $steptypes);

        [$itemxpshares, $missionxpshares, $pillbonusxp, $latepenaltybonusxp] = wizard_generate::compute_shared_xp_shares(
            $params['instanceid'],
            $config,
            $params
        );

        $plansteptypes = self::expand_story_arc($steptypes, $params['size']);
        $steps = array_map(
            static fn(string $type): array => ['type' => $type, 'label' => self::step_label($type)],
            $plansteptypes
        );

        return [
            'runid' => $runid,
            'steps' => $steps,
            'total' => count($steps),
            'item_xp_shares' => $itemxpshares,
            'mission_xp_shares' => $missionxpshares,
            'pill_bonus_xp' => $pillbonusxp,
            'latepenalty_bonus_xp' => $latepenaltybonusxp,
            'has_slow_step' => in_array('next_chapter', $steptypes, true),
        ];
    }

    /**
     * Expands the single "next_chapter" module into the live-progress plan's real granularity:
     * one "story_outline" step (the AI arc skeleton) followed by one "story_chapter_N" step per
     * AI-generated chapter — so the progress bar can show "Chapter 3 of 6" instead of the whole
     * arc looking like one opaque step. Every other step type passes through unchanged.
     *
     * @param string[] $steptypes The logical module list from build_step_types().
     * @param string $size Journey size: short, medium or long.
     * @return string[] The expanded, execution-ready step plan.
     */
    protected static function expand_story_arc(array $steptypes, string $size): array {
        $aichapters = max(0, \block_playerhud\local\xp_budget::compute_chapter_count($size) - 1);

        $expanded = [];
        foreach ($steptypes as $type) {
            if ($type !== 'next_chapter') {
                $expanded[] = $type;
                continue;
            }
            if ($aichapters === 0) {
                continue;
            }
            $expanded[] = 'story_outline';
            for ($i = 1; $i <= $aichapters; $i++) {
                $expanded[] = "story_chapter_{$i}";
            }
        }

        return $expanded;
    }

    /**
     * Resolves the localised label for a step type, reusing the same strings the module
     * checkboxes already use in the wizard form. "story_outline"/"story_chapter_N" (the expanded
     * story arc steps) get their own labels instead, since they have no matching checkbox.
     *
     * @param string $type Step type identifier, e.g. 'items'.
     * @return string The localised label.
     */
    protected static function step_label(string $type): string {
        if ($type === 'story_outline') {
            return get_string('wizard_step_story_outline', 'block_playerhud');
        }
        if (preg_match('/^story_chapter_(\d+)$/', $type, $matches) === 1) {
            return get_string('wizard_step_story_chapter', 'block_playerhud', (int) $matches[1]);
        }

        $stringkeybytype = [
            'items' => 'wizard_module_items_ai',
            'missions' => 'wizard_module_missions',
            'playercoin' => 'wizard_module_playercoin',
            'avatars' => 'wizard_module_avatars',
            'rpg' => 'wizard_module_rpg',
            'progress_item' => 'wizard_module_progress_item',
            'next_chapter' => 'wizard_module_next_chapter',
            'pill' => 'wizard_module_pill',
            'latepenalty' => 'wizard_module_latepenalty',
            'comercio' => 'wizard_module_trade',
            'secret_drops' => 'wizard_module_secret',
            'ranking' => 'wizard_module_ranking',
            'auto_distribute' => 'wizard_module_autodistribute',
        ];

        return get_string($stringkeybytype[$type] ?? 'wizard_generating', 'block_playerhud');
    }

    /**
     * Return structure for wizard_start.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'runid' => new external_value(PARAM_INT, 'Wizard run ID'),
            'steps' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Step type identifier'),
                    'label' => new external_value(PARAM_TEXT, 'Localised step label'),
                ]),
                'Ordered plan of steps to run, one at a time'
            ),
            'total' => new external_value(PARAM_INT, 'Total number of steps in the plan'),
            'item_xp_shares' => new external_multiple_structure(
                new external_value(PARAM_INT, 'XP share'),
                'XP to assign to each generated item, in creation order'
            ),
            'mission_xp_shares' => new external_multiple_structure(
                new external_value(PARAM_INT, 'XP share'),
                'XP to assign to each generated mission, in selection order'
            ),
            'pill_bonus_xp' => new external_value(
                PARAM_INT,
                'Reward XP for the Pill trade-completion quest, from the shared budget'
            ),
            'latepenalty_bonus_xp' => new external_value(
                PARAM_INT,
                'Reward XP for the Latepenalty early-win quest, from the shared budget'
            ),
            'has_slow_step' => new external_value(
                PARAM_BOOL,
                'Whether the plan includes an AI story chapter, which can take a while'
            ),
        ]);
    }
}
