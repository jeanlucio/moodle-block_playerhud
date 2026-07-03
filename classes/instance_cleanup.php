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
 * Deletes every row a block instance owns across the plugin's own tables.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

/**
 * Class instance_cleanup
 *
 * Moodle core drops block_instances, block_positions, the block context (and its files)
 * and the generic hide/dock user_preferences automatically when a block instance is
 * deleted, but it has no knowledge of a plugin's own tables. block_base::instance_delete()
 * is exactly the hook reserved for that, so every row this plugin ever wrote for the
 * deleted instance is removed here.
 *
 * @package block_playerhud
 */
class instance_cleanup {
    /**
     * Deletes every row owned by a block instance, across all of this plugin's tables.
     *
     * Child tables that only reference a parent id (not blockinstanceid directly) are
     * deleted first, so no dangling row is ever left behind mid-cleanup.
     *
     * @param int $blockinstanceid The block instance being deleted.
     * @return void
     */
    public static function delete_instance_data(int $blockinstanceid): void {
        global $DB;

        $params = ['biid' => $blockinstanceid];

        // Choices -> story nodes -> chapters.
        $DB->delete_records_select(
            'block_playerhud_choices',
            'nodeid IN (SELECT sn.id
                          FROM {block_playerhud_story_nodes} sn
                          JOIN {block_playerhud_chapters} c ON c.id = sn.chapterid
                         WHERE c.blockinstanceid = :biid)',
            $params
        );
        $DB->delete_records_select(
            'block_playerhud_story_nodes',
            'chapterid IN (SELECT id FROM {block_playerhud_chapters} WHERE blockinstanceid = :biid)',
            $params
        );

        // Inventory -> items.
        $DB->delete_records_select(
            'block_playerhud_inventory',
            'itemid IN (SELECT id FROM {block_playerhud_items} WHERE blockinstanceid = :biid)',
            $params
        );

        // Quest log -> quests.
        $DB->delete_records_select(
            'block_playerhud_quest_log',
            'questid IN (SELECT id FROM {block_playerhud_quests} WHERE blockinstanceid = :biid)',
            $params
        );

        // Trade requirements, rewards and transaction log -> trades.
        $DB->delete_records_select(
            'block_playerhud_trade_reqs',
            'tradeid IN (SELECT id FROM {block_playerhud_trades} WHERE blockinstanceid = :biid)',
            $params
        );
        $DB->delete_records_select(
            'block_playerhud_trade_rewards',
            'tradeid IN (SELECT id FROM {block_playerhud_trades} WHERE blockinstanceid = :biid)',
            $params
        );
        $DB->delete_records_select(
            'block_playerhud_trade_log',
            'tradeid IN (SELECT id FROM {block_playerhud_trades} WHERE blockinstanceid = :biid)',
            $params
        );

        // Wizard rollback manifest -> wizard runs.
        $DB->delete_records_select(
            'block_playerhud_wizard_objects',
            'runid IN (SELECT id FROM {block_playerhud_wizard_runs} WHERE blockinstanceid = :biid)',
            $params
        );
        $DB->delete_records_select(
            'block_playerhud_wizard_shortcodes',
            'runid IN (SELECT id FROM {block_playerhud_wizard_runs} WHERE blockinstanceid = :biid)',
            $params
        );

        // Tables keyed directly on blockinstanceid.
        $DB->delete_records('block_playerhud_user', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_items', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_drops', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_classes', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_rpg_progress', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_quests', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_chapters', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_trades', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_ai_logs', ['blockinstanceid' => $blockinstanceid]);
        $DB->delete_records('block_playerhud_wizard_runs', ['blockinstanceid' => $blockinstanceid]);

        // Per-instance user preference (avatar equipped in this specific instance). The BYOK
        // AI keys (block_playerhud_gemini_key and friends) are intentionally left untouched:
        // they are global to the user across every block instance, and are already covered
        // by the Privacy API's delete_data_for_user() for full account erasure.
        $DB->delete_records('user_preferences', ['name' => 'block_playerhud_avatar_' . $blockinstanceid]);
    }
}
