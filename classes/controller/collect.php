<?php
namespace block_playerhud\controller;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

class collect {

    /**
     * Executa a lógica principal de coleta.
     */
    public function execute() {
        global $USER, $DB, $PAGE;

        // 1. Parâmetros
        $instanceid = required_param('instanceid', PARAM_INT);
        $dropid     = required_param('dropid', PARAM_INT);
        $courseid   = required_param('courseid', PARAM_INT);
        $isajax     = optional_param('ajax', 0, PARAM_INT); // Detecta se é via AJAX

        // 2. Segurança
        require_login($courseid);
        require_sesskey();

        // URL de retorno (caso não seja AJAX)
        $returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);

        try {
            // Valida Drop e Item
            $drop = $DB->get_record('block_playerhud_drops', ['id' => $dropid, 'blockinstanceid' => $instanceid], '*', MUST_EXIST);
            $item = $DB->get_record('block_playerhud_items', ['id' => $drop->itemid], '*', MUST_EXIST);

            if (!$item->enabled) {
                throw new \moodle_exception('itemnotfound', 'block_playerhud');
            }

            // Verifica Regras do Jogo (Limites e Cooldown atual)
            $this->process_game_rules($drop, $USER->id);

            // Executa a Transação (Dar item e XP)
            $earned_xp = $this->process_transaction($drop, $item, $instanceid, $USER->id);

            // Prepara Mensagem de Feedback
            $msgParams = new \stdClass();
            $msgParams->name = format_string($item->name);
            $msgParams->xp = ($earned_xp > 0) ? " (+{$earned_xp} XP)" : "";
            $message = get_string('collected_msg', 'block_playerhud', $msgParams);

            // --- DADOS PARA O FRONTEND (AJAX) ---
            $game_data = null;
            $item_data = null; // Dados visuais do item para a sidebar
            $cooldown_deadline = 0;
            $limit_reached = false;

            if ($isajax) {
                // A. Dados do Jogador
                $player = \block_playerhud\game::get_player($instanceid, $USER->id);
                $bi = $DB->get_record('block_instances', ['id' => $instanceid]);
                $config = unserialize(base64_decode($bi->configdata));
                if (!$config) $config = new \stdClass();

                $stats = \block_playerhud\game::get_game_stats($config, $instanceid, $player->currentxp);
                
                // CORREÇÃO AQUI: Enviamos o total_game_xp como 'xp_target' para o JS
                $game_data = [
                    'currentxp' => $player->currentxp,
                    'level' => $stats['level'],
                    'max_levels' => $stats['max_levels'],
                    'xp_target' => $stats['total_game_xp'], // Agora aponta para o Total Geral
                    'progress' => $stats['progress'],
                    'total_game_xp' => $stats['total_game_xp'],
                    'level_class' => $stats['level_class'], // A cor do nível
                    'is_win' => ($player->currentxp >= $stats['total_game_xp'] && $stats['total_game_xp'] > 0) // Checa vitória
                ];

                // ... (restante do código permanece igual)
                // B. Verificação de Limites e Cooldown
                $count = $DB->count_records('block_playerhud_inventory', ['userid' => $USER->id, 'dropid' => $drop->id]);
                if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
                    $limit_reached = true;
                }
                if (!$limit_reached && $drop->respawntime > 0) {
                     $cooldown_deadline = time() + $drop->respawntime;
                }

                // C. Preparar dados visuais do Item (para injetar na sidebar)
                $context = \context_block::instance($instanceid);
                $media = \block_playerhud\utils::get_item_display_data($item, $context);
                $isimage = $media['is_image'] ? 1 : 0;
                $imageurl = $media['is_image'] ? $media['url'] : strip_tags($media['content']);
                $str_xp = get_string('xp', 'block_playerhud');
                $desc = !empty($item->description) ? format_text($item->description, FORMAT_HTML) : '';

                $item_data = [
                    'name' => format_string($item->name),
                    'xp' => $item->xp . ' ' . $str_xp,
                    'image' => $imageurl,
                    'isimage' => $isimage,
                    'description' => $desc,
                    'date' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
                    'timestamp' => time()
                ];
            }
            // ------------------------------------

            $this->respond($isajax, true, $message, $returnurl, $game_data, $cooldown_deadline, $limit_reached, $item_data);

        } catch (\Exception $e) {
            $this->respond($isajax, false, $e->getMessage(), $returnurl);
        }
    }

    /**
     * Verifica se o usuário pode coletar (Cooldown e Limites).
     */
    private function process_game_rules($drop, $userid) {
        global $DB;
        $inventory = $DB->get_records('block_playerhud_inventory', [
            'userid' => $userid, 
            'dropid' => $drop->id
        ], 'timecreated DESC');
        
        $count = count($inventory);
        $lastcollected = reset($inventory);

        if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        if ($lastcollected && $drop->respawntime > 0) {
            $readytime = $lastcollected->timecreated + $drop->respawntime;
            if (time() < $readytime) {
                $minutesleft = ceil(($readytime - time()) / 60);
                throw new \moodle_exception('waitmore', 'block_playerhud', '', $minutesleft);
            }
        }
    }

    /**
     * Insere no inventário e dá XP.
     */
    private function process_transaction($drop, $item, $instanceid, $userid) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        
        try {
            $newinv = new \stdClass();
            $newinv->userid = $userid;
            $newinv->itemid = $item->id;
            $newinv->dropid = $drop->id;
            $newinv->timecreated = time();
            $newinv->source = 'map';
            $DB->insert_record('block_playerhud_inventory', $newinv);

            // --- LÓGICA DE XP PROTEGIDA ---
            $xpgain = 0;
            
            // Regra de Ouro: Se o drop for infinito (maxusage == 0), o XP ganho é FORÇADO a 0.
            // Isso permite que o item tenha 100 XP (para drops finitos/quests), 
            // mas não quebre o jogo em drops infinitos.
            $is_infinite_drop = ((int)$drop->maxusage === 0);

            if ($item->xp > 0 && !$is_infinite_drop) {
                $xpgain = $item->xp;
                $player = \block_playerhud\game::get_player($instanceid, $userid);
                
                // [CORREÇÃO] Atualizar objeto completo para registrar o tempo do desempate
                $player->currentxp += $xpgain;
                $player->timemodified = time(); // Essencial para o ranking por tempo!
                
                $DB->update_record('block_playerhud_user', $player);
            }

            $transaction->allow_commit();
            return $xpgain;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Envia a resposta (JSON ou Redirect).
     */
    private function respond($ajax, $success, $message, $url, $data = null, $cooldown_deadline = 0, $limit_reached = false, $item_data = null) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success, 
                'message' => $message,
                'game_data' => $data,
                'item_data' => $item_data, // Novo campo
                'cooldown_deadline' => $cooldown_deadline,
                'limit_reached' => $limit_reached
            ]);
            die();
        } else {
            redirect($url, $message, $success ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
        }
    }
}
