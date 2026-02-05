<?php
namespace block_playerhud\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

/**
 * Privacy provider for block_playerhud.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * 1. METADADOS: Define quais tabelas guardam dados pessoais.
     */
    public static function get_metadata(collection $collection): collection {
        // Dados Principais
        $collection->add_database_table('block_playerhud_user', [
            'currentxp' => 'privacy:metadata:playerhud_user:currentxp',
            'ranking_visibility' => 'privacy:metadata:playerhud_user:ranking_visibility',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playerhud_user');

        // Inventário
        $collection->add_database_table('block_playerhud_inventory', [
            'itemid' => 'privacy:metadata:inventory:itemid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:inventory');

        // Progresso RPG (Karma, Classes, História)
        $collection->add_database_table('block_playerhud_rpg_progress', [
            'classid' => 'privacy:metadata:rpg:classid',
            'karma' => 'privacy:metadata:rpg:karma',
            'current_nodes' => 'privacy:metadata:rpg:nodes',
            'completed_chapters' => 'privacy:metadata:rpg:chapters',
        ], 'privacy:metadata:rpg');

        // Logs
        $collection->add_database_table('block_playerhud_quest_log', [
            'questid' => 'privacy:metadata:quest_log:questid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:quest_log');

        $collection->add_database_table('block_playerhud_trade_log', [
            'tradeid' => 'privacy:metadata:trade_log:tradeid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:trade_log');

        $collection->add_database_table('block_playerhud_ai_logs', [
            'action_type' => 'privacy:metadata:ai_logs:action',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:ai_logs');

        return $collection;
    }

    /**
     * 2. CONTEXTOS: Encontra onde o usuário tem dados.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        
        $sql = "SELECT ctx.id
                  FROM {block_playerhud_user} u
                  JOIN {block_instances} bi ON u.blockinstanceid = bi.id
                  JOIN {context} ctx ON (ctx.instanceid = bi.id AND ctx.contextlevel = :blocklevel)
                 WHERE u.userid = :userid";
        
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'blocklevel' => CONTEXT_BLOCK]);

        return $contextlist;
    }

    /**
     * 3. USERLIST: Encontra QUAIS usuários têm dados em um contexto específico.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_block) {
            return;
        }

        $params = ['instanceid' => $context->instanceid];

        // Usuários com perfil
        $sql = "SELECT userid FROM {block_playerhud_user} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Usuários com progresso RPG
        $sql_rpg = "SELECT userid FROM {block_playerhud_rpg_progress} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql_rpg, $params);

        // Usuários com logs de IA
        $sql_ai = "SELECT userid FROM {block_playerhud_ai_logs} WHERE blockinstanceid = :instanceid";
        $userlist->add_from_sql('userid', $sql_ai, $params);
    }

    /**
     * 4. EXPORTAÇÃO: Exporta os dados para o zip do GDPR.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $contexts = $contextlist->get_contexts();
        $userid = $contextlist->get_userid();

        foreach ($contexts as $context) {
            if ($context->contextlevel != CONTEXT_BLOCK) {
                continue;
            }

            $instanceid = $context->instanceid;

            // A. Perfil Geral
            $player = $DB->get_record('block_playerhud_user', ['blockinstanceid' => $instanceid, 'userid' => $userid]);
            if ($player) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), 'Profile'],
                    (object) [
                        'currentxp' => $player->currentxp,
                        'level_progress' => $player->enable_gamification ? 'Enabled' : 'Disabled',
                        'joined' => transform::datetime($player->timecreated),
                    ]
                );
            }

            // B. Progresso RPG
            $rpg = $DB->get_record('block_playerhud_rpg_progress', ['blockinstanceid' => $instanceid, 'userid' => $userid]);
            if ($rpg) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), 'RPG Progress'],
                    (object) [
                        'class_id' => $rpg->classid,
                        'karma' => $rpg->karma,
                        'history' => $rpg->current_nodes,
                        'completed_chapters' => $rpg->completed_chapters
                    ]
                );
            }

            // C. Inventário
            $sql = "SELECT inv.* FROM {block_playerhud_inventory} inv
                      JOIN {block_playerhud_items} it ON inv.itemid = it.id
                     WHERE inv.userid = :userid AND it.blockinstanceid = :instanceid";
            
            $inventory = $DB->get_records_sql($sql, ['userid' => $userid, 'instanceid' => $instanceid]);

            $data = [];
            foreach ($inventory as $inv) {
                $data[] = [
                    'item_id' => $inv->itemid,
                    'collected_on' => transform::datetime($inv->timecreated)
                ];
            }
            if (!empty($data)) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_playerhud'), 'Inventory'],
                    (object) ['items' => $data]
                );
            }
        }
    }

    /**
     * 5. EXCLUSÃO TOTAL: Apaga tudo de um contexto (Curso/Bloco apagado).
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }
        $instanceid = $context->instanceid;

        // Apaga tabelas diretas
        $DB->delete_records('block_playerhud_user', ['blockinstanceid' => $instanceid]);
        $DB->delete_records('block_playerhud_rpg_progress', ['blockinstanceid' => $instanceid]);
        $DB->delete_records('block_playerhud_ai_logs', ['blockinstanceid' => $instanceid]);
        
        // Apaga inventário (Busca itens do bloco)
        $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid], '', 'id');
        if ($items) {
            $itemids = array_keys($items);
            list($insql, $inparams) = $DB->get_in_or_equal($itemids);
            $DB->delete_records_select('block_playerhud_inventory', "itemid $insql", $inparams);
        }

        // Apaga logs de Quests
        $quests = $DB->get_records('block_playerhud_quests', ['blockinstanceid' => $instanceid], '', 'id');
        if ($quests) {
            $questids = array_keys($quests);
            list($qinsql, $qinparams) = $DB->get_in_or_equal($questids);
            $DB->delete_records_select('block_playerhud_quest_log', "questid $qinsql", $qinparams);
        }
        
        // Apaga logs de Trades
        $trades = $DB->get_records('block_playerhud_trades', ['blockinstanceid' => $instanceid], '', 'id');
        if ($trades) {
            $tradeids = array_keys($trades);
            list($tinsql, $tinparams) = $DB->get_in_or_equal($tradeids);
            $DB->delete_records_select('block_playerhud_trade_log', "tradeid $tinsql", $tinparams);
        }
    }

    /**
     * 6. EXCLUSÃO INDIVIDUAL: Apaga dados de um único usuário.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_userid();
        $userids = [$userid];
        
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_BLOCK) {
                self::delete_data_for_user_list_in_context($context->instanceid, $userids);
            }
        }
    }

    /**
     * 7. EXCLUSÃO EM MASSA: Apaga dados de VÁRIOS usuários.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_BLOCK) {
            return;
        }
        self::delete_data_for_user_list_in_context($context->instanceid, $userlist->get_userids());
    }

    /**
     * HELPER: Função interna para exclusão por lista de IDs.
     */
    protected static function delete_data_for_user_list_in_context(int $instanceid, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        list($usql, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge(['instanceid' => $instanceid], $uparams);

        // 1. Apaga tabelas principais
        $DB->delete_records_select('block_playerhud_user', "blockinstanceid = :instanceid AND userid $usql", $params);
        $DB->delete_records_select('block_playerhud_rpg_progress', "blockinstanceid = :instanceid AND userid $usql", $params);
        $DB->delete_records_select('block_playerhud_ai_logs', "blockinstanceid = :instanceid AND userid $usql", $params);

        // 2. Apaga Inventário (JOIN com Items)
        $sql_inv = "SELECT inv.id FROM {block_playerhud_inventory} inv
                    JOIN {block_playerhud_items} it ON inv.itemid = it.id
                    WHERE it.blockinstanceid = :instanceid AND inv.userid $usql";
        $inv_records = $DB->get_records_sql($sql_inv, $params);
        if ($inv_records) {
            $inv_ids = array_keys($inv_records);
            list($delsql, $delparams) = $DB->get_in_or_equal($inv_ids);
            $DB->delete_records_select('block_playerhud_inventory', "id $delsql", $delparams);
        }

        // 3. Apaga Logs de Quest
        $sql_quest = "SELECT ql.id FROM {block_playerhud_quest_log} ql
                      JOIN {block_playerhud_quests} q ON ql.questid = q.id
                      WHERE q.blockinstanceid = :instanceid AND ql.userid $usql";
        $quest_records = $DB->get_records_sql($sql_quest, $params);
        if ($quest_records) {
            $ql_ids = array_keys($quest_records);
            list($qdelsql, $qdelparams) = $DB->get_in_or_equal($ql_ids);
            $DB->delete_records_select('block_playerhud_quest_log', "id $qdelsql", $qdelparams);
        }

        // 4. Apaga Logs de Trade
        $sql_trade = "SELECT tl.id FROM {block_playerhud_trade_log} tl
                      JOIN {block_playerhud_trades} t ON tl.tradeid = t.id
                      WHERE t.blockinstanceid = :instanceid AND tl.userid $usql";
        $trade_records = $DB->get_records_sql($sql_trade, $params);
        if ($trade_records) {
            $tl_ids = array_keys($trade_records);
            list($tdelsql, $tdelparams) = $DB->get_in_or_equal($tl_ids);
            $DB->delete_records_select('block_playerhud_trade_log', "id $tdelsql", $tdelparams);
        }
    }
}
