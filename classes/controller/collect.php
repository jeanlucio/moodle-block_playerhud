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
            $cooldown_deadline = 0;

            if ($isajax) {
                // A. Dados do Jogador (XP/Level atualizados pós-transação)
                // Precisamos recarregar para pegar o XP novo
                $player = \block_playerhud\game::get_player($instanceid, $USER->id);
                
                // Carrega config para cálculo exato dos níveis
                $bi = $DB->get_record('block_instances', ['id' => $instanceid]);
                $config = unserialize(base64_decode($bi->configdata));
                if (!$config) $config = new \stdClass();

                $stats = \block_playerhud\game::get_game_stats($config, $instanceid, $player->currentxp);
                
                $game_data = [
                    'currentxp' => $player->currentxp,
                    'level' => $stats['level'],
                    'progress' => $stats['progress'],
                    'total_game_xp' => $stats['total_game_xp']
                ];

                // B. Cálculo do Próximo Cooldown (Para o Timer)
                // Se o drop tem tempo de respawn definido...
                if ($drop->respawntime > 0) {
                     // Verifica se o usuário ainda pode pegar mais (limite de uso)
                     $count = $DB->count_records('block_playerhud_inventory', ['userid' => $USER->id, 'dropid' => $drop->id]);
                     
                     // Se for infinito (0) OU ainda não atingiu o limite máximo
                     if ($drop->maxusage == 0 || $count < $drop->maxusage) {
                         // O próximo tempo será AGORA + tempo de espera
                         $cooldown_deadline = time() + $drop->respawntime;
                     }
                }
            }
            // ------------------------------------

            $this->respond($isajax, true, $message, $returnurl, $game_data, $cooldown_deadline);

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
        $lastcollected = reset($inventory); // Pega o mais recente

        // Verifica Limite Máximo
        if ($drop->maxusage > 0 && $count >= $drop->maxusage) {
            throw new \moodle_exception('limitreached', 'block_playerhud');
        }

        // Verifica Cooldown (Espera)
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
            // 1. Insere Inventário
            $newinv = new \stdClass();
            $newinv->userid = $userid;
            $newinv->itemid = $item->id;
            $newinv->dropid = $drop->id;
            $newinv->timecreated = time();
            $newinv->source = 'map';
            $DB->insert_record('block_playerhud_inventory', $newinv);

            // 2. Atualiza XP do Jogador
            $xpgain = 0;
            if ($item->xp > 0) {
                $xpgain = $item->xp;
                $player = \block_playerhud\game::get_player($instanceid, $userid);
                $newxp = $player->currentxp + $xpgain;
                $DB->set_field('block_playerhud_user', 'currentxp', $newxp, ['id' => $player->id]);
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
    private function respond($ajax, $success, $message, $url, $data = null, $cooldown_deadline = 0) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success, 
                'message' => $message,
                'game_data' => $data,               // Dados para atualizar HUD
                'cooldown_deadline' => $cooldown_deadline // Dados para iniciar Timer
            ]);
            die();
        } else {
            // Fallback para redirect clássico
            redirect($url, $message, $success ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
        }
    }
}
