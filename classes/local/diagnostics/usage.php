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
 * Site-wide adoption and engagement figures of the block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local\diagnostics;

/**
 * Counts how the block is used across the site, for the administrator's diagnostics page.
 *
 * Every figure covers live instances only: rows of removed instances are reported separately
 * by {@see integrity}. Only aggregates are returned, never data about a single user.
 *
 * @package    block_playerhud
 */
class usage {
    /** @var int Days covered by the activity figures. */
    public const WINDOWDAYS = 90;

    /** @var string[] Feature flags counted per instance; all default to on, as in view.php. */
    public const FEATURES = ['enable_rpg', 'enable_quests', 'enable_ranking', 'enable_items'];

    /**
     * How widely the block is deployed.
     *
     * Counted per course: the block allows one instance per course and is only offered on course
     * pages. Courses fall in exactly one of three groups (withitems, playersonly, empty), so the
     * three add up to the course total. Blocks an earlier release let users put on their
     * Dashboard are counted apart, as dashboard, and left out of everything else.
     *
     * @return array Keys: courses, withitems, playersonly, empty, features (flag => count) and dashboard.
     */
    public static function get_adoption(): array {
        global $DB;

        $incourse = "FROM {block_instances} bi
                     JOIN {context} ctx ON ctx.id = bi.parentcontextid AND ctx.contextlevel = :courselevel
                    WHERE bi.blockname = :blockname";
        $params = ['courselevel' => CONTEXT_COURSE, 'blockname' => 'playerhud'];

        $hasitems = "EXISTS (SELECT 1 FROM {block_playerhud_items} i WHERE i.blockinstanceid = bi.id)";
        $hasplayers = "EXISTS (SELECT 1 FROM {block_playerhud_user} u WHERE u.blockinstanceid = bi.id)";
        $counts = $DB->get_record_sql(
            "SELECT COUNT(DISTINCT ctx.instanceid) AS courses,
                    SUM(CASE WHEN $hasitems THEN 1 ELSE 0 END) AS withitems,
                    SUM(CASE WHEN NOT $hasitems AND $hasplayers THEN 1 ELSE 0 END) AS playersonly,
                    SUM(CASE WHEN NOT $hasitems AND NOT $hasplayers THEN 1 ELSE 0 END) AS emptyinstances
             $incourse",
            $params
        );

        $features = array_fill_keys(self::FEATURES, 0);
        $configs = $DB->get_recordset_sql("SELECT bi.id, bi.configdata $incourse", $params);
        foreach ($configs as $instance) {
            $config = \block_playerhud\utils::get_block_config($instance);
            foreach (self::FEATURES as $flag) {
                if (!isset($config->$flag) || !empty($config->$flag)) {
                    $features[$flag]++;
                }
            }
        }
        $configs->close();

        $dashboard = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_instances} bi
               JOIN {context} ctx ON ctx.id = bi.parentcontextid AND ctx.contextlevel = :userlevel
              WHERE bi.blockname = :blockname",
            ['userlevel' => CONTEXT_USER, 'blockname' => 'playerhud']
        );

        return [
            'courses' => (int) $counts->courses,
            'withitems' => (int) $counts->withitems,
            'playersonly' => (int) $counts->playersonly,
            'empty' => (int) $counts->emptyinstances,
            'features' => $features,
            'dashboard' => (int) $dashboard,
        ];
    }

    /**
     * What players have done, counted from a given moment.
     *
     * @param int $since Start of the activity window (timestamp).
     * @return array Keys: players, active, optedout, xp, itemsreceived, questscompleted,
     *               trades, airequests (provider => count) and wizardruns.
     */
    public static function get_engagement(int $since): array {
        global $DB;

        $live = "JOIN {block_instances} bi ON bi.id = %s AND bi.blockname = :blockname";
        $params = ['blockname' => 'playerhud', 'since' => $since];

        // A player's timemodified only advances on an XP gain, so it marks their last gain.
        $players = $DB->get_record_sql(
            "SELECT COUNT(1) AS players,
                    SUM(CASE WHEN u.currentxp > 0 AND u.timemodified >= :since THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN u.enable_gamification = 0 THEN 1 ELSE 0 END) AS optedout,
                    SUM(u.currentxp) AS xp
               FROM {block_playerhud_user} u
             " . sprintf($live, 'u.blockinstanceid'),
            $params
        );

        // Items arrive through both storage generations: one legacy inventory row per unit,
        // or one positive quantity-ledger entry in the new engine.
        $legacyitems = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_playerhud_inventory} inv
               JOIN {block_playerhud_items} it ON it.id = inv.itemid
             " . sprintf($live, 'it.blockinstanceid') . "
              WHERE inv.timecreated >= :since",
            $params
        );
        $ledgeritems = $DB->get_field_sql(
            "SELECT COALESCE(SUM(sl.delta), 0)
               FROM {block_playerhud_stack_log} sl
               JOIN {block_playerhud_items} it ON it.id = sl.itemid
             " . sprintf($live, 'it.blockinstanceid') . "
              WHERE sl.delta > 0 AND sl.timecreated >= :since",
            $params
        );

        $quests = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_playerhud_quest_log} ql
               JOIN {block_playerhud_quests} q ON q.id = ql.questid
             " . sprintf($live, 'q.blockinstanceid') . "
              WHERE ql.timecreated >= :since",
            $params
        );
        $trades = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_playerhud_trade_log} tl
               JOIN {block_playerhud_trades} t ON t.id = tl.tradeid
             " . sprintf($live, 't.blockinstanceid') . "
              WHERE tl.timecreated >= :since",
            $params
        );
        $airequests = $DB->get_records_sql_menu(
            "SELECT a.ai_provider, COUNT(1)
               FROM {block_playerhud_ai_logs} a
             " . sprintf($live, 'a.blockinstanceid') . "
              WHERE a.timecreated >= :since
           GROUP BY a.ai_provider
           ORDER BY COUNT(1) DESC, a.ai_provider",
            $params
        );
        $wizardruns = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {block_playerhud_wizard_runs} w
             " . sprintf($live, 'w.blockinstanceid') . "
              WHERE w.timecreated >= :since",
            $params
        );

        return [
            'players' => (int) $players->players,
            'active' => (int) $players->active,
            'optedout' => (int) $players->optedout,
            'xp' => (int) $players->xp,
            'itemsreceived' => (int) $legacyitems + (int) $ledgeritems,
            'questscompleted' => (int) $quests,
            'trades' => (int) $trades,
            'airequests' => array_map('intval', $airequests),
            'wizardruns' => (int) $wizardruns,
        ];
    }
}
