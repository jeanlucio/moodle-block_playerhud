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
 * Detection and cleanup of data left behind by removed block instances.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local\diagnostics;

use block_playerhud\instance_cleanup;

/**
 * Finds and deletes rows that no live PlayerHUD instance can reach any more.
 *
 * Two kinds of leftovers exist. Orphan instances: rows keyed on a blockinstanceid whose
 * block_instances row is gone (instances deleted before instance_delete() cleaned up, v1.7.0).
 * Loose rows: child rows whose parent row (item, quest, chapter, story node, trade, wizard run)
 * is gone. No page, report or Privacy API export reaches either kind.
 *
 * @package    block_playerhud
 */
class integrity {
    /** @var string[] Tables keyed directly on the block instance. */
    public const DIRECT_TABLES = [
        'block_playerhud_user',
        'block_playerhud_items',
        'block_playerhud_drops',
        'block_playerhud_classes',
        'block_playerhud_rpg_progress',
        'block_playerhud_quests',
        'block_playerhud_chapters',
        'block_playerhud_trades',
        'block_playerhud_ai_logs',
        'block_playerhud_wizard_runs',
    ];

    /**
     * @var array Child tables as table => [foreign key column, parent table]. A parent that is
     *      itself a child (story nodes) is listed before its own children (choices), so cleaning
     *      in this order also catches rows that only become loose once their parent goes.
     */
    public const CHILD_TABLES = [
        'block_playerhud_story_nodes' => ['chapterid', 'block_playerhud_chapters'],
        'block_playerhud_choices' => ['nodeid', 'block_playerhud_story_nodes'],
        'block_playerhud_inventory' => ['itemid', 'block_playerhud_items'],
        'block_playerhud_stack' => ['itemid', 'block_playerhud_items'],
        'block_playerhud_stack_log' => ['itemid', 'block_playerhud_items'],
        'block_playerhud_quest_log' => ['questid', 'block_playerhud_quests'],
        'block_playerhud_trade_reqs' => ['tradeid', 'block_playerhud_trades'],
        'block_playerhud_trade_rewards' => ['tradeid', 'block_playerhud_trades'],
        'block_playerhud_trade_log' => ['tradeid', 'block_playerhud_trades'],
        'block_playerhud_wizard_objects' => ['runid', 'block_playerhud_wizard_runs'],
        'block_playerhud_wizard_shortcodes' => ['runid', 'block_playerhud_wizard_runs'],
    ];

    /** @var int Most orphan instances detailed (and offered for deletion) at once; the rest come next time. */
    public const MAXLISTED = 100;

    /** @var int Seconds searched on each side of an orphan's last XP change to find its course in the log. */
    public const LOG_WINDOW = 300;

    /** @var string Pattern of the backup file names written before a cleanup. */
    private const BACKUP_PATTERN = '/^orphans-\d{8}-\d{6}-[0-9a-f]{4}\.json$/';

    /**
     * Lists the block instance ids that still own rows although their block instance is gone.
     *
     * @return int[] Orphan block instance ids, ascending.
     */
    public static function get_orphan_instance_ids(): array {
        global $DB;

        $selects = [];
        $params = [];
        foreach (self::DIRECT_TABLES as $index => $table) {
            $selects[] = "SELECT t{$index}.blockinstanceid AS id
                            FROM {{$table}} t{$index}
                           WHERE NOT EXISTS (SELECT 1
                                               FROM {block_instances} bi{$index}
                                              WHERE bi{$index}.id = t{$index}.blockinstanceid
                                                AND bi{$index}.blockname = :bn{$index})";
            $params['bn' . $index] = 'playerhud';
        }

        $ids = array_map('intval', $DB->get_fieldset_sql(implode(' UNION ', $selects), $params));
        sort($ids);
        return $ids;
    }

