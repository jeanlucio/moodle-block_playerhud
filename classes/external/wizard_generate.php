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
            'size' => new external_value(PARAM_ALPHA, 'Journey size: short or long', VALUE_DEFAULT, 'short'),
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
        ]);
    }

    /**
     * Runs the gamification wizard.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $theme Subject theme.
     * @param string $tone Narrative tone.
     * @param string $size Journey size: short or long.
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
        bool $includecomercio = false
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

            if ($params['include_comercio']) {
                $createdtrades = self::generate_comercio($params['instanceid'], $runid);
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
     * @param string $size Journey size: short or long.
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
        $amount = ($size === 'long') ? self::SIZE_LONG_AMOUNT : self::SIZE_SHORT_AMOUNT;

        $balancecontext = \block_playerhud\local\analytics::balance_context(
            $instanceid,
            $xpperlevel,
            $maxlevels,
            $amount
        );

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate(
            'item',
            $theme,
            -1,
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
     * Only level and collection milestones are used: they depend solely on the
     * block configuration and the item pool, never on course modules or trades
     * that the wizard has no guarantee exist yet.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param \stdClass $config Block configuration.
     * @param int $runid Wizard run ID.
     * @return string[] Names of the created quests.
     */
    protected static function generate_missions(int $instanceid, int $courseid, \stdClass $config, int $runid): array {
        global $DB;

        $allowedtypes = [\block_playerhud\quest::TYPE_LEVEL, \block_playerhud\quest::TYPE_UNIQUE_ITEMS];
        $suggestions = \block_playerhud\quest::get_heuristic_suggestions($instanceid, $courseid, $config);

        $createdquests = [];
        $createdids = [];

        foreach ($suggestions as $suggestion) {
            if (!in_array($suggestion['type'], $allowedtypes, true)) {
                continue;
            }
            $record = \block_playerhud\quest::build_record_from_suggestion($instanceid, $suggestion);
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
            $result = \block_playerhud\external\insert_drop_shortcode::execute(
                $instanceid,
                $courseid,
                $drop->id,
                $suggested['cmid'],
                'intro',
                'top'
            );
            if ($result['success']) {
                \block_playerhud\local\wizard::record_shortcode($runid, (int) $drop->id, (int) $suggested['cmid'], 'intro');
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
