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
     * Undoes a wizard run: deletes every object it created, wherever it lives.
     *
     * Scoped to the given block instance so a run ID from another instance can
     * never be rolled back through this method.
     *
     * @param int $runid The run ID.
     * @param int $blockinstanceid The block instance the caller is authorised for.
     * @return int The number of objects deleted.
     */
    public static function rollback(int $runid, int $blockinstanceid): int {
        global $DB;

        $DB->get_record(
            'block_playerhud_wizard_runs',
            ['id' => $runid, 'blockinstanceid' => $blockinstanceid],
            'id',
            MUST_EXIST
        );

        $objects = $DB->get_records('block_playerhud_wizard_objects', ['runid' => $runid]);

        $idsbytable = [];
        foreach ($objects as $object) {
            $idsbytable[$object->objecttable][] = (int) $object->objectid;
        }

        $transaction = $DB->start_delegated_transaction();

        foreach ($idsbytable as $table => $ids) {
            $DB->delete_records_list($table, 'id', $ids);
        }

        $DB->delete_records('block_playerhud_wizard_objects', ['runid' => $runid]);
        self::finish_run($runid, 'rolledback');

        $transaction->allow_commit();

        return count($objects);
    }
}
