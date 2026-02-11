<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main management page for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// 1. Initial configuration and parameters.
$courseid   = required_param('id', PARAM_INT);
$instanceid = required_param('instanceid', PARAM_INT);
$activetab  = optional_param('tab', 'items', PARAM_ALPHA);

// Action Parameters.
$action  = optional_param('action', '', PARAM_ALPHANUMEXT);
$itemid  = optional_param('itemid', 0, PARAM_INT);
$questid = optional_param('questid', 0, PARAM_INT);
$tradeid = optional_param('tradeid', 0, PARAM_INT);

// Sorting Parameters.
$sort = optional_param('sort', '', PARAM_ALPHA);
$dir  = optional_param('dir', 'ASC', PARAM_ALPHA);

// 2. Security checks.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);

require_login($course);

// Definimos o contexto do BLOCO, igual ao view.php
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

// Base URL for redirects.
$baseurl = new moodle_url('/blocks/playerhud/manage.php', [
    'id' => $courseid,
    'instanceid' => $instanceid
]);

// --- MOVIDO: Configuração da Página (Antes de qualquer output ou lógica de render) ---
// 2.1 Page Setup
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse'); // Garante que a gaveta de blocos apareça
$PAGE->set_title(get_string('pluginname', 'block_playerhud'));
$PAGE->set_heading(format_string($course->fullname));
// ---------------------------------------------------------------------------------

// 3. Action processing (Global Controllers).

