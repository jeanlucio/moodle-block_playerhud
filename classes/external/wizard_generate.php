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
                'Narrative tone key for RPG content: fantasy, scifi, mystery or academic',
                VALUE_DEFAULT,
                'fantasy'
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
     * @param string $tonekey Narrative tone key for RPG content.
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
        string $tonekey = 'fantasy'
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

        $runid = \block_playerhud\local\wizard::start_run($params['instanceid'], (int) $USER->id, $modules);

        try {
            $createditems = [];
            $createdquests = [];

            if ($params['include_items']) {
                $createditems = self::generate_items(
                    $params['instanceid'],
                    $config,
                    $params['theme'],
                    $params['tone'],
                    $params['size'],
                    $runid
                );
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

            \block_playerhud\local\wizard::finish_run($runid, 'done');

            return [
                'success' => true,
                'runid' => $runid,
                'message' => '',
                'created_items' => $createditems,
                'created_quests' => $createdquests,
            ];
        } catch (\Exception $e) {
            // Generation failures happen before any object is saved, so the run leaves
            // nothing to roll back; 'rolledback' accurately reflects that end state.
            \block_playerhud\local\wizard::finish_run($runid, 'rolledback');

            return [
                'success' => false,
                'runid' => $runid,
                'message' => $e->getMessage(),
                'created_items' => [],
                'created_quests' => [],
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
     * @return string[] Names of the created items.
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

        return $createditems;
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
     * Creates the PlayerCoin item and records it in the run.
     *
     * Only the item itself is created here — the optional drop into the course news forum
     * (`external\setup_playercoin_drop`) writes into course content that the generic
     * table/id rollback manifest cannot undo, so it is left as the existing manual follow-up
     * action in the Items tab rather than something the wizard does automatically.
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
        ]);
    }
}
