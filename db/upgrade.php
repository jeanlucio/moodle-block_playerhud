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
        // The code-generation loop below is O(n) over every drop still missing a code, one
        // DB round-trip per row. A dev site with a handful of drops finishes instantly; a
        // production site with years of accumulated drops can take long enough to hit the
        // web server's max_execution_time mid-step, leaving this savepoint unreached. Extend
        // both the PHP time limit and the upgrade-page stall watchdog before starting.
        upgrade_set_timeout();

        // 1. Backfill any missing drop codes before enforcing NOTNULL + UNIQUE.
        // Sites installed before the code field existed may still hold NULL/empty values.
        $drops = $DB->get_records_select(
            'block_playerhud_drops',
            'code IS NULL OR code = :empty',
            ['empty' => '']
        );

        // Existing codes per blockinstanceid, loaded once so each collision check below is an
        // in-memory lookup instead of a record_exists() round-trip — a site with years of
        // accumulated drops previously paid at least 2 DB calls per row (more on any hash
        // collision) just to find a free code.
        $codesbyinstance = [];
        $existingcodes = $DB->get_records_sql(
            "SELECT id, blockinstanceid, code FROM {block_playerhud_drops} WHERE code IS NOT NULL AND code <> ''"
        );
        foreach ($existingcodes as $existing) {
            $codesbyinstance[$existing->blockinstanceid][$existing->code] = true;
        }

        foreach ($drops as $drop) {
            do {
                $code = strtoupper(random_string(6));
            } while (isset($codesbyinstance[$drop->blockinstanceid][$code]));

            $codesbyinstance[$drop->blockinstanceid][$code] = true;
            $DB->set_field('block_playerhud_drops', 'code', $code, ['id' => $drop->id]);
        }

        // Drops.code becomes mandatory and unique per block instance, so the shortcode
        // lookup in filter_playerhud can never be ambiguous. Both changes always land
        // together, so the index's presence alone guards the pair: a database (e.g.
        // PostgreSQL) refuses to alter a column's NOT NULL constraint while a unique index
        // still depends on it, which change_field_notnull() would hit if this step ever ran
        // again against a site whose schema already reached this state.
        $dropstable = new \xmldb_table('block_playerhud_drops');
        $codeindex = new \xmldb_index('blockinstance_code', XMLDB_INDEX_UNIQUE, ['blockinstanceid', 'code']);
        if (!$dbman->index_exists($dropstable, $codeindex)) {
            $codefield = new \xmldb_field('code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_notnull($dropstable, $codefield);
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
        // instead of collapsing every pre-existing row to 0. A single correlated-subquery
        // UPDATE, same pattern as the quest_log backfill below (2026070903), instead of one
        // set_field() round-trip per matching row.
        $DB->execute(
            "UPDATE {block_playerhud_inventory}
                SET xpawarded = (
                    SELECT i.xp FROM {block_playerhud_items} i WHERE i.id = itemid
                )
              WHERE source IN ('map', 'teacher', 'revoked')
                AND itemid IN (SELECT id FROM {block_playerhud_items} WHERE xp > 0)
                AND COALESCE(
                    (SELECT d.maxusage FROM {block_playerhud_drops} d WHERE d.id = dropid), 1
                ) > 0"
        );

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
            // Single correlated-subquery UPDATE instead of one set_field() round-trip per
            // matching row, same pattern as the block above.
            $DB->execute(
                "UPDATE {block_playerhud_inventory}
                    SET xpawarded = (
                        SELECT i.xp FROM {block_playerhud_items} i WHERE i.id = itemid
                    )
                  WHERE source IN ('map', 'teacher', 'revoked')
                    AND xpawarded = 0
                    AND timecreated < :cutoff
                    AND itemid IN (SELECT id FROM {block_playerhud_items} WHERE xp > 0)
                    AND COALESCE(
                        (SELECT d.maxusage FROM {block_playerhud_drops} d WHERE d.id = dropid), 1
                    ) > 0",
                ['cutoff' => $cutoff]
            );
        }

        upgrade_block_savepoint(true, 2026070902, 'playerhud');
    }

    if ($oldversion < 2026070903) {
        // Add xpawarded to block_playerhud_quest_log: records the actual reward_xp paid out at
        // claim time, independent of the quest's current (possibly since-edited) reward_xp
        // value. Mirrors the same column already added to block_playerhud_inventory.
        $table = new \xmldb_table('block_playerhud_quest_log');
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

        // Backfill rows that already existed when this column was added, using the quest's
        // current reward_xp — the same value the audit log used to read on the fly. This runs
        // in the same atomic step as add_field(), before any request can reach the new
        // xpawarded-aware code, so (unlike the inventory backfill above) no site could already
        // hold a row written by that new code: this is the first time this column exists
        // anywhere, so every row seen here safely predates it.
        $DB->execute(
            "UPDATE {block_playerhud_quest_log}
                SET xpawarded = (
                    SELECT q.reward_xp FROM {block_playerhud_quests} q WHERE q.id = questid
                )
              WHERE questid IN (SELECT id FROM {block_playerhud_quests})"
        );

        upgrade_block_savepoint(true, 2026070903, 'playerhud');
    }

    if ($oldversion < 2026081800) {
        // Add value to block_playerhud_drops: quantity granted per collection, independent
        // of maxusage (how many times the drop can be collected). Default 1 preserves the
        // exact behaviour of every drop that already exists.
        $table = new \xmldb_table('block_playerhud_drops');
        $field = new \xmldb_field(
            'value',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'maxusage'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add reward_itemqty to block_playerhud_quests: quantity of reward_itemid granted on
        // claim. Default 1 preserves the exact behaviour of every quest that already exists.
        $table = new \xmldb_table('block_playerhud_quests');
        $field = new \xmldb_field(
            'reward_itemqty',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'reward_itemid'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Create block_playerhud_stack: current owned quantity of an item per user, one row
        // per user+item. Alongside block_playerhud_stack_log below, this is the new storage
        // for every quantity granted/consumed from now on. block_playerhud_inventory is left
        // untouched — no data is migrated into these tables; existing quantity keeps being
        // read from it. See get_available_quantity() in classes/local/external_items.php.
        $table = new \xmldb_table('block_playerhud_stack');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('qty', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('user_item', XMLDB_INDEX_UNIQUE, ['userid', 'itemid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create block_playerhud_stack_log: append-only ledger, one row per grant/consume
        // action (not per unit) — feeds the audit history tab and, via dropid, the drop
        // pickup-limit/cooldown check (drop_guard::check_pickup_allowed()).
        $table = new \xmldb_table('block_playerhud_stack_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dropid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('delta', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('xpawarded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('user_item_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'itemid', 'timecreated']);
        $table->add_index('drop_user', XMLDB_INDEX_NOTUNIQUE, ['dropid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026081800, 'playerhud');
    }

    return true;
}