// Action: Toggle Item Status.
if ($action == 'toggle' && $itemid && confirm_sesskey()) {
    $it = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($it) {
        $newstatus = $it->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_items', 'enabled', $newstatus, ['id' => $itemid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Toggle Quest Status.
if ($action == 'toggle_quest' && $questid && confirm_sesskey()) {
    $q = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    if ($q) {
        $newstatus = $q->enabled ? 0 : 1;
        $DB->set_field('block_playerhud_quests', 'enabled', $newstatus, ['id' => $questid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'quests', 'sort' => $sort, 'dir' => $dir]),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Item (Single).
if ($action === 'delete' && $itemid && confirm_sesskey()) {
    $item = $DB->get_record('block_playerhud_items', ['id' => $itemid, 'blockinstanceid' => $instanceid]);
    if ($item) {
        // 1. Remove XP from users holding this item.
        $sql = "SELECT userid, COUNT(id) as qtd 
                  FROM {block_playerhud_inventory} 
                 WHERE itemid = ? 
              GROUP BY userid";
        $holders = $DB->get_records_sql($sql, [$itemid]);
        
        foreach ($holders as $holder) {
            $xptoremove = $item->xp * $holder->qtd;
            // [CORREÇÃO] Atualizar timemodified para refletir a mudança de saldo
            $DB->execute(
                "UPDATE {block_playerhud_user} 
                    SET currentxp = GREATEST(0, currentxp - ?),
                        timemodified = ?
                  WHERE userid = ? AND blockinstanceid = ?",
                [$xptoremove, time(), $holder->userid, $instanceid]
            );
        }

        // 2. Delete dependencies.
        $DB->delete_records('block_playerhud_inventory', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_drops', ['itemid' => $itemid]);
        $DB->delete_records('block_playerhud_trade_reqs', ['itemid' => $itemid]); 
        $DB->delete_records('block_playerhud_trade_rewards', ['itemid' => $itemid]);
        
        // 3. Delete the item files and record.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $itemid);
        $DB->delete_records('block_playerhud_items', ['id' => $itemid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Bulk Delete Items (Multiple).
if ($action === 'bulk_delete' && confirm_sesskey()) {
    $bulkids = optional_param_array('bulk_ids', [], PARAM_INT);
    
    if (!empty($bulkids)) {
        $fs = get_file_storage();
        $deletedcount = 0;

        foreach ($bulkids as $delid) {
            $item = $DB->get_record('block_playerhud_items', ['id' => $delid, 'blockinstanceid' => $instanceid]);
            if ($item) {
                // 1. Remove XP from users.
                $sql = "SELECT userid, COUNT(id) as qtd 
                          FROM {block_playerhud_inventory} 
                         WHERE itemid = ? 
                      GROUP BY userid";
                $holders = $DB->get_records_sql($sql, [$delid]);
                
                foreach ($holders as $holder) {
                    $xptoremove = $item->xp * $holder->qtd;
                    // [CORREÇÃO] Atualizar timemodified aqui também
                    $DB->execute(
                        "UPDATE {block_playerhud_user} 
                            SET currentxp = GREATEST(0, currentxp - ?),
                                timemodified = ?
                          WHERE userid = ? AND blockinstanceid = ?",
                        [$xptoremove, time(), $holder->userid, $instanceid]
                    );
                }

                // 2. Delete dependencies.
                $DB->delete_records('block_playerhud_inventory', ['itemid' => $delid]);
                $DB->delete_records('block_playerhud_drops', ['itemid' => $delid]);
                $DB->delete_records('block_playerhud_trade_reqs', ['itemid' => $delid]); 
                $DB->delete_records('block_playerhud_trade_rewards', ['itemid' => $delid]);
                
                // 3. Delete files and record.
                $fs->delete_area_files($context->id, 'block_playerhud', 'item_image', $delid);
                $DB->delete_records('block_playerhud_items', ['id' => $delid]);
                
                $deletedcount++;
            }
        }

        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('deleted_bulk', 'block_playerhud', $deletedcount),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url($baseurl, ['tab' => 'items', 'sort' => $sort, 'dir' => $dir]),
            get_string('no_items_selected', 'block_playerhud'),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

// Action: Delete Quest.
if ($action == 'delete_quest' && $questid && confirm_sesskey()) {
    $quest = $DB->get_record('block_playerhud_quests', ['id' => $questid, 'blockinstanceid' => $instanceid]);
    
    if ($quest) {
        // 1. [NOVO] Reverter XP dos alunos que completaram
        if ($quest->reward_xp > 0) {
            $completions = $DB->get_records('block_playerhud_quest_log', ['questid' => $questid]);
            
            // Usamos time() fixo para todos nessa transação
            $now = time(); 

            foreach ($completions as $log) {
                // Remove o XP da recompensa e atualiza a data para o desempate
                $DB->execute(
                    "UPDATE {block_playerhud_user} 
                        SET currentxp = GREATEST(0, currentxp - ?),
                            timemodified = ?
                      WHERE userid = ? AND blockinstanceid = ?",
                    [$quest->reward_xp, $now, $log->userid, $instanceid]
                );
            }
        }

        // 2. Apagar registros
        $DB->delete_records('block_playerhud_quest_log', ['questid' => $questid]);
        $DB->delete_records('block_playerhud_quests', ['id' => $questid]);

        redirect(
            new moodle_url($baseurl, ['tab' => 'quests']),
            get_string('quest_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Trade.
if ($action == 'delete_trade' && $tradeid && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();
    try {
        $DB->delete_records('block_playerhud_trade_reqs', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_rewards', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trade_log', ['tradeid' => $tradeid]);
        $DB->delete_records('block_playerhud_trades', ['id' => $tradeid, 'blockinstanceid' => $instanceid]);
        $transaction->allow_commit();
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('changessaved', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Exception $e) {
        $transaction->rollback($e);
        redirect(
            new moodle_url($baseurl, ['tab' => 'trades']),
            get_string('error_msg', 'block_playerhud', $e->getMessage()),
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Action: Delete Class.
if ($action == 'delete_class') {
    $classid = optional_param('classid', 0, PARAM_INT);
    if ($classid && confirm_sesskey()) {
        $fs = get_file_storage();
        for ($i = 1; $i <= 5; $i++) {
            $fs->delete_area_files($context->id, 'block_playerhud', 'class_image_' . $i, $classid);
        }
        $DB->delete_records('block_playerhud_classes', ['id' => $classid, 'blockinstanceid' => $instanceid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'classes']),
            get_string('class_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Delete Chapter.
if ($action == 'delete_chapter') {
    $chapterid = optional_param('chapterid', 0, PARAM_INT);
    if ($chapterid && confirm_sesskey()) {
        $scenes = $DB->get_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        foreach ($scenes as $sc) {
            $DB->delete_records('block_playerhud_choices', ['nodeid' => $sc->id]);
        }
        $DB->delete_records('block_playerhud_story_nodes', ['chapterid' => $chapterid]);
        $DB->delete_records('block_playerhud_chapters', ['id' => $chapterid, 'blockinstanceid' => $instanceid]);
        redirect(
            new moodle_url($baseurl, ['tab' => 'chapters']),
            get_string('chapter_deleted', 'block_playerhud'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Action: Save API Keys.
if ($action == 'save_keys' && confirm_sesskey()) {
    $gkey = optional_param('gemini_key', '', PARAM_TEXT);
    $qkey = optional_param('groq_key', '', PARAM_TEXT);

    // In blocks, we save settings to the instance configdata.
    $config = (array) unserialize(base64_decode($bi->configdata));
    $config['apikey_gemini'] = trim($gkey);
    $config['apikey_groq'] = trim($qkey);
    
    $bi->configdata = base64_encode(serialize((object)$config));
    $DB->update_record('block_instances', $bi);
    
    redirect(
        new moodle_url($baseurl, ['tab' => 'config']),
        get_string('changessaved', 'block_playerhud'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// 4. PRE-RENDER LOGIC (Controller Strategy)
$content_html = '';

// Tenta carregar o controlador da aba
$render_class = "\\block_playerhud\\output\\manage\\tab_{$activetab}";

if (class_exists($render_class)) {
    // Instancia o renderizador da aba
    $renderer = new $render_class($instanceid, $courseid, $sort, $dir);
    
    // Se tiver lógica de processamento de formulário (ex: items/add)
    if (method_exists($renderer, 'process')) {
        $renderer->process();
    }

    // Renderiza o conteúdo da aba
    if ($renderer instanceof \templatable) {
        // Render via Mustache (Padrão Novo)
        if (method_exists($renderer, 'display')) {
             $content_html = $renderer->display();
        } else {
             // Fallback para templates padrão
             $content_html = $OUTPUT->render_from_template("block_playerhud/tab_{$activetab}", $renderer->export_for_template($OUTPUT));
        }
    } else {
        // Fallback para classes antigas (display manual, se houver)
        if (method_exists($renderer, 'display')) {
            $content_html = $renderer->display();
        }
    }
} else {
    // Aba não implementada ou arquivo faltando
    $content_html = $OUTPUT->notification(
        get_string('tab_maintenance', 'block_playerhud', ucfirst($activetab)),
        'info'
    );
}

// (O Page Setup foi movido para o topo para garantir layout correto)

echo $OUTPUT->header();

// Definição das Abas (V1.0 - Funcionalidades em construção ocultas)
$tabs_def = [
    'items'    => ['icon' => '📚', 'text' => get_string('tab_items', 'block_playerhud')],
    
    // --- Ocultos para lançamento ---
    // 'trades'   => ['icon' => '⚖️', 'text' => get_string('tab_trades', 'block_playerhud')],
    // 'quests'   => ['icon' => '📜', 'text' => get_string('tab_quests', 'block_playerhud')],
    // 'chapters' => ['icon' => '📖', 'text' => get_string('tab_chapters', 'block_playerhud')],
    // 'classes'  => ['icon' => '🦸', 'text' => get_string('tab_classes', 'block_playerhud')],
    // 'reports'  => ['icon' => '📊', 'text' => get_string('tab_reports', 'block_playerhud')],
    // ----------------------------

    'config'   => ['icon' => '🛠️', 'text' => get_string('tab_config', 'block_playerhud')],
];

$tabs_data = [];
foreach ($tabs_def as $key => $data) {
    $tabs_data[] = [
        'active' => ($activetab == $key),
        'url' => (new moodle_url($baseurl, ['tab' => $key]))->out(false),
        'icon' => $data['icon'],
        'text' => $data['text']
    ];
}

// Dados para o Layout
$layout_data = [
    'str_title' => get_string('master_panel', 'block_playerhud'),
    'url_backpack' => (new moodle_url('/blocks/playerhud/view.php', ['id' => $courseid, 'instanceid' => $instanceid]))->out(false),
    'str_backpack' => get_string('openbackpack', 'block_playerhud'),
    'url_course' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'str_back_course' => get_string('back_to_course', 'block_playerhud'),
    'tabs' => $tabs_data,
    'content_html' => $content_html
];

echo $OUTPUT->render_from_template('block_playerhud/manage_layout', $layout_data);

echo $OUTPUT->footer();
