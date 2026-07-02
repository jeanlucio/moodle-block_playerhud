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
 * Web service to run the gamification wizard.
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
use context_block;

/**
 * External API that runs the gamification wizard.
 *
 * Covers the Items & Trade module (AI generation), the Missions module (heuristic
 * suggestions derived from existing items/levels), the mechanical PlayerCoin/Avatars
 * modules, and RPG Classes (3 pre-defined archetypes + a fixed Chapter 1 that assigns
 * one to the student — a class can never exist without the story that grants it, see
 * `local\rpg_archetypes`). Later modules (Story chapters 2+...) will be added to this
 * same entry point, each recording its own objects into the same wizard run for a
 * single combined rollback.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard_generate extends external_api {
    /** @var int Number of items generated for the "short journey" size. */
    private const SIZE_SHORT_AMOUNT = 5;

    /** @var int Number of items generated for the "medium journey" size. */
    private const SIZE_MEDIUM_AMOUNT = 10;

    /** @var int Number of items generated for the "long journey" size. */
    private const SIZE_LONG_AMOUNT = 15;

    /** @var int How many progress items a chapter's costed choices ask for, in total. */
    private const CHAPTER_ITEM_COST = 2;

    /**
     * Define parameters for wizard_generate.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'theme' => new external_value(PARAM_TEXT, 'Subject theme'),
            'tone' => new external_value(PARAM_TEXT, 'Narrative tone', VALUE_DEFAULT, ''),
            'size' => new external_value(PARAM_ALPHA, 'Journey size: short, medium or long', VALUE_DEFAULT, 'short'),
            'include_items' => new external_value(PARAM_BOOL, 'Generate the Items & Trade module', VALUE_DEFAULT, true),
            'include_missions' => new external_value(
                PARAM_BOOL,
                'Generate heuristic Mission suggestions',
                VALUE_DEFAULT,
                false
            ),
            'include_playercoin' => new external_value(
                PARAM_BOOL,
                'Create the PlayerCoin item',
                VALUE_DEFAULT,
                false
            ),
            'include_avatars' => new external_value(
                PARAM_BOOL,
                'Create the pre-defined avatar item pack',
                VALUE_DEFAULT,
                false
            ),
            'include_rpg' => new external_value(
                PARAM_BOOL,
                'Create the RPG class pack and the fixed Chapter 1 that assigns one to the student',
                VALUE_DEFAULT,
                false
            ),
            'tone_key' => new external_value(
                PARAM_ALPHA,
                'Narrative tone key for RPG content and the progress item: fantasy, scifi, ' .
                    'mystery or academic',
                VALUE_DEFAULT,
                'fantasy'
            ),
            'include_auto_distribute' => new external_value(
                PARAM_BOOL,
                "Automatically insert this run's generated drops into matching course activities",
                VALUE_DEFAULT,
                false
            ),
            'include_progress_item' => new external_value(
                PARAM_BOOL,
                'Create a themed progress item with an infinite, cooldown-based drop',
                VALUE_DEFAULT,
                false
            ),
            'include_next_chapter' => new external_value(
                PARAM_BOOL,
                'Generate a new AI story chapter that costs the progress item on some choices',
                VALUE_DEFAULT,
                false
            ),
            'include_comercio' => new external_value(
                PARAM_BOOL,
                'Wire PlayerCoin<->Avatar Pack trades from whatever already exists in the instance',
                VALUE_DEFAULT,
                false
            ),
            'include_pill' => new external_value(
                PARAM_BOOL,
                'Create the tone-specific Knowledge Pill and Book items, spread across course activities',
                VALUE_DEFAULT,
                false
            ),
            'include_latepenalty' => new external_value(
                PARAM_BOOL,
                'Create the Deadline Extension item (soft dependency on local_latepenalty)',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Runs the gamification wizard.
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
     * @param bool $includeautodistribute Whether to auto-distribute this run's drops.
     * @param bool $includeprogressitem Whether to create the themed progress item.
     * @param bool $includenextchapter Whether to generate a new AI story chapter.
     * @param bool $includecomercio Whether to wire PlayerCoin<->Avatar Pack trades.
     * @param bool $includepill Whether to create the Knowledge Pill and Book items.
     * @param bool $includelatepenalty Whether to create the Deadline Extension item.
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
        bool $includeautodistribute = false,
        bool $includeprogressitem = false,
        bool $includenextchapter = false,
        bool $includecomercio = false,
        bool $includepill = false,
        bool $includelatepenalty = false
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
            'include_auto_distribute' => $includeautodistribute,
            'include_progress_item' => $includeprogressitem,
            'include_next_chapter' => $includenextchapter,
            'include_comercio' => $includecomercio,
            'include_pill' => $includepill,
            'include_latepenalty' => $includelatepenalty,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        $bi = $DB->get_record('block_instances', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $config = unserialize_object(base64_decode($bi->configdata));
        if (!$config) {
            $config = new \stdClass();
        }

        $modules = [];
        if ($params['include_items']) {
            $modules[] = 'items';
        }
        if ($params['include_missions']) {
            $modules[] = 'missions';
        }
        if ($params['include_playercoin']) {
            $modules[] = 'playercoin';
        }
        if ($params['include_avatars']) {
            $modules[] = 'avatars';
        }
        if ($params['include_rpg']) {
            $modules[] = 'rpg';
        }
        if ($params['include_progress_item']) {
            $modules[] = 'progress_item';
        }
        if ($params['include_next_chapter']) {
            $modules[] = 'next_chapter';
        }
        if ($params['include_comercio']) {
            $modules[] = 'comercio';
        }
        if ($params['include_pill']) {
            $modules[] = 'pill';
        }
        if ($params['include_latepenalty']) {
            $modules[] = 'latepenalty';
        }

        $runid = \block_playerhud\local\wizard::start_run($params['instanceid'], (int) $USER->id, $modules);

        try {
            $createditems = [];
            $createdquests = [];
            $createdtrades = [];
            $createddropids = [];
            $distributemessage = '';

            if ($params['include_items']) {
                $itemsresult = self::generate_items(
                    $params['instanceid'],
                    $config,
                    $params['theme'],
                    $params['tone'],
                    $params['size'],
                    $runid
                );
                $createditems = $itemsresult['names'];
                $createddropids = $itemsresult['drop_ids'];
            }

            if ($params['include_missions']) {
                $createdquests = self::generate_missions(
                    $params['instanceid'],
                    $params['courseid'],
                    $config,
                    $params['size'],
                    $params['tone_key'],
                    $runid
                );
            }

            if ($params['include_playercoin']) {
                $createditems = array_merge(
                    $createditems,
                    self::generate_playercoin($params['instanceid'], $params['courseid'], $runid)
                );
            }

            if ($params['include_avatars']) {
                $createditems = array_merge(
                    $createditems,
                    self::generate_avatars($params['instanceid'], $params['courseid'], $runid)
                );
            }

            if ($params['include_rpg']) {
                $createditems = array_merge(
                    $createditems,
                    self::generate_rpg_classes($params['instanceid'], $params['tone_key'], $runid)
                );
            }

            if ($params['include_progress_item']) {
                $progressresult = self::generate_progress_item($params['instanceid'], $params['tone_key'], $runid);
                $createditems = array_merge($createditems, $progressresult['names']);
                $createddropids = array_merge($createddropids, $progressresult['drop_ids']);
            }

            if ($params['include_next_chapter']) {
                $chapterresult = self::generate_next_chapter(
                    $params['instanceid'],
                    $params['theme'],
                    $params['tone_key'],
                    $runid
                );
                $createditems = array_merge(
                    $createditems,
                    $chapterresult['created_items'],
                    [$chapterresult['chapter_title']]
                );
            }

            if ($params['include_pill']) {
                $createditems = array_merge($createditems, self::generate_pill(
                    $params['instanceid'],
                    $params['courseid'],
                    $params['tone_key'],
                    $runid
                ));
                // The Pill->Book trade (and its quest) is intrinsic to this mechanic, so it is
                // wired here rather than in Comercio — otherwise generating the Pill without also
                // ticking Comercio would leave the Book permanently unobtainable.
                $pilltrade = self::generate_pill_trade($params['instanceid'], $runid);
                if ($pilltrade['trade_name'] !== '') {
                    $createdtrades[] = $pilltrade['trade_name'];
                }
                if ($pilltrade['quest_name'] !== '') {
                    $createdquests[] = $pilltrade['quest_name'];
                }
            }

            if ($params['include_latepenalty']) {
                $lpresult = self::generate_latepenalty($params['instanceid'], $runid);
                $createditems = array_merge($createditems, $lpresult['items']);
                if ($lpresult['quest_name'] !== '') {
                    $createdquests[] = $lpresult['quest_name'];
                }
                // The PlayerCoin->Deadline Extension trade is intrinsic to this mechanic too, so it
                // is wired here (a no-op when PlayerCoin is absent) rather than in Comercio.
                $lptradename = self::generate_latepenalty_trade($params['instanceid'], $runid);
                if ($lptradename !== '') {
                    $createdtrades[] = $lptradename;
                }
            }

            if ($params['include_comercio']) {
                $createdtrades = array_merge(
                    $createdtrades,
                    self::generate_comercio($params['instanceid'], $runid)
                );
            }

            if ($params['include_auto_distribute'] && !empty($createddropids)) {
                $distributemessage = self::distribute_drops(
                    $params['instanceid'],
                    $params['courseid'],
                    $createddropids,
                    $runid
                );
            }

            \block_playerhud\local\wizard::finish_run($runid, 'done');

            return [
                'success' => true,
                'runid' => $runid,
                'message' => '',
                'created_items' => $createditems,
                'created_quests' => $createdquests,
                'created_trades' => $createdtrades,
                'distribute_message' => $distributemessage,
            ];
        } catch (\Exception $e) {
            // A later module (e.g. auto-distribute) can fail after earlier modules already
            // wrote real rows, so always run the real rollback here rather than just labelling
            // the run 'rolledback' — it is a no-op when the manifest is empty (failure before
            // any insert) and a real cleanup otherwise, avoiding orphaned, unrecoverable content.
            \block_playerhud\local\wizard::rollback($runid, $params['instanceid'], $params['courseid']);

            return [
                'success' => false,
                'runid' => $runid,
                'message' => $e->getMessage(),
                'created_items' => [],
                'created_quests' => [],
                'created_trades' => [],
                'distribute_message' => '',
            ];
        }
    }

    /**
     * Generates the Items & Trade module (AI items with drops) and records them in the run.
     *
     * @param int $instanceid Block instance ID.
     * @param \stdClass $config Block configuration.
     * @param string $theme Subject theme.
     * @param string $tone Narrative tone.
     * @param string $size Journey size: short, medium or long.
     * @param int $runid Wizard run ID.
     * @return array{names: string[], drop_ids: int[]} Created item names and drop IDs.
     */
    protected static function generate_items(
        int $instanceid,
        \stdClass $config,
        string $theme,
        string $tone,
        string $size,
        int $runid
    ): array {
        global $DB, $USER;

        $xpperlevel = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int)$config->max_levels : 20;
        $amount = match ($size) {
            'long' => self::SIZE_LONG_AMOUNT,
            'medium' => self::SIZE_MEDIUM_AMOUNT,
            default => self::SIZE_SHORT_AMOUNT,
        };

        $balancecontext = \block_playerhud\local\analytics::balance_context(
            $instanceid,
            $xpperlevel,
            $maxlevels,
            $amount
        );

        // A deterministic per-item share of the remaining XP room, instead of a single random
        // value picked once from a coarse gap-size bucket and applied identically to every item
        // regardless of how many are being generated (see xp_budget's docblock).
        $itemxp = \block_playerhud\local\xp_budget::compute_share($balancecontext['gap'], $amount);

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate(
            'item',
            $theme,
            $itemxp,
            true,
            [
                'tone' => $tone,
                'balance_context' => $balancecontext,
                // A finite drop_max keeps the balanced XP meaningful: the Golden Rule in
                // game.php/collect.php forces XP to 0 on infinite (maxusage=0) drops, so an
                // unset drop_max here would silently make every wizard item worth nothing.
                'drop_max' => 1,
                'drop_time' => 0,
            ],
            $amount
        );

        if (!empty($result['created_item_ids'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', $result['created_item_ids']);
        }
        if (!empty($result['created_drop_ids'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_drops', $result['created_drop_ids']);
        }

        $createditems = $result['created_items'] ?? [];
        if (!empty($createditems)) {
            $logs = [];
            $now = time();
            foreach ($createditems as $itemname) {
                $log = new \stdClass();
                $log->blockinstanceid = $instanceid;
                $log->userid          = $USER->id;
                $log->action_type     = 'item';
                $log->object_name     = $itemname;
                $log->ai_provider     = $result['provider'] ?? 'Unknown';
                $log->timecreated     = $now;
                $logs[] = $log;
            }
            $DB->insert_records('block_playerhud_ai_logs', $logs);
        }

        return [
            'names' => $createditems,
            'drop_ids' => $result['created_drop_ids'] ?? [],
        ];
    }

    /**
     * Generates heuristic Mission suggestions and records them in the run.
     *
     * Level, collection and activity-completion milestones are the candidates. Unlike a manual
     * "Suggest Missions" run (which creates every candidate the teacher leaves ticked), the
     * wizard limits itself to a journey-sized subset, round-robining across the candidate types
     * so a course with many completion-enabled activities cannot crowd out level or collection
     * milestones. Each selected mission's name is re-flavoured for the chosen tone, and its
     * reward_xp is overridden to an even share of the XP room still left after any items this
     * run (or a previous one) already created — see `xp_budget`.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param \stdClass $config Block configuration.
     * @param string $size Journey size: short, medium or long.
     * @param string $tonekey Narrative tone key.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created quests.
     */
    protected static function generate_missions(
        int $instanceid,
        int $courseid,
        \stdClass $config,
        string $size,
        string $tonekey,
        int $runid
    ): array {
        global $DB;

        $xpperlevel = isset($config->xp_per_level) ? (int) $config->xp_per_level : 100;
        $maxlevels = isset($config->max_levels) ? (int) $config->max_levels : 20;

        $allowedtypes = [
            \block_playerhud\quest::TYPE_LEVEL,
            \block_playerhud\quest::TYPE_UNIQUE_ITEMS,
            \block_playerhud\quest::TYPE_ACTIVITY,
        ];
        $suggestions = \block_playerhud\quest::get_heuristic_suggestions($instanceid, $courseid, $config);
        $suggestions = array_values(array_filter(
            $suggestions,
            static fn(array $suggestion): bool => in_array($suggestion['type'], $allowedtypes, true)
        ));

        $limit = \block_playerhud\local\xp_budget::compute_mission_count($size);
        $selected = \block_playerhud\local\xp_budget::select_balanced_missions($suggestions, $limit);

        // Economy_health() (unlike balance_context(), used for items) also accounts for existing
        // quest reward_xp, so missions never overshoot the ceiling on top of quests already there.
        $health = \block_playerhud\local\analytics::economy_health($instanceid, $xpperlevel, $maxlevels);
        $gap = max(0, $health->xp_ceiling - $health->total_items_xp);
        $missionxp = \block_playerhud\local\xp_budget::compute_share($gap, count($selected));

        $createdquests = [];
        $createdids = [];

        foreach ($selected as $suggestion) {
            $suggestion['name'] = self::resolve_mission_name($suggestion, $courseid, $tonekey);
            $record = \block_playerhud\quest::build_record_from_suggestion($instanceid, $suggestion, $missionxp);
            $questid = (int) $DB->insert_record('block_playerhud_quests', $record);
            $createdids[] = $questid;
            $createdquests[] = $record->name;
        }

        if (!empty($createdids)) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_quests', $createdids);
        }

        return $createdquests;
    }

    /**
     * Creates the PlayerCoin item, auto-distributes its drop into the course news forum
     * and records both in the run.
     *
     * The drop is only attempted right after the item itself was just created — PlayerCoin
     * already existing means a previous run (or the manual Items tab) already decided whether
     * to set up the forum drop, so this never risks inserting a second one on a rerun. A
     * missing news forum is a tolerated no-op, same as the manual flow in the Items tab.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created items (empty if PlayerCoin already existed).
     */
    protected static function generate_playercoin(int $instanceid, int $courseid, int $runid): array {
        $result = \block_playerhud\external\create_playercoin::execute($instanceid, $courseid);

        if (empty($result['created'])) {
            return [];
        }

        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', [$result['itemid']]);

        $dropresult = \block_playerhud\external\setup_playercoin_drop::execute($instanceid, $courseid, $result['itemid']);
        if (!empty($dropresult['success']) && !empty($dropresult['dropid'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_drops', [$dropresult['dropid']]);
            \block_playerhud\local\wizard::record_shortcode($runid, $dropresult['dropid'], $dropresult['cmid'], 'intro');
        }

        return ['PlayerCoin'];
    }

    /**
     * Creates the pre-defined avatar item pack and records the new items in the run.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created avatar items.
     */
    protected static function generate_avatars(int $instanceid, int $courseid, int $runid): array {
        $result = \block_playerhud\external\create_avatar_pack::execute($instanceid, $courseid);

        if (!empty($result['created_item_ids'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', $result['created_item_ids']);
        }

        return $result['created_item_names'] ?? [];
    }

    /**
     * Wires PlayerCoin<->Avatar Pack trades via the existing heuristic suggestion engine
     * (`game::build_trade_suggestions()`) and records each created trade, requirement and
     * reward row in the run.
     *
     * Works off whatever PlayerCoin and avatar items already exist in the instance, not just
     * ones created in this same run — same philosophy as the Missions module. A no-op when
     * either is missing, or when every suggestion is already covered by an existing trade.
     *
     * @param int $instanceid Block instance ID.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created trades.
     */
    protected static function generate_comercio(int $instanceid, int $runid): array {
        $suggestions = \block_playerhud\game::build_trade_suggestions($instanceid);

        $createdtrades = [];
        foreach ($suggestions as $suggestion) {
            $result = \block_playerhud\game::create_trade_from_suggestion($instanceid, $suggestion);
            \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trades', $result['tradeid']);
            \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trade_reqs', $result['reqid']);
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_trade_rewards', $result['rewardids']);
            $createdtrades[] = $suggestion['name'];
        }

        return $createdtrades;
    }

    /** @var int Knowledge Pill quantity the Book trade costs — always leaves exactly 1 spare. */
    private const PILL_TRADE_COST = 10;

    /**
     * Wires the Pill<->Book trade (a fixed 1-item recipe, not a heuristic suggestion) once both
     * items exist, and creates the "earned the exclusive trade" quest.
     *
     * The quest uses TYPE_SPECIFIC_TRADE, which counts permanent `block_playerhud_trade_log`
     * entries rather than current item holdings — unlike the collector quest in generate_pill(),
     * it can never be lost by spending the reward afterwards. That asymmetry is exactly why the
     * Pill total (PILL_TARGET = 11) always leaves 1 spare after this trade's cost (10): the
     * collector quest DOES check current holdings, so trading away the last Pill before claiming
     * it would fail the re-verification in quest::claim_reward().
     *
     * Idempotent: skipped once any trade already rewards the Book item.
     *
     * @param int $instanceid Block instance ID.
     * @param int $runid Wizard run ID.
     * @return array{trade_name: string, quest_name: string} Empty strings when skipped (Pill or
     *     Book missing, or the trade already exists).
     */
    protected static function generate_pill_trade(int $instanceid, int $runid): array {
        global $DB;

        $pill = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type' => 'knowledge_pill',
        ]);
        $book = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type' => 'knowledge_book',
        ]);
        if (!$pill || !$book) {
            return ['trade_name' => '', 'quest_name' => ''];
        }

        $alreadywired = $DB->record_exists_sql(
            "SELECT 1
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid AND tr.itemid = :bookid",
            ['iid' => $instanceid, 'bookid' => $book->id]
        );
        if ($alreadywired) {
            return ['trade_name' => '', 'quest_name' => ''];
        }

        $tradename = format_string($book->name);
        $suggestion = [
            'name' => $tradename,
            'cost_itemid' => $pill->id,
            'cost_qty' => self::PILL_TRADE_COST,
            'rewards' => [['id' => $book->id, 'qty' => 1]],
        ];
        $result = \block_playerhud\game::create_trade_from_suggestion($instanceid, $suggestion);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trades', $result['tradeid']);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trade_reqs', $result['reqid']);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_trade_rewards', $result['rewardids']);

        $questsuggestion = [
            'type' => \block_playerhud\quest::TYPE_SPECIFIC_TRADE,
            'requirement' => 1,
            'req_itemid' => $result['tradeid'],
            'name' => get_string('wizard_book_quest_name', 'block_playerhud', $tradename),
            'reward_xp' => 150,
            'image_todo' => $book->image,
            'image_done' => $book->image,
        ];
        $questrecord = \block_playerhud\quest::build_record_from_suggestion($instanceid, $questsuggestion);
        $questid = (int) $DB->insert_record('block_playerhud_quests', $questrecord);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_quests', $questid);

        return ['trade_name' => $tradename, 'quest_name' => $questrecord->name];
    }

    /**
     * Creates the RPG class pack and, unless it already exists, the fixed opening chapter
     * that assigns one of the 3 archetypes to the student.
     *
     * A class can only ever be assigned through a story choice (`set_class_id`), so classes
     * and Chapter 1 are always created together — there is deliberately no way to end up
     * with a class the student cannot obtain. Chapter 1 itself is a fixed, tone-specific
     * skeleton (`local\rpg_archetypes`), never AI-generated: the class-selection moment is
     * too important to depend on AI output quality. Idempotent per tone: if a chapter with
     * this tone's title already exists for the instance, the whole module is skipped.
     *
     * @param int $instanceid Block instance ID.
     * @param string $tonekey Narrative tone key.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created classes, plus the chapter title if it was created.
     */
    protected static function generate_rpg_classes(int $instanceid, string $tonekey, int $runid): array {
        global $DB;

        $pack = \block_playerhud\local\rpg_archetypes::get_pack($tonekey);

        $classresult = \block_playerhud\external\create_class_pack::execute($instanceid, 0, $tonekey);
        if (!empty($classresult['created_class_ids'])) {
            \block_playerhud\local\wizard::record_objects(
                $runid,
                'block_playerhud_classes',
                $classresult['created_class_ids']
            );
        }
        $createditems = $classresult['created_class_names'] ?? [];

        // Chapter 1 already exists for this tone (e.g. the module was run before): the class
        // pack call above is idempotent by name, so nothing more to do here.
        $chaptertitle = $pack['chapter_title'];
        $chapterconditions = ['blockinstanceid' => $instanceid, 'title' => $chaptertitle];
        if ($DB->record_exists('block_playerhud_chapters', $chapterconditions)) {
            return $createditems;
        }

        // Resolve role => classid for every archetype in the pack, covering both classes
        // created just above and ones that already existed, so set_class_id always resolves.
        $namesbyrole = array_column($pack['classes'], 'name', 'role');
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($namesbyrole), SQL_PARAMS_NAMED);
        $inparams['instanceid'] = $instanceid;
        $classidsbyname = $DB->get_records_select(
            'block_playerhud_classes',
            "blockinstanceid = :instanceid AND name $insql",
            $inparams,
            '',
            'name, id'
        );
        $classidsbyrole = [];
        foreach ($namesbyrole as $role => $name) {
            $classidsbyrole[$role] = isset($classidsbyname[$name]) ? (int) $classidsbyname[$name]->id : 0;
        }

        $transaction = $DB->start_delegated_transaction();

        $chapter = new \stdClass();
        $chapter->blockinstanceid = $instanceid;
        $chapter->title = $chaptertitle;
        $chapter->intro_text = '';
        $chapter->unlock_date = 0;
        $chapter->required_level = 0;
        $chapter->sortorder = $DB->count_records('block_playerhud_chapters', ['blockinstanceid' => $instanceid]) + 1;
        $chapterid = (int) $DB->insert_record('block_playerhud_chapters', $chapter);

        $nodeids = [];
        foreach ($pack['nodes'] as $index => $nodedata) {
            $node = new \stdClass();
            $node->chapterid = $chapterid;
            $node->content = $nodedata['content'];
            $node->is_start = $nodedata['is_start'] ? 1 : 0;
            $nodeids[$index] = (int) $DB->insert_record('block_playerhud_story_nodes', $node);
        }

        $choiceids = [];
        foreach ($pack['nodes'] as $index => $nodedata) {
            foreach ($nodedata['choices'] as $choicedata) {
                $choice = new \stdClass();
                $choice->nodeid = $nodeids[$index];
                $choice->text = $choicedata['text'];
                $choice->next_nodeid = $nodeids[$choicedata['target']];
                $choice->req_class_id = 0;
                $choice->req_karma_min = 0;
                $choice->karma_delta = $choicedata['karma_delta'] ?? 0;
                $choice->set_class_id = isset($choicedata['class_role'])
                    ? $classidsbyrole[$choicedata['class_role']]
                    : 0;
                $choice->cost_itemid = 0;
                $choice->cost_item_qty = 1;
                $choiceids[] = (int) $DB->insert_record('block_playerhud_choices', $choice);
            }
        }

        $transaction->allow_commit();

        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_chapters', [$chapterid]);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_story_nodes', array_values($nodeids));
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_choices', $choiceids);

        $createditems[] = $chaptertitle;

        return $createditems;
    }

    /**
     * Creates a themed progress item with an infinite, cooldown-based drop.
     *
     * A plain "item" like PlayerCoin, not tied to RPG Classes: it can be generated whether or
     * not the RPG module runs in the same call. Its intended use (as a `cost_itemid` in future
     * AI-generated story chapters, via the already-existing `story_manager.php` cost mechanic)
     * is not implemented yet — this only creates the item and its drop.
     *
     * `maxusage=0` (infinite) already forces XP to 0 on collection via the Golden Rule in
     * `game.php`/`collect.php`, but `xp` is set to 0 explicitly here too for clarity: this
     * item's value is progression, not experience points.
     *
     * @param int $instanceid Block instance ID.
     * @param string $tonekey Narrative tone key.
     * @param int $runid Wizard run ID.
     * @return array{names: string[], drop_ids: int[]} Empty when the item already existed.
     */
    protected static function generate_progress_item(int $instanceid, string $tonekey, int $runid): array {
        global $DB;

        $emojibytone = [
            'fantasy' => "\u{1F48E}",
            'scifi' => "\u{1F50B}",
            'mystery' => "\u{1F9E9}",
            'academic' => "\u{1F4D1}",
        ];
        $emoji = $emojibytone[$tonekey] ?? $emojibytone['fantasy'];
        $name = self::resolve_progress_item_name($tonekey);

        if ($DB->record_exists('block_playerhud_items', ['blockinstanceid' => $instanceid, 'name' => $name])) {
            return ['names' => [], 'drop_ids' => []];
        }

        $now = time();
        $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name' => $name,
            'image' => $emoji,
            'description' => get_string('wizard_progress_item_desc_text', 'block_playerhud'),
            'xp' => 0,
            'enabled' => 1,
            'tradable' => 0,
            'secret' => 0,
            'required_class_id' => '0',
            'action_type' => '',
            'action_value' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
            'blockinstanceid' => $instanceid,
            'itemid' => $itemid,
            'name' => $name,
            'maxusage' => 0,
            'respawntime' => 3600,
            'code' => \block_playerhud\utils::generate_drop_code($instanceid),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_drops', [$dropid]);

        return ['names' => [$name], 'drop_ids' => [$dropid]];
    }

    /**
     * Resolves the tone-specific progress item name, falling back to the Fantasy name for an
     * unrecognised tone key.
     *
     * @param string $tonekey Narrative tone key.
     * @return string The item name.
     */
    protected static function resolve_progress_item_name(string $tonekey): string {
        $namestringkey = "wizard_progress_item_name_$tonekey";
        if (!get_string_manager()->string_exists($namestringkey, 'block_playerhud')) {
            $namestringkey = 'wizard_progress_item_name_fantasy';
        }

        return get_string($namestringkey, 'block_playerhud');
    }

    /** @var int Total Knowledge Pill quantity across the whole course, whatever its size. */
    private const PILL_TARGET = 11;

    /**
     * Creates the Knowledge Pill and Book items (paired, tone-specific names/emoji) and spreads
     * the Pill's drops across course activities via
     * {@see \block_playerhud\local\drop_distribution::compute_pill_quotas()} — so the total
     * collectible across the whole course is always exactly PILL_TARGET regardless of how many
     * activities exist.
     *
     * Deliberately does not create a "collect all PILL_TARGET" quest: the Pill<->Book trade
     * (see generate_pill_trade()) spends 10 of the 11, and any quest requiring simultaneous
     * possession of all 11 would become permanently unwinnable the moment a student trades —
     * the total ever obtainable is capped at exactly 11, so once spent there is no way to hold
     * 11 again. The "earned the exclusive trade" quest (TYPE_SPECIFIC_TRADE, in
     * generate_pill_trade()) is the safe way to reward the Pill/Book loop, since it counts a
     * permanent trade_log entry rather than current holdings.
     *
     * Idempotent per tone: if a Pill with this tone's name already exists, the whole module is
     * skipped, mirroring the progress item's own idempotency check. A course with no eligible
     * activities yet still gets the items — same tolerant behaviour as the general
     * auto-distribute step.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $tonekey Narrative tone key.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created items (empty when the module was skipped as
     *     already done).
     */
    protected static function generate_pill(int $instanceid, int $courseid, string $tonekey, int $runid): array {
        global $DB;

        $pillname = self::resolve_pill_name($tonekey);
        if ($DB->record_exists('block_playerhud_items', ['blockinstanceid' => $instanceid, 'name' => $pillname])) {
            return [];
        }

        $bookname = self::resolve_book_name($tonekey);
        $pillemoji = self::pill_emoji($tonekey);
        $bookemoji = self::book_emoji($tonekey);
        $now = time();

        $pillid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name' => $pillname,
            'image' => $pillemoji,
            'description' => get_string('wizard_pill_desc_text', 'block_playerhud'),
            'xp' => 0,
            'enabled' => 1,
            'tradable' => 0,
            'secret' => 0,
            'required_class_id' => '0',
            'action_type' => 'knowledge_pill',
            'action_value' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $bookid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name' => $bookname,
            'image' => $bookemoji,
            'description' => get_string('wizard_book_desc_text', 'block_playerhud'),
            'xp' => 0,
            'enabled' => 1,
            'tradable' => 0,
            'secret' => 0,
            'required_class_id' => '0',
            'action_type' => 'knowledge_book',
            'action_value' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', [$pillid, $bookid]);

        $modules = \block_playerhud\local\drop_distribution::get_eligible_modules($courseid);
        $quotas = \block_playerhud\local\drop_distribution::compute_pill_quotas(self::PILL_TARGET, count($modules));

        $dropids = [];
        foreach ($quotas as $i => $quota) {
            $dropid = (int) $DB->insert_record('block_playerhud_drops', (object) [
                'blockinstanceid' => $instanceid,
                'itemid' => $pillid,
                'name' => $pillname,
                'maxusage' => $quota,
                'respawntime' => 3600,
                'code' => \block_playerhud\utils::generate_drop_code($instanceid),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $dropids[] = $dropid;

            // Page modules never show their intro unless the teacher explicitly enabled
            // "Display page description" — their content, in contrast, is always shown on the
            // page's own view. Every other eligible module type shows its intro unconditionally
            // on its own view page, regardless of the course-page "show description" setting.
            $field = $modules[$i]['supports_content'] ? 'content' : 'intro';
            $result = \block_playerhud\external\insert_drop_shortcode::execute(
                $instanceid,
                $courseid,
                $dropid,
                $modules[$i]['cmid'],
                $field,
                'top'
            );
            if ($result['success']) {
                \block_playerhud\local\wizard::record_shortcode($runid, $dropid, (int) $modules[$i]['cmid'], $field);
            }
        }
        if (!empty($dropids)) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_drops', $dropids);
        }

        return [$pillname, $bookname];
    }

    /** @var int Days a Deadline Extension item pushes the target activity's deadline by. */
    private const LATEPENALTY_EXTENSION_DAYS = 2;

    /** @var int Level requirement for the Deadline Extension "early win" milestone quest. */
    private const LATEPENALTY_QUEST_LEVEL = 2;

    /**
     * Creates the Deadline Extension item (soft dependency on local_latepenalty, same
     * action_type='deadline_extension' the manual Items form already supports) and an "early
     * win" milestone quest that hands it out as a reward alongside XP.
     *
     * Not tone-specific — reuses the existing item_power_deadline string, since this is a
     * utility tool rather than narrative flavour. cmid is left at 0 so the student picks which
     * LP-eligible activity to extend when they use it, same as the manual form's "Any" option.
     *
     * A no-op when local_latepenalty is not installed (re-checked here even though the wizard
     * UI already hides this module's checkbox in that case — never trust the client alone for a
     * server-side decision). Tolerant when the course has no LP-eligible activity yet: the item
     * and quest are still created, simply unusable until the teacher configures an LP rule.
     *
     * @param int $instanceid Block instance ID.
     * @param int $runid Wizard run ID.
     * @return array{items: string[], quest_name: string} Empty when LP isn't installed or the
     *     item already exists.
     */
    protected static function generate_latepenalty(int $instanceid, int $runid): array {
        global $DB;

        if (!class_exists('\local_latepenalty\recalculator')) {
            return ['items' => [], 'quest_name' => ''];
        }

        $itemname = get_string('item_power_deadline', 'block_playerhud');
        if ($DB->record_exists('block_playerhud_items', ['blockinstanceid' => $instanceid, 'name' => $itemname])) {
            return ['items' => [], 'quest_name' => ''];
        }

        $now = time();
        $itemid = (int) $DB->insert_record('block_playerhud_items', (object) [
            'blockinstanceid' => $instanceid,
            'name' => $itemname,
            'image' => "\u{23F3}",
            'description' => get_string('wizard_latepenalty_desc_text', 'block_playerhud'),
            'xp' => 0,
            'enabled' => 1,
            'tradable' => 0,
            'secret' => 0,
            'required_class_id' => '0',
            'action_type' => 'deadline_extension',
            'action_value' => json_encode(['days' => self::LATEPENALTY_EXTENSION_DAYS, 'cmid' => 0]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_items', [$itemid]);

        $questsuggestion = [
            'type' => \block_playerhud\quest::TYPE_LEVEL,
            'requirement' => self::LATEPENALTY_QUEST_LEVEL,
            'reward_itemid' => $itemid,
            'name' => get_string('wizard_latepenalty_quest_name', 'block_playerhud', self::LATEPENALTY_QUEST_LEVEL),
            'reward_xp' => self::LATEPENALTY_QUEST_LEVEL * 20,
            'image_todo' => "\u{1F4C8}",
            'image_done' => "\u{23F3}",
        ];
        $questrecord = \block_playerhud\quest::build_record_from_suggestion($instanceid, $questsuggestion);
        $questid = (int) $DB->insert_record('block_playerhud_quests', $questrecord);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_quests', $questid);

        return ['items' => [$itemname], 'quest_name' => $questrecord->name];
    }

    /** @var int PlayerCoin quantity the Deadline Extension trade costs. */
    private const LATEPENALTY_TRADE_COST = 20;

    /**
     * Wires the PlayerCoin<->Deadline Extension trade once both items exist.
     *
     * @param int $instanceid Block instance ID.
     * @param int $runid Wizard run ID.
     * @return string Name of the created trade, or an empty string when skipped (PlayerCoin or
     *     the Deadline Extension item missing, or the trade already exists).
     */
    protected static function generate_latepenalty_trade(int $instanceid, int $runid): string {
        global $DB;

        $coin = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type' => 'playercoin',
        ]);
        $item = $DB->get_record('block_playerhud_items', [
            'blockinstanceid' => $instanceid,
            'action_type' => 'deadline_extension',
        ]);
        if (!$coin || !$item) {
            return '';
        }

        $alreadywired = $DB->record_exists_sql(
            "SELECT 1
               FROM {block_playerhud_trade_rewards} tr
               JOIN {block_playerhud_trades} t ON t.id = tr.tradeid
              WHERE t.blockinstanceid = :iid AND tr.itemid = :itemid",
            ['iid' => $instanceid, 'itemid' => $item->id]
        );
        if ($alreadywired) {
            return '';
        }

        $tradename = format_string($item->name);
        $suggestion = [
            'name' => $tradename,
            'cost_itemid' => $coin->id,
            'cost_qty' => self::LATEPENALTY_TRADE_COST,
            'rewards' => [['id' => $item->id, 'qty' => 1]],
        ];
        $result = \block_playerhud\game::create_trade_from_suggestion($instanceid, $suggestion);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trades', $result['tradeid']);
        \block_playerhud\local\wizard::record_object($runid, 'block_playerhud_trade_reqs', $result['reqid']);
        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_trade_rewards', $result['rewardids']);

        return $tradename;
    }

    /**
     * Resolves a tone-flavoured name for a heuristic mission suggestion, falling back to the
     * Fantasy tone's phrasing for an unrecognised tone key, and to the suggestion's own
     * generic name for an unsupported quest type or a since-removed activity.
     *
     * @param array $suggestion A suggestion from quest::get_heuristic_suggestions().
     * @param int $courseid Course ID, to resolve an activity's display name for TYPE_ACTIVITY.
     * @param string $tonekey Narrative tone key.
     * @return string The tone-flavoured mission name.
     */
    protected static function resolve_mission_name(array $suggestion, int $courseid, string $tonekey): string {
        $typekey = match ((int) $suggestion['type']) {
            \block_playerhud\quest::TYPE_LEVEL => 'level',
            \block_playerhud\quest::TYPE_UNIQUE_ITEMS => 'unique',
            \block_playerhud\quest::TYPE_ACTIVITY => 'activity',
            default => '',
        };
        if ($typekey === '') {
            return $suggestion['name'];
        }

        $placeholder = $suggestion['requirement'];
        if ($typekey === 'activity') {
            $modinfo = get_fast_modinfo($courseid);
            $cmid = (int) $suggestion['requirement'];
            if (!isset($modinfo->cms[$cmid])) {
                return $suggestion['name'];
            }
            $placeholder = format_string($modinfo->get_cm($cmid)->name);
        }

        $namestringkey = "wizard_mission_name_{$typekey}_{$tonekey}";
        if (!get_string_manager()->string_exists($namestringkey, 'block_playerhud')) {
            $namestringkey = "wizard_mission_name_{$typekey}_fantasy";
        }

        return get_string($namestringkey, 'block_playerhud', $placeholder);
    }

    /**
     * Resolves the tone-specific Knowledge Pill name, falling back to the Fantasy name for an
     * unrecognised tone key.
     *
     * @param string $tonekey Narrative tone key.
     * @return string The item name.
     */
    protected static function resolve_pill_name(string $tonekey): string {
        $namestringkey = "wizard_pill_name_$tonekey";
        if (!get_string_manager()->string_exists($namestringkey, 'block_playerhud')) {
            $namestringkey = 'wizard_pill_name_fantasy';
        }

        return get_string($namestringkey, 'block_playerhud');
    }

    /**
     * Resolves the tone-specific Book name, falling back to the Fantasy name for an
     * unrecognised tone key.
     *
     * @param string $tonekey Narrative tone key.
     * @return string The item name.
     */
    protected static function resolve_book_name(string $tonekey): string {
        $namestringkey = "wizard_book_name_$tonekey";
        if (!get_string_manager()->string_exists($namestringkey, 'block_playerhud')) {
            $namestringkey = 'wizard_book_name_fantasy';
        }

        return get_string($namestringkey, 'block_playerhud');
    }

    /**
     * Returns the Knowledge Pill emoji for a tone.
     *
     * @param string $tonekey The tone key.
     * @return string The emoji character.
     */
    protected static function pill_emoji(string $tonekey): string {
        $map = [
            'fantasy' => "\u{1F9EA}",
            'scifi' => "\u{1F489}",
            'mystery' => "\u{1F4F0}",
            'academic' => "\u{1F48A}",
        ];

        return $map[$tonekey] ?? $map['fantasy'];
    }

    /**
     * Returns the Book emoji for a tone.
     *
     * @param string $tonekey The tone key.
     * @return string The emoji character.
     */
    protected static function book_emoji(string $tonekey): string {
        $map = [
            'fantasy' => "\u{1F4D6}",
            'scifi' => "\u{1F4BE}",
            'mystery' => "\u{1F5C2}\u{FE0F}",
            'academic' => "\u{1F4DA}",
        ];

        return $map[$tonekey] ?? $map['fantasy'];
    }

    /**
     * Generates a new AI story chapter, costing the progress item on some of its choices.
     *
     * Ensures the progress item exists first (creating it if this is the first module in this
     * instance to need it) — a story chapter with no cost item defeats the point of it. Logs to
     * `block_playerhud_ai_logs` like every other AI generation entry point in this plugin.
     *
     * @param int $instanceid Block instance ID.
     * @param string $theme Subject theme.
     * @param string $tonekey Narrative tone key.
     * @param int $runid Wizard run ID.
     * @return array{created_items: string[], chapter_title: string} Progress item names created
     *     as a side effect (empty if it already existed) and the new chapter's title.
     */
    protected static function generate_next_chapter(int $instanceid, string $theme, string $tonekey, int $runid): array {
        global $DB, $USER;

        $itemname = self::resolve_progress_item_name($tonekey);
        $item = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $instanceid, 'name' => $itemname]);

        $createditems = [];
        if (!$item) {
            $progressresult = self::generate_progress_item($instanceid, $tonekey, $runid);
            $createditems = $progressresult['names'];
            $itemconditions = ['blockinstanceid' => $instanceid, 'name' => $itemname];
            $item = $DB->get_record('block_playerhud_items', $itemconditions, '*', MUST_EXIST);
        }

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate_story($theme, [
            'item_id' => (int) $item->id,
            'item_qty' => self::CHAPTER_ITEM_COST,
        ]);

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = $USER->id;
        $log->action_type     = 'story';
        $log->object_name     = $result['chapter_title'];
        $log->ai_provider     = $result['provider'] ?? 'Unknown';
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log);

        \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_chapters', [$result['chapter_id']]);
        if (!empty($result['node_ids'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_story_nodes', $result['node_ids']);
        }
        if (!empty($result['choice_ids'])) {
            \block_playerhud\local\wizard::record_objects($runid, 'block_playerhud_choices', $result['choice_ids']);
        }

        return ['created_items' => $createditems, 'chapter_title' => $result['chapter_title']];
    }

    /**
     * Auto-distributes this run's newly created drops into the course's best-matching
     * activities, when the course already has any eligible for a shortcode.
     *
     * Deliberately scoped to only the drops this run just created — not a general "fix
     * every undistributed drop" sweep. If the course has no eligible activities yet, this
     * is a no-op that returns a message telling the teacher to come back later; the manual
     * distribution screen (`tab_items::render_distribute_view()`) always remains available.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param int[] $dropids Drop IDs created earlier in this same run.
     * @param int $runid Wizard run ID, so each inserted shortcode can be recorded for rollback.
     * @return string Empty on success/no-op, or a message explaining why nothing was inserted.
     */
    protected static function distribute_drops(int $instanceid, int $courseid, array $dropids, int $runid): string {
        global $DB;

        $modules = \block_playerhud\local\drop_distribution::get_eligible_modules($courseid);
        if (empty($modules)) {
            return get_string('wizard_distribute_no_activities', 'block_playerhud');
        }

        [$insql, $inparams] = $DB->get_in_or_equal($dropids, SQL_PARAMS_NAMED);
        $sql = "SELECT d.id, d.code, d.name AS drop_name, i.name AS item_name
                  FROM {block_playerhud_drops} d
                  JOIN {block_playerhud_items} i ON d.itemid = i.id
                 WHERE d.id $insql";
        $drops = $DB->get_records_sql($sql, $inparams);

        foreach ($drops as $drop) {
            $haystack = $drop->drop_name . ' ' . $drop->item_name;
            $suggested = \block_playerhud\local\drop_distribution::suggest_module($haystack, $modules);
            if (!$suggested) {
                continue;
            }
            // Page modules never show their intro unless the teacher explicitly enabled
            // "Display page description" — their content, in contrast, is always shown on the
            // page's own view. Every other eligible module type shows its intro unconditionally
            // on its own view page, regardless of the course-page "show description" setting.
            $field = $suggested['supports_content'] ? 'content' : 'intro';
            $result = \block_playerhud\external\insert_drop_shortcode::execute(
                $instanceid,
                $courseid,
                $drop->id,
                $suggested['cmid'],
                $field,
                'top'
            );
            if ($result['success']) {
                \block_playerhud\local\wizard::record_shortcode($runid, (int) $drop->id, (int) $suggested['cmid'], $field);
            }
        }

        return '';
    }

    /**
     * Return structure for wizard_generate.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'runid' => new external_value(PARAM_INT, 'Wizard run ID, for rollback'),
            'message' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
            'created_items' => new \core_external\external_multiple_structure(
                new external_value(PARAM_TEXT, 'Item name'),
                'List of created items',
                VALUE_OPTIONAL
            ),
            'created_quests' => new \core_external\external_multiple_structure(
                new external_value(PARAM_TEXT, 'Quest name'),
                'List of created quests',
                VALUE_OPTIONAL
            ),
            'created_trades' => new \core_external\external_multiple_structure(
                new external_value(PARAM_TEXT, 'Trade name'),
                'List of created trades',
                VALUE_OPTIONAL
            ),
            'distribute_message' => new external_value(
                PARAM_TEXT,
                'Note about drop auto-distribution, empty when nothing to report',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