    /**
     * Describes each orphan instance so an administrator can judge it before deleting.
     *
     * The course comes from the standard log: the xp_changed event of the instance's last XP
     * gain, searched only within LOG_WINDOW seconds of that gain so the lookup stays on the
     * log's timecreated index instead of scanning the whole table.
     *
     * Only the first $limit orphans (by id) are detailed, which keeps the detail queries and the
     * page bounded on a site with many removed blocks; the remaining ones are listed once these
     * are cleaned. Instances whose course still exists come first.
     *
     * @param int $limit Most orphans to detail; 0 for all.
     * @param int[]|null $ids Orphan ids already looked up by the caller, to avoid a second lookup.
     * @return array Objects with id, players, xp, firstactivity, lastactivity, deletedusers,
     *               items, courseid (0 when unknown) and courseexists (null when unknown).
     */
    public static function get_orphan_instances(int $limit = self::MAXLISTED, ?array $ids = null): array {
        global $DB;

        $ids = $ids ?? self::get_orphan_instance_ids();
        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }
        if (empty($ids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'oi');
        $players = $DB->get_records_sql(
            "SELECT u.blockinstanceid, COUNT(1) AS players, SUM(u.currentxp) AS xp,
                    MIN(u.timecreated) AS firstactivity, MAX(u.timemodified) AS lastactivity,
                    SUM(CASE WHEN usr.id IS NULL OR usr.deleted = 1 THEN 1 ELSE 0 END) AS deletedusers
               FROM {block_playerhud_user} u
          LEFT JOIN {user} usr ON usr.id = u.userid
              WHERE u.blockinstanceid $insql
           GROUP BY u.blockinstanceid",
            $inparams
        );
        $items = $DB->get_records_sql_menu(
            "SELECT blockinstanceid, COUNT(1)
               FROM {block_playerhud_items}
              WHERE blockinstanceid $insql
           GROUP BY blockinstanceid",
            $inparams
        );

        $courses = self::find_courses_in_log($players);

        $result = [];
        foreach ($ids as $id) {
            $stats = $players[$id] ?? null;
            $courseid = $courses[$id] ?? 0;
            $result[] = (object) [
                'id' => $id,
                'players' => $stats ? (int) $stats->players : 0,
                'xp' => $stats ? (int) $stats->xp : 0,
                'firstactivity' => $stats ? (int) $stats->firstactivity : 0,
                'lastactivity' => $stats ? (int) $stats->lastactivity : 0,
                'deletedusers' => $stats ? (int) $stats->deletedusers : 0,
                'items' => (int) ($items[$id] ?? 0),
                'courseid' => $courseid,
                'courseexists' => null,
            ];
        }

        $existing = [];
        if (!empty($courses)) {
            $existing = $DB->get_records_list('course', 'id', array_values(array_unique($courses)), '', 'id');
        }
        foreach ($result as $orphan) {
            if ($orphan->courseid > 0) {
                $orphan->courseexists = isset($existing[$orphan->courseid]);
            }
        }

        // Instances whose course still exists first: they are the ones to look at before deleting.
        usort($result, static fn($a, $b) => (int) ($b->courseexists === true) <=> (int) ($a->courseexists === true)
            ?: $a->id <=> $b->id);

        return $result;
    }

