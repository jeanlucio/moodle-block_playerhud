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
 * Upgrade script for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block_playerhud.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Result.
 */
function xmldb_block_playerhud_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Past upgrade steps were merged into install.xml for the current baseline version.

    if ($oldversion < 2026052801) {
        // Remove guest default permission from block/playerhud:view.
        // The capability was never effective (guests are blocked earlier in get_content
        // and require_login), but the archetype declaration was misleading and prevented
        // the Permissions UI from working as expected when an admin restricted the role.
        $guestrole = $DB->get_record('role', ['shortname' => 'guest']);
        if ($guestrole) {
            unassign_capability('block/playerhud:view', $guestrole->id);
        }

        upgrade_block_savepoint(true, 2026052801, 'playerhud');
    }

    if ($oldversion < 2026052802) {
        // Add action_type and action_value columns to block_playerhud_items.
        // These columns support item powers: avatar_profile and deadline_extension.
        $table = new \xmldb_table('block_playerhud_items');

        $fieldtype = new \xmldb_field('action_type', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        if (!$dbman->field_exists($table, $fieldtype)) {
            $dbman->add_field($table, $fieldtype);
        }

        $fieldvalue = new \xmldb_field('action_value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $fieldvalue)) {
            $dbman->add_field($table, $fieldvalue);
        }

        upgrade_block_savepoint(true, 2026052802, 'playerhud');
    }

    if ($oldversion < 2026052903) {
        // Mark all existing PlayerCoin items with action_type = 'playercoin' so they can
        // be identified by field value rather than by the mutable display name.
        $DB->execute(
            "UPDATE {block_playerhud_items}
                SET action_type = 'playercoin'
              WHERE name = 'PlayerCoin'
                AND (action_type IS NULL OR action_type = '')"
        );

        upgrade_block_savepoint(true, 2026052903, 'playerhud');
    }

    if ($oldversion < 2026060101) {
        // Add emoji/URL fallback fields (one per tier) to block_playerhud_classes.
        // Mirrors the existing item->image field: accepts an emoji character or an absolute URL.
        $table = new \xmldb_table('block_playerhud_classes');

        for ($tier = 1; $tier <= 5; $tier++) {
            $field = new \xmldb_field(
                'emoji_tier' . $tier,
                XMLDB_TYPE_CHAR,
                '255',
                null,
                null,
                null,
                null
            );
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_block_savepoint(true, 2026060101, 'playerhud');
    }

    if ($oldversion < 2026062303) {
        // Add the milestones bitmask to block_playerhud_user. Tracks one-time
        // celebration popups already shown (e.g. first PlayerCoin, first quest).
        $table = new \xmldb_table('block_playerhud_user');
        $field = new \xmldb_field(
            'milestones',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'last_shop_view'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_block_savepoint(true, 2026062303, 'playerhud');
    }

    if ($oldversion < 2026070101) {
        // 1. Backfill any missing drop codes before enforcing NOTNULL + UNIQUE.
        // Sites installed before the code field existed may still hold NULL/empty values.
        $drops = $DB->get_records_select(
            'block_playerhud_drops',
            'code IS NULL OR code = :empty',
            ['empty' => '']
        );
        foreach ($drops as $drop) {
            $exists = true;
            while ($exists) {
                $code = strtoupper(random_string(6));
                $exists = $DB->record_exists('block_playerhud_drops', [
                    'blockinstanceid' => $drop->blockinstanceid,
                    'code' => $code,
                ]);
            }
            $DB->set_field('block_playerhud_drops', 'code', $code, ['id' => $drop->id]);
        }

        // Drops.code becomes mandatory and unique per block instance, so the shortcode
        // lookup in filter_playerhud can never be ambiguous.
        $dropstable = new \xmldb_table('block_playerhud_drops');
        $codefield = new \xmldb_field('code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_notnull($dropstable, $codefield);

        $codeindex = new \xmldb_index('blockinstance_code', XMLDB_INDEX_UNIQUE, ['blockinstanceid', 'code']);
        if (!$dbman->index_exists($dropstable, $codeindex)) {
            $dbman->add_index($dropstable, $codeindex);
        }

        // 2. Add the missing timecreated/timemodified audit fields.
        $tablenames = [
            'block_playerhud_rpg_progress',
            'block_playerhud_chapters',
            'block_playerhud_story_nodes',
            'block_playerhud_choices',
            'block_playerhud_trade_reqs',
            'block_playerhud_trade_rewards',
        ];
        foreach ($tablenames as $tablename) {
            $table = new \xmldb_table($tablename);
            foreach (['timecreated', 'timemodified'] as $fieldname) {
                $field = new \xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                    $DB->set_field_select($tablename, $fieldname, time(), '1=1');
                }
            }
        }

        // Trades already has timecreated; only timemodified is missing.
        $tradestable = new \xmldb_table('block_playerhud_trades');
        $timemodifiedfield = new \xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($tradestable, $timemodifiedfield)) {
            $dbman->add_field($tradestable, $timemodifiedfield);
            $DB->set_field_select('block_playerhud_trades', 'timemodified', time(), '1=1');
        }

        // 3. Wizard rollback manifest: one row per generation run, one row per object it created.
        if (!$dbman->table_exists('block_playerhud_wizard_runs')) {
            $table = new \xmldb_table('block_playerhud_wizard_runs');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('blockinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('modules', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'running');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        if (!$dbman->table_exists('block_playerhud_wizard_objects')) {
            $table = new \xmldb_table('block_playerhud_wizard_objects');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('objecttable', XMLDB_TYPE_CHAR, '60', null, XMLDB_NOTNULL, null, null);
            $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('runid', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026070101, 'playerhud');
    }

    if ($oldversion < 2026070201) {
        // Wizard shortcode manifest: lets rollback strip a drop shortcode back out of course
        // content (activity intro/content, or the news forum for PlayerCoin) instead of only
        // deleting the drop row and leaving the shortcode text orphaned.
        if (!$dbman->table_exists('block_playerhud_wizard_shortcodes')) {
            $table = new \xmldb_table('block_playerhud_wizard_shortcodes');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('dropid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('field', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('runid', XMLDB_INDEX_NOTUNIQUE, ['runid']);
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026070201, 'playerhud');
    }

    if ($oldversion < 2026070901) {
        // Add xpawarded to block_playerhud_inventory: records the actual XP paid out for this
        // copy at grant time (0 for infinite drops, quest/trade item rewards, or a zero-XP
        // item), instead of always recomputing it later from the item's current (possibly
        // since-edited) xp value.
        $table = new \xmldb_table('block_playerhud_inventory');
        $field = new \xmldb_field(
            'xpawarded',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'timecreated'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill rows that already existed when this column was added, using the same
        // formula the audit log used to recompute on the fly (current item xp, only when the
        // drop was finite or missing). This runs in the same atomic step as add_field(), before
        // any request can reach the new xpawarded-aware code, so every row seen here predates
        // the column and is safe to backfill unconditionally. This does not recover a row where
        // the item was edited after the historical grant — that number is unrecoverable without
        // forensic log reconstruction — but it preserves whatever accuracy already existed
        // instead of collapsing every pre-existing row to 0.
        $sql = "SELECT inv.id, i.xp
                  FROM {block_playerhud_inventory} inv
                  JOIN {block_playerhud_items} i ON i.id = inv.itemid
             LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
                 WHERE inv.source IN ('map', 'teacher', 'revoked')
                   AND i.xp > 0
                   AND COALESCE(d.maxusage, 1) > 0";
        $rows = $DB->get_records_sql($sql);
        foreach ($rows as $row) {
            $DB->set_field('block_playerhud_inventory', 'xpawarded', $row->xp, ['id' => $row->id]);
        }

        upgrade_block_savepoint(true, 2026070901, 'playerhud');
    }

    if ($oldversion < 2026070902) {
        // Catch-up for sites that already passed 2026070901 before the backfill above existed
        // (this dev environment's own three containers). Unlike the block above, real requests
        // may already have written correct rows through the new xpawarded-aware code by the
        // time this runs — including legitimately-zero ones (an infinite drop, a zero-XP item).
        // Blindly reusing the "xpawarded = 0" condition here could overwrite those with a wrong
        // non-zero value if the item/drop was edited afterwards. So this step only touches rows
        // strictly older than the moment this site reached 2026070901, read from its own
        // upgrade_log — anything created at or after that moment was already written correctly
        // and must never be touched.
        $cutoff = $DB->get_field_sql(
            "SELECT MIN(timemodified) FROM {upgrade_log}
              WHERE plugin = 'block_playerhud' AND version = '2026070901'
                AND info = 'Upgrade savepoint reached'"
        );

        if ($cutoff) {
            $sql = "SELECT inv.id, i.xp
                      FROM {block_playerhud_inventory} inv
                      JOIN {block_playerhud_items} i ON i.id = inv.itemid
                 LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
                     WHERE inv.source IN ('map', 'teacher', 'revoked')
                       AND inv.xpawarded = 0
                       AND inv.timecreated < :cutoff
                       AND i.xp > 0
                       AND COALESCE(d.maxusage, 1) > 0";
            $rows = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);
            foreach ($rows as $row) {
                $DB->set_field('block_playerhud_inventory', 'xpawarded', $row->xp, ['id' => $row->id]);
            }
        }

        upgrade_block_savepoint(true, 2026070902, 'playerhud');
    }

    return true;
}
