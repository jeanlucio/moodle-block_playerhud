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
 * Gamification wizard run manifest and rollback for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Tracks what the gamification wizard generates so a run can be undone later.
 *
 * Every object the wizard creates (item, drop, quest, class...) is recorded against
 * the run that created it. Rollback deletes exactly those objects, regardless of
 * which tables they belong to.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard {
    /**
     * Starts a new wizard run.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $userid The teacher running the wizard.
     * @param array $modules The mechanics selected for this run (e.g. ['items']).
     * @return int The new run ID.
     */
    public static function start_run(int $blockinstanceid, int $userid, array $modules): int {
        global $DB;

        $now = time();
        $run = new \stdClass();
        $run->blockinstanceid = $blockinstanceid;
        $run->userid = $userid;
        $run->modules = json_encode($modules);
        $run->status = 'running';
        $run->timecreated = $now;
        $run->timemodified = $now;

        return (int) $DB->insert_record('block_playerhud_wizard_runs', $run);
    }

    /**
     * Records a single object created by a run, for later rollback.
     *
     * @param int $runid The run ID.
     * @param string $objecttable The table the object belongs to.
     * @param int $objectid The object's ID in that table.
     * @return void
     */
    public static function record_object(int $runid, string $objecttable, int $objectid): void {
        global $DB;

        $DB->insert_record('block_playerhud_wizard_objects', (object) [
            'runid' => $runid,
            'objecttable' => $objecttable,
            'objectid' => $objectid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Records a batch of objects created by a run, for later rollback.
     *
     * @param int $runid The run ID.
     * @param string $objecttable The table the objects belong to.
     * @param int[] $objectids The objects' IDs in that table.
     * @return void
     */
    public static function record_objects(int $runid, string $objecttable, array $objectids): void {
        global $DB;

        $now = time();
        $records = [];
        foreach ($objectids as $objectid) {
            $records[] = (object) [
                'runid' => $runid,
                'objecttable' => $objecttable,
                'objectid' => (int) $objectid,
                'timecreated' => $now,
            ];
        }

        if (!empty($records)) {
            $DB->insert_records('block_playerhud_wizard_objects', $records);
        }
    }

    /**
     * Records a drop shortcode inserted into course content by a run, so rollback
     * can strip it back out via {@see \block_playerhud\external\remove_drop_shortcode}.
     *
     * @param int $runid The run ID.
     * @param int $dropid The drop whose shortcode was inserted.
     * @param int $cmid The course module the shortcode was inserted into.
     * @param string $field The field the shortcode was inserted into (intro or content).
     * @return void
     */
    public static function record_shortcode(int $runid, int $dropid, int $cmid, string $field): void {
        global $DB;

        $DB->insert_record('block_playerhud_wizard_shortcodes', (object) [
            'runid' => $runid,
            'dropid' => $dropid,
            'cmid' => $cmid,
            'field' => $field,
            'timecreated' => time(),
        ]);
    }

    /**
     * Marks a run as finished.
     *
     * @param int $runid The run ID.
     * @param string $status New status: 'done' or 'rolledback'.
     * @return void
     */
    public static function finish_run(int $runid, string $status): void {
        global $DB;

        $DB->update_record('block_playerhud_wizard_runs', (object) [
            'id' => $runid,
            'status' => $status,
            'timemodified' => time(),
        ]);
    }

    /**
     * Returns the most recent still-active runs for an instance, with object
     * counts per table so the caller can build a human-readable summary.
     *
     * Only 'done' runs are returned: a rolledback run has nothing left to undo.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param int $limit Maximum number of runs to return.
     * @return \stdClass[] Records {id, timecreated, counts: array<string, int>}, newest first.
     */
    public static function get_active_runs(int $blockinstanceid, int $limit = 10): array {
        global $DB;

        $runs = $DB->get_records(
            'block_playerhud_wizard_runs',
            ['blockinstanceid' => $blockinstanceid, 'status' => 'done'],
            'timecreated DESC',
            'id, timecreated',
            0,
            $limit
        );

        if (empty($runs)) {
            return [];
        }

        // Bulk-load object counts per run+table to avoid an N+1 query problem.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($runs), SQL_PARAMS_NAMED);
        $sql = "SELECT runid, objecttable, COUNT(*) AS cnt
                  FROM {block_playerhud_wizard_objects}
                 WHERE runid $insql
              GROUP BY runid, objecttable";
        $countrows = $DB->get_recordset_sql($sql, $inparams);

        $countsbyrun = [];
        foreach ($countrows as $row) {
            $countsbyrun[$row->runid][$row->objecttable] = (int) $row->cnt;
        }
        $countrows->close();

        $result = [];
        foreach ($runs as $run) {
            // A run that created nothing (every heuristic milestone already existed)
            // has nothing to undo, so it is not worth listing.
            if (empty($countsbyrun[$run->id])) {
                continue;
            }
            $result[] = (object) [
                'id' => (int) $run->id,
                'timecreated' => (int) $run->timecreated,
                'counts' => $countsbyrun[$run->id],
            ];
        }

        return $result;
    }

    /**
     * Returns, per wizard mechanic, whether it has already been generated for this instance —
     * used to disable each mechanic's card after its first successful run, instead of letting
     * the teacher re-run it and hit an unexplained "nothing new was generated" no-op.
     *
     * The wizard is deliberately one-shot per mechanic per course: the dedicated management
     * screens ("Criar Item Mágico", "Sugerir Missões", "Sugerir Trocas", "Distribuir Drops")
     * are the intended path for adding more of anything later.
     *
     * Three different signals decide this, and they are NOT interchangeable:
     * - PlayerCoin, Avatars, Pill, Secret Drops, Latepenalty and the RPG progress item each have
     *   a real idempotency fingerprint (a fixed action_type or tone-specific name), so these
     *   check that fingerprint's live existence instead of run history. A 'done' run is not
     *   enough on its own: content deleted through some other route (e.g. the Items management
     *   screen) leaves the run's status untouched, and trusting that stale history would disable
     *   the card forever with no way back short of editing the database directly.
     * - Items, Missions and Comércio have no fixed-name fingerprint of their own (their content
     *   is arbitrary AI-generated names or heuristic trades), so existence cannot be checked the
     *   same way. Instead, a completed ('done') run having included the module only counts when
     *   at least one object that SAME run recorded in its own manifest still exists — the
     *   manifest is a real proxy for "this specific run's output survived", not just "some run
     *   once claimed to have done this". A run whose manifest is empty (e.g. its content was
     *   deleted through some other route, outside the wizard's own undo) no longer counts,
     *   exactly like the fingerprint checks above. This is imprecise only when a single run
     *   bundled the module with another item-creating mechanic (e.g. Items + PlayerCoin
     *   together) and only the OTHER mechanic's item survived — a known, narrow limitation
     *   accepted because the alternative (trusting stale history unconditionally) is the exact
     *   bug this exists to prevent.
     * - Ranking uses neither signal: it is a live read of the block's own current
     *   `enable_ranking` setting, not run history or a fixed content fingerprint. Checking its
     *   box any number of times is harmless either way (generate_ranking() already no-ops if it
     *   is already on) — its card simply mirrors whatever the setting is right now, disabling
     *   itself while ranking is on and re-enabling the instant a teacher turns it back off via
     *   the block's own settings screen, regardless of how many wizard runs ever touched it.
     *
     * @param int $blockinstanceid The block instance ID.
     * @param \stdClass $config The block instance configuration (for the ranking flag).
     * @return array<string, bool> Keyed by mechanic: items, missions, playercoin, avatars,
     *     comercio, pill, secret_drops, latepenalty, progress_item, rpg, ranking.
     */
    public static function get_generated_modules(int $blockinstanceid, \stdClass $config): array {
        global $DB;

        $ranrunids = [];
        $runs = $DB->get_records(
            'block_playerhud_wizard_runs',
            ['blockinstanceid' => $blockinstanceid, 'status' => 'done'],
            '',
            'id, modules'
        );
        foreach ($runs as $run) {
            foreach ((array) json_decode($run->modules ?? '[]') as $module) {
                $ranrunids[$module][] = (int) $run->id;
            }
        }

        $items = $DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $blockinstanceid],
            '',
            'id, name, action_type'
        );
        $actiontypes = [];
        $itemnames = [];
        foreach ($items as $item) {
            $actiontypes[$item->action_type] = true;
            $itemnames[$item->name] = true;
        }

        $tonekeys = ['fantasy', 'scifi', 'mystery', 'academic'];
        $hasprogressitem = false;
        $hassecretitem = false;
        foreach ($tonekeys as $tonekey) {
            $progressname = \block_playerhud\external\wizard_generate::resolve_progress_item_name($tonekey);
            $secretname = \block_playerhud\external\wizard_generate::resolve_secret_name($tonekey);
            $hasprogressitem = $hasprogressitem || isset($itemnames[$progressname]);
            $hassecretitem = $hassecretitem || isset($itemnames[$secretname]);
        }

        // RPG counts classes and chapters from any origin (hand-made ones included): generating
        // a wizard arc on top of an existing story is exactly the pile-up this rule prevents.
        $hasstory = $DB->record_exists('block_playerhud_classes', ['blockinstanceid' => $blockinstanceid])
            || $DB->record_exists('block_playerhud_chapters', ['blockinstanceid' => $blockinstanceid]);

        return [
            'items' => self::has_manifest_content($ranrunids['items'] ?? [], 'block_playerhud_items'),
            'missions' => self::has_manifest_content($ranrunids['missions'] ?? [], 'block_playerhud_quests'),
            'playercoin' => isset($actiontypes['playercoin']),
            'avatars' => isset($actiontypes['avatar_profile']),
            'comercio' => self::has_manifest_content($ranrunids['comercio'] ?? [], 'block_playerhud_trades'),
            'pill' => isset($actiontypes['knowledge_pill']),
            'secret_drops' => $hassecretitem,
            'latepenalty' => isset($actiontypes['deadline_extension']),
            'progress_item' => $hasprogressitem,
            'rpg' => !empty($ranrunids['rpg']) || !empty($ranrunids['next_chapter']) || $hasstory,
            'ranking' => !empty($config->enable_ranking),
        ];
    }

    /**
     * Whether at least one object recorded in the manifest of any of the given runs, for the
     * given table, still exists — used by get_generated_modules() for the mechanics with no
     * fixed-name fingerprint of their own (Items, Missions, Comércio), so a 'done' run whose
     * output was entirely deleted through some other route no longer counts as generated.
     *
     * @param int[] $runids Wizard run IDs to check the manifest of.
     * @param string $table The object table to check existence in (e.g. 'block_playerhud_items').
     * @return bool
     */
    protected static function has_manifest_content(array $runids, string $table): bool {
        global $DB;

        if (empty($runids)) {
            return false;
        }

        [$runinsql, $runinparams] = $DB->get_in_or_equal($runids, SQL_PARAMS_NAMED);
        $objectids = $DB->get_fieldset_select(
            'block_playerhud_wizard_objects',
            'objectid',
            "runid $runinsql AND objecttable = :objecttable",
            array_merge($runinparams, ['objecttable' => $table])
        );
        if (empty($objectids)) {
            return false;
        }

        [$objinsql, $objinparams] = $DB->get_in_or_equal(array_unique($objectids));
        return $DB->record_exists_select($table, "id $objinsql", $objinparams);
    }

    /**
     * Turns a boolean block-config flag on if it is currently off, otherwise a no-op.
     *
     * Deliberately one-directional, matching generate_ranking()'s own long-standing rule:
     * a wizard mechanic checking the teacher's box is deliberate intent, so it is safe to
     * ensure the setting that makes its content visible is on — but never to turn a setting
     * back off, since that could undo a choice the teacher made for reasons unrelated to this
     * run (the tab a flag gates can hold content from outside the wizard entirely). Writes
     * through the block's own `instance_config_save()` (merges into the existing config object
     * rather than replacing it), same safe pattern as `wizard_apply_suggested_levels`.
     *
     * @param int $blockinstanceid Block instance ID.
     * @param string $flag Config property name, e.g. 'enable_items'.
     * @return void
     */
    public static function ensure_config_flag(int $blockinstanceid, string $flag): void {
        $blockinstance = \block_instance_by_id($blockinstanceid);
        $config = $blockinstance->config ?: new \stdClass();

        if (!empty($config->$flag)) {
            return;
        }

        $config->$flag = 1;
        $blockinstance->instance_config_save($config);
    }

    /**
     * Undoes a wizard run: deletes every object it created, wherever it lives.
     *
     * Scoped to the given block instance so a run ID from another instance can
     * never be rolled back through this method. Any shortcode this run inserted into
     * course content is stripped back out first (best-effort — a shortcode whose activity
     * was since deleted or edited independently is skipped, never blocking the rest of the
     * rollback), before the objects it points to are deleted.
     *
     * Each object is deleted through the same controller the management UI uses
     * (item/quest/trade/chapter delete), so a rollback reverts the XP students earned
     * from what it created and clears their play history (inventory, quest and trade
     * logs) exactly as deleting each object by hand would — rather than a raw DELETE that
     * would leave the earned XP standing and strand those rows as orphans.
     *
     * @param int $runid The run ID.
     * @param int $blockinstanceid The block instance the caller is authorised for.
     * @param int $courseid The course the block instance belongs to.
     * @return int The number of objects deleted.
     */
    public static function rollback(int $runid, int $blockinstanceid, int $courseid): int {
        global $DB;

        $DB->get_record(
            'block_playerhud_wizard_runs',
            ['id' => $runid, 'blockinstanceid' => $blockinstanceid],
            'id',
            MUST_EXIST
        );

        $shortcodes = $DB->get_records('block_playerhud_wizard_shortcodes', ['runid' => $runid]);
        foreach ($shortcodes as $shortcode) {
            try {
                \block_playerhud\external\remove_drop_shortcode::execute(
                    $blockinstanceid,
                    $courseid,
                    (int) $shortcode->dropid,
                    (int) $shortcode->cmid,
                    $shortcode->field
                );
            } catch (\Throwable $e) {
                // The activity may have been deleted or edited independently since the
                // shortcode was inserted — never let that block the rest of the rollback.
                continue;
            }
        }

        $objects = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);

        $idsbytable = [];
        foreach ($objects as $object) {
            $idsbytable[$object->objecttable][] = (int) $object->objectid;
        }

        $context = \context_block::instance($blockinstanceid);

        $transaction = $DB->start_delegated_transaction();

        // Route the objects that carry XP or play history through their controllers so a
        // rollback mirrors a manual delete. Order matters: trades and quests first (they
        // own their own log rows), then items (whose delete reverts item XP and cascades
        // to inventory, drops and trade references), then chapters (cascade to scenes).
        self::rollback_trades($idsbytable['block_playerhud_trades'] ?? [], $blockinstanceid);
        self::rollback_quests($idsbytable['block_playerhud_quests'] ?? [], $blockinstanceid);
        self::rollback_items($idsbytable['block_playerhud_items'] ?? [], $blockinstanceid, $context);
        self::rollback_chapters($idsbytable['block_playerhud_chapters'] ?? [], $blockinstanceid);

        // Defensive sweep for the pure child rows the cascades above normally remove
        // already (trade req/reward, story node/choice, drop). Deleting an id that is
        // already gone is a harmless no-op; this only matters if a parent was missing.
        $childtables = [
            'block_playerhud_trade_reqs',
            'block_playerhud_trade_rewards',
            'block_playerhud_story_nodes',
            'block_playerhud_choices',
            'block_playerhud_drops',
        ];
        foreach ($childtables as $childtable) {
            if (!empty($idsbytable[$childtable])) {
                $DB->delete_records_list($childtable, 'id', $idsbytable[$childtable]);
            }
        }

        // Safety net: any other recorded table a future module might add falls back to a
        // raw delete, so nothing the run created is ever left behind.
        $handled = array_merge(
            ['block_playerhud_items', 'block_playerhud_quests', 'block_playerhud_trades', 'block_playerhud_chapters'],
            $childtables
        );
        foreach ($idsbytable as $table => $ids) {
            if (!in_array($table, $handled, true)) {
                $DB->delete_records_list($table, 'id', $ids);
            }
        }

        $DB->delete_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        $DB->delete_records('block_playerhud_wizard_shortcodes', ['runid' => $runid]);
        self::finish_run($runid, 'rolledback');

        $transaction->allow_commit();

        return count($objects);
    }

    /**
     * Deletes the run's trades through the trade controller, so each trade's
     * requirement, reward and log rows are cleaned up the same way a manual trade
     * deletion would. Ids no longer present are skipped.
     *
     * @param int[] $tradeids The recorded trade IDs.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    private static function rollback_trades(array $tradeids, int $instanceid): void {
        global $DB;

        if (empty($tradeids)) {
            return;
        }

        $existing = $DB->get_records_list('block_playerhud_trades', 'id', $tradeids, '', 'id');
        $controller = new \block_playerhud\controller\trades();
        foreach ($existing as $trade) {
            $controller->delete_trade((int) $trade->id, $instanceid);
        }
    }

    /**
     * Deletes the run's quests through the quest controller, reverting the reward
     * XP of every student who had claimed them and clearing the quest log.
     *
     * @param int[] $questids The recorded quest IDs.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    private static function rollback_quests(array $questids, int $instanceid): void {
        if (empty($questids)) {
            return;
        }

        \block_playerhud\controller\quests::bulk_delete_quests($questids, $instanceid);
    }

    /**
     * Deletes the run's items through the item controller, reverting the XP of every
     * holder and cascading to inventory, drops and trade references, exactly as a
     * manual item deletion would. Foreign-instance ids are filtered out first.
     *
     * @param int[] $itemids The recorded item IDs.
     * @param int $instanceid The owning block instance ID.
     * @param \context_block $context The block context, for file area cleanup.
     * @return void
     */
    private static function rollback_items(array $itemids, int $instanceid, \context_block $context): void {
        global $DB;

        if (empty($itemids)) {
            return;
        }

        $items = $DB->get_records_list('block_playerhud_items', 'id', $itemids, '', 'id, blockinstanceid');
        $items = array_filter($items, static fn($item): bool => (int) $item->blockinstanceid === $instanceid);
        if (empty($items)) {
            return;
        }

        \block_playerhud\controller\items::bulk_delete_items($items, $instanceid, $context);
    }

    /**
     * Deletes the run's story chapters through the chapter controller, cascading to
     * their scenes and choices. Ids no longer present are skipped.
     *
     * @param int[] $chapterids The recorded chapter IDs.
     * @param int $instanceid The owning block instance ID.
     * @return void
     */
    private static function rollback_chapters(array $chapterids, int $instanceid): void {
        global $DB;

        if (empty($chapterids)) {
            return;
        }

        $existing = $DB->get_records_list('block_playerhud_chapters', 'id', $chapterids, '', 'id');
        $controller = new \block_playerhud\controller\chapters();
        foreach ($existing as $chapter) {
            $controller->delete_chapter((int) $chapter->id, $instanceid);
        }
    }
}