    /**
     * Counts the player rows of every orphan instance, listed or not.
     *
     * @return int Player rows whose block instance is gone.
     */
    public static function count_orphan_players(): int {
        global $DB;
        return $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_playerhud_user} u
              WHERE NOT EXISTS (SELECT 1
                                  FROM {block_instances} bi
                                 WHERE bi.id = u.blockinstanceid
                                   AND bi.blockname = :blockname)",
            ['blockname' => 'playerhud']
        );
    }

    /**
     * Finds the course each orphan instance belonged to, from its last xp_changed log entry.
     *
     * @param array $players Per-instance player stats keyed by block instance id (lastactivity used).
     * @return array Course id keyed by block instance id; instances not found are absent.
     */
    private static function find_courses_in_log(array $players): array {
        global $DB;

        if (empty($players) || !$DB->get_manager()->table_exists('logstore_standard_log')) {
            return [];
        }

        $conditions = [];
        $params = ['blocklevel' => CONTEXT_BLOCK];
        $index = 0;
        foreach ($players as $stats) {
            if ((int) $stats->lastactivity <= 0) {
                continue;
            }
            $conditions[] = "(l.contextinstanceid = :bid{$index} AND l.timecreated BETWEEN :from{$index} AND :to{$index})";
            $params['bid' . $index] = (int) $stats->blockinstanceid;
            $params['from' . $index] = (int) $stats->lastactivity - self::LOG_WINDOW;
            $params['to' . $index] = (int) $stats->lastactivity + self::LOG_WINDOW;
            $index++;
        }
        if (empty($conditions)) {
            return [];
        }

        $rows = $DB->get_records_sql_menu(
            "SELECT l.contextinstanceid, MAX(l.courseid)
               FROM {logstore_standard_log} l
              WHERE l.contextlevel = :blocklevel
                AND l.courseid > 0
                AND (" . implode(' OR ', $conditions) . ")
           GROUP BY l.contextinstanceid",
            $params
        );

        return array_map('intval', $rows);
    }

    /**
     * Counts, per child table, the rows whose parent row no longer exists.
     *
     * @return array Row count keyed by table name, every child table present.
     */
    public static function count_loose_rows(): array {
        global $DB;

        $selects = [];
        foreach (self::CHILD_TABLES as $table => [$column, $parent]) {
            $selects[] = "SELECT '{$table}' AS tablename, COUNT(1) AS n
                            FROM {{$table}} c
                           WHERE " . self::loose_condition('c', $column, $parent);
        }

        $counts = $DB->get_records_sql_menu(implode(' UNION ALL ', $selects));
        $result = [];
        foreach (array_keys(self::CHILD_TABLES) as $table) {
            $result[$table] = (int) ($counts[$table] ?? 0);
        }
        return $result;
    }

    /**
     * Deletes the selected orphan instances and every loose row, after saving a copy of them.
     *
     * Only ids that are still orphans are honoured, so a live instance is never touched even
     * if its id is submitted. Everything runs in one transaction; if it fails, nothing is
     * deleted and the copy is removed.
     *
     * @param int[] $instanceids Orphan instance ids chosen by the administrator.
     * @return array Keys: file (backup file name), instances (int) and rows (int, rows deleted).
     */
    public static function cleanup(array $instanceids): array {
        global $DB;

        $selected = array_values(array_intersect(
            array_map('intval', $instanceids),
            self::get_orphan_instance_ids()
        ));

        if (empty($selected) && array_sum(self::count_loose_rows()) === 0) {
            return ['file' => '', 'instances' => 0, 'rows' => 0];
        }

        [$path, $filename] = self::new_backup_path();
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \moodle_exception('diag_backup_failed', 'block_playerhud');
        }

        $rows = 0;
        $transaction = $DB->start_delegated_transaction();
        try {
            fwrite($handle, '{"created":' . time() . ',"instances":' . json_encode($selected) . ',"tables":{');
            $first = true;

            if (!empty($selected)) {
                $rows += self::export_instance_rows($handle, $selected, $first);
                // One cleanup per instance reuses the tested path that instance_delete() runs.
                foreach ($selected as $instanceid) {
                    instance_cleanup::delete_instance_data($instanceid);
                }
            }

            // Bounded by the schema, not by the data: one export and one delete per child table.
            foreach (self::CHILD_TABLES as $table => [$column, $parent]) {
                $condition = self::loose_condition($table, $column, $parent);
                $rows += self::export_rows($handle, $table, $condition, [], $first, 'loose');
                $DB->delete_records_select($table, $condition);
            }

            fwrite($handle, '}}');
            fclose($handle);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (file_exists($path)) {
                unlink($path);
            }
            $transaction->rollback($e);
        }

        return ['file' => $filename, 'instances' => count($selected), 'rows' => $rows];
    }

    /**
     * Writes every row owned by the given orphan instances to the backup.
     *
     * @param resource $handle Open backup file.
     * @param int[] $instanceids Orphan instance ids.
     * @param bool $first Whether no table section has been written yet (updated).
     * @return int Number of rows written.
     */
    private static function export_instance_rows($handle, array $instanceids, bool &$first): int {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'ei');
        $rows = 0;

        foreach (self::DIRECT_TABLES as $table) {
            $rows += self::export_rows($handle, $table, "blockinstanceid $insql", $params, $first, 'instance');
        }
        foreach (self::CHILD_TABLES as $table => [$column, $parent]) {
            if ($parent === 'block_playerhud_story_nodes') {
                $parentids = "SELECT sn.id
                                FROM {block_playerhud_story_nodes} sn
                                JOIN {block_playerhud_chapters} ch ON ch.id = sn.chapterid
                               WHERE ch.blockinstanceid $insql";
            } else {
                $parentids = "SELECT id FROM {{$parent}} WHERE blockinstanceid $insql";
            }
            $rows += self::export_rows($handle, $table, "$column IN ($parentids)", $params, $first, 'instance');
        }

        $prefnames = array_map(static fn(int $id): string => 'block_playerhud_avatar_' . $id, $instanceids);
        [$prefsql, $prefparams] = $DB->get_in_or_equal($prefnames, SQL_PARAMS_NAMED, 'ep');
        $rows += self::export_rows($handle, 'user_preferences', "name $prefsql", $prefparams, $first, 'instance');

        return $rows;
    }

    /**
     * Streams the rows of one table matching a condition into the backup as a JSON array.
     *
     * @param resource $handle Open backup file.
     * @param string $table Table name without prefix.
     * @param string $condition SQL WHERE condition.
     * @param array $params Condition parameters.
     * @param bool $first Whether no table section has been written yet (updated).
     * @param string $kind Section label: 'instance' or 'loose'.
     * @return int Number of rows written.
     */
    private static function export_rows(
        $handle,
        string $table,
        string $condition,
        array $params,
        bool &$first,
        string $kind
    ): int {
        global $DB;

        $recordset = $DB->get_recordset_select($table, $condition, $params);
        if (!$recordset->valid()) {
            $recordset->close();
            return 0;
        }

        fwrite($handle, ($first ? '' : ',') . json_encode($kind . ':' . $table) . ':[');
        $first = false;
        $count = 0;
        foreach ($recordset as $record) {
            fwrite($handle, ($count > 0 ? ',' : '') . json_encode($record));
            $count++;
        }
        $recordset->close();
        fwrite($handle, ']');

        return $count;
    }

    /**
     * Builds the condition matching rows of a child table whose parent row is gone.
     *
     * @param string $alias Alias or table name of the child rows in the outer query.
     * @param string $column Foreign key column pointing at the parent.
     * @param string $parent Parent table name.
     * @return string SQL condition.
     */
    private static function loose_condition(string $alias, string $column, string $parent): string {
        $prefixed = (strpos($alias, 'block_') === 0) ? "{{$alias}}" : $alias;
        return "NOT EXISTS (SELECT 1 FROM {{$parent}} p WHERE p.id = {$prefixed}.{$column})";
    }

    /**
     * Directory where cleanup backups are kept, created on demand.
     *
     * @return string Absolute path.
     */
    public static function get_backup_dir(): string {
        global $CFG;
        return make_writable_directory($CFG->dataroot . '/block_playerhud/orphan_backups');
    }

    /**
     * Picks an unused path for a new backup file.
     *
     * @return array [string $path, string $filename]
     */
    private static function new_backup_path(): array {
        $filename = 'orphans-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.json';
        return [self::get_backup_dir() . '/' . $filename, $filename];
    }

    /**
     * Lists the backup files, newest first.
     *
     * @return array Objects with name, size (bytes) and time (modification timestamp).
     */
    public static function list_backups(): array {
        $dir = self::get_backup_dir();
        $backups = [];
        foreach (scandir($dir) ?: [] as $name) {
            if (preg_match(self::BACKUP_PATTERN, $name)) {
                $path = $dir . '/' . $name;
                $backups[] = (object) ['name' => $name, 'size' => filesize($path), 'time' => filemtime($path)];
            }
        }
        usort($backups, static fn($a, $b) => $b->time <=> $a->time ?: strcmp($b->name, $a->name));
        return $backups;
    }

    /**
     * Resolves a backup file name to its path, refusing anything that is not one of our backups.
     *
     * @param string $filename Name as submitted by the browser.
     * @return string|null Absolute path, or null when the name is invalid or the file is missing.
     */
    public static function resolve_backup(string $filename): ?string {
        if (!preg_match(self::BACKUP_PATTERN, $filename)) {
            return null;
        }
        $path = self::get_backup_dir() . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    /**
     * Deletes one backup file.
     *
     * @param string $filename Backup file name.
     * @return bool Whether a file was deleted.
     */
    public static function delete_backup(string $filename): bool {
        $path = self::resolve_backup($filename);
        return $path !== null && unlink($path);
    }
}
