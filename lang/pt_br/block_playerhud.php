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
 * Portuguese (Brazil) language strings for PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Files.LineLength

$string['actions'] = 'Ações';
$string['add_cost_item'] = 'Adicionar item de requisito';
$string['add_reward_item'] = 'Adicionar item de recompensa';
$string['add_trade'] = 'Nova Oferta de Negociação';
$string['ai_btn_conjure'] = 'Conjurar!';
$string['ai_btn_create'] = 'Criar Item Mágico';
$string['ai_create_drop'] = 'Gerar local de Drop?';
$string['ai_created_count'] = '{$a} item(ns) criado(s)!';
$string['ai_creating'] = 'Conjurando...';
$string['ai_drop_settings'] = 'Configurações de Drop';
$string['ai_error_apikey'] = 'Chave da API Gemini não configurada nas configurações do plugin.';
$string['ai_error_no_keys'] = 'Nenhuma chave de IA configurada (nem pelo Professor, nem pela Instituição).';
$string['ai_error_offline'] = 'IA Offline: {$a}';
$string['ai_error_parsing'] = 'O feitiço da IA falhou (Resposta JSON inválida). Tente novamente.';
$string['ai_ex_desc'] = 'Descrição Factual...';
$string['ai_ex_loc'] = 'Local';
$string['ai_ex_name'] = 'Nome';
$string['ai_info_infinite_xp'] = 'Itens infinitos foram criados com 0 XP para manter o equilíbrio.';
$string['ai_item_list_label'] = 'Item(ns):';
$string['ai_json_instruction'] = 'Retorne APENAS um JSON válido seguindo esta estrutura:';
$string['ai_prompt_ctx_easy'] = 'Contexto: O jogo precisa de itens comuns ou introdutórios.';
$string['ai_prompt_ctx_hard'] = 'Contexto: O jogo precisa de itens de alto valor (raros/complexos).';
$string['ai_prompt_item'] = 'Crie item RPG. Tema: \'{$a->theme}\'. JSON: {"name":"", "description":"", "emoji":"", "xp":{$a->xp}, "location_name":"Localização"}. Responda em Português.';
$string['ai_prompt_tech_xp'] = 'Requisito Técnico: O valor interno de XP deste item será {$a} (NÃO escreva este número na descrição, é apenas para seu controle de "valor" ou "raridade" do objeto).';
$string['ai_prompt_theme_item'] = 'Tema (ex: Mitologia, Química)';
$string['ai_reply_lang'] = 'Responda estritamente no idioma: {$a}.';
$string['ai_rnd_xp'] = 'Vazio = Aleatório';
$string['ai_role_item'] = 'Atue como um Especialista no Assunto e Educador.';
$string['ai_rules_item'] = 'IMPORTANTE: Crie uma descrição factual, realista e educacional do item. REGRAS: 1. O "nome" deve ser curto (máximo de 4 palavras). 2. A "descrição" deve ser extremamente concisa e direta (máximo de 150 caracteres). 3. NÃO invente histórias de fantasia ou "lore", e NÃO mencione XP, níveis ou mecânicas de jogo.';
$string['ai_success'] = 'Item criado com sucesso!';
$string['ai_task_multi'] = 'Crie {$a->count} itens reais e distintos relacionados ao tema: \'{$a->theme}\'.';
$string['ai_task_single'] = 'Crie UM item real relacionado ao tema: \'{$a}\'.';
$string['ai_theme_placeholder'] = 'Ex: História da Arte';
$string['ai_tip_balanced'] = 'Este item ajuda a balancear a economia do jogo.';
$string['ai_validation_theme'] = 'Por favor, digite um tema para a história!';
$string['ai_warn_overflow'] = 'Atenção: Com este item, o jogo tem {$a}% a mais de XP do que o necessário.';
$string['api_key_placeholder'] = 'Deixe vazio para usar a da instituição';
$string['api_settings_desc'] = 'Se você tiver suas próprias chaves de API (Gemini ou Groq), insira-as aqui. O sistema usará suas chaves como prioridade. Se deixar em branco, o sistema tentará usar a chave global da instituição (se houver).';
$string['api_settings_title'] = 'Suas Chaves de IA (Opcional)';
$string['average'] = 'Média';
$string['back'] = 'Voltar';
$string['back_to_course'] = 'Voltar ao Curso';
$string['back_to_library'] = 'Voltar para a Biblioteca';
$string['bal_msg_easy'] = 'Fácil demais! Há <strong>{$a->total} XP</strong> disponíveis. O aluno chegará ao topo muito rápido.';
$string['bal_msg_empty'] = 'O jogo está vazio. Crie itens para começar.';
$string['bal_msg_hard'] = 'Difícil! Há apenas <strong>{$a->total} XP</strong> disponíveis, mas o aluno precisa de <strong>{$a->req} XP</strong> para zerar.';
$string['bal_msg_perfect'] = 'Excelente! O jogo está balanceado ({$a->ratio}% de cobertura).';
$string['cancel'] = 'Cancelar';
$string['changessaved'] = 'Alterações salvas com sucesso.';
$string['choice_text'] = 'Texto do Botão';
$string['class_empty'] = 'Nenhuma classe atribuída ainda.';
$string['class_name'] = 'Classe RPG';
$string['click_to_hide'] = 'Clique para ocultar';
$string['click_to_show'] = 'Clique para mostrar';
$string['close'] = 'Fechar';
$string['col_number'] = 'N.';
$string['collected'] = 'Coletado';
$string['collected_msg'] = 'Você coletou: {$a->name}{$a->xp}!';
$string['completed'] = 'Concluído';
$string['confirm_bulk_delete'] = 'Tem certeza que deseja excluir os itens selecionados?';
$string['confirm_delete'] = 'Tem certeza que deseja excluir isto?';
$string['confirm_disable'] = 'Tem certeza? Seu HUD desaparecerá até que você o reative.';
$string['confirm_revoke'] = 'Tem certeza que deseja remover este item do aluno? O XP correspondente será deduzido da pontuação total.';
$string['connector_and'] = ' e ';
$string['default_drop_name'] = 'Drop Gerado';
$string['delete'] = 'Excluir';
$string['delete_n_items'] = 'Excluir %d itens';
$string['delete_selected'] = 'Excluir selecionados';
$string['deleted'] = 'Item excluído.';
$string['deleted_bulk'] = '{$a} itens excluídos com sucesso.';
$string['description'] = 'Descrição';
$string['description_help'] = 'Um texto curto descrevendo o item (história, funcionalidade ou sabor). Isso aparecerá quando o aluno passar o mouse sobre o item na Mochila.';
$string['details'] = 'Detalhes';
$string['disable_exit'] = 'Desativar e Sair';
$string['drop_config_header'] = 'Configurar Drop para: {$a}';
$string['drop_configured_msg'] = 'Drop configurado!';
$string['drop_max_qty'] = 'Qtd Máxima';
$string['drop_name_default'] = 'Ex: dentro do castelo';
$string['drop_name_label'] = 'Localização / Nome';
$string['drop_new_title'] = 'Nova Localização';
$string['drop_rules_header'] = 'Regras de Coleta';
$string['drop_save_btn'] = 'Salvar Localização';
$string['drop_supplies_label'] = 'Suprimentos';
$string['drop_unlimited_label'] = 'Ilimitado';
$string['drop_unlimited_xp_warning'] = '<strong>Nota:</strong> Drops infinitos não concedem XP. Mesmo que este item tenha valor de XP, <strong>este drop específico dará 0 XP</strong>.';
$string['dropcode'] = 'Shortcode';
$string['dropcode_help'] = 'Copie este código e cole em qualquer lugar do seu curso (Rótulos, Páginas, Fóruns, Tarefas, etc). Quando o aluno vir este código, o botão "Coletar" aparecerá. Dica: Se você colocar o nome da atividade na localização, o sistema gera automaticamente o link para que você possa acessá-la rapidamente.';
$string['drops'] = 'Drops (Localização)';
$string['drops_btn_new'] = 'Nova Localização de Drop';
$string['drops_col_actions'] = 'Ações';
$string['drops_col_code'] = 'Código';
$string['drops_col_id'] = 'ID';
$string['drops_col_name'] = 'Local (Nome)';
$string['drops_col_qty'] = 'Qtd';
$string['drops_col_time'] = 'Tempo';
$string['drops_confirm_delete'] = 'Excluir esta localização de drop?';
$string['drops_empty'] = 'Nenhum drop configurado.';
$string['drops_empty_desc'] = 'Crie um "Drop" para gerar um código e esconder este item no seu curso.';
$string['drops_header_managedrops'] = 'Gerenciando Drops para:';
$string['drops_immediate'] = 'Imediato';
$string['drops_summary'] = 'Você possui {$a} drops espalhados para este item.';
$string['edit'] = 'Editar';
$string['empty'] = '- Vazio -';
$string['enable_ranking'] = 'Ativar Ranking';
$string['enable_ranking_help'] = 'Se ativado, uma aba "Ranking" estará disponível. Os alunos podem ver sua posição individualmente ou por grupo. Os alunos também podem optar por sair se preferirem privacidade.';
$string['enabled'] = 'Ativo?';
$string['enabled_help'] = 'Se desmarcado, este item não aparecerá no jogo, não poderá ser coletado e desaparecerá do inventário dos alunos se eles já o tiverem (até ser ativado novamente).';
$string['err_clipboard'] = 'Não foi possível copiar para a área de transferência. Seu navegador pode estar bloqueando esta ação.';
$string['error_connection'] = 'Erro de conexão.';
$string['error_msg'] = 'Erro: {$a}';
$string['error_quest_already_claimed'] = 'Recompensa já resgatada.';
$string['error_quest_invalid'] = 'Missão inválida.';
$string['error_quest_requirements'] = 'Requisitos não atendidos.';
$string['error_service_code'] = 'Erro no serviço {$a->service}: {$a->code}';
$string['error_trade_class'] = 'Sua classe de RPG não pode realizar esta troca.';
$string['error_trade_group'] = 'Esta troca está restrita a outro grupo.';
$string['error_trade_insufficient'] = 'Itens insuficientes. Você está faltando {$a->missing}x {$a->name}.';
$string['error_trade_invalid'] = 'Troca inválida ou inativa.';
$string['error_trade_lock'] = 'Transação em andamento. Aguarde um momento e tente novamente.';
$string['error_trade_onetime'] = 'Você já realizou esta troca. Ela só pode ser feita uma vez.';
$string['error_unknown_mode'] = 'Modo de geração desconhecido.';
$string['export_csv'] = 'Exportar CSV';
$string['export_excel'] = 'Exportar Excel';
$string['game_balance'] = 'Saúde da Economia (Balanceamento)';
$string['gemini_apikey'] = 'Chave API Google Gemini';
$string['gemini_apikey_desc'] = 'Insira sua Chave de API para ativar a funcionalidade de criação automática de itens usando IA.';
$string['gen_btn'] = '🪄 Gerar Código';
$string['gen_code_label'] = 'Código Final';
$string['gen_copied'] = 'Copiado!';
$string['gen_copy'] = 'Copiar';
$string['gen_copy_short'] = 'Copiar';
$string['gen_customize'] = 'Personalizar';
$string['gen_leave_empty'] = 'Deixe vazio para usar o padrão.';
$string['gen_link_help'] = 'Digite o que o aluno deve ler.';
$string['gen_link_label'] = 'Texto do Link';
$string['gen_link_placeholder'] = 'Clique aqui para coletar';
$string['gen_preview'] = 'Prévia';
$string['gen_style'] = 'Estilo de Exibição';
$string['gen_style_card'] = 'Cartão Completo';
$string['gen_style_card_desc'] = 'Ícone + Nome + Botão (Padrão)';
$string['gen_style_image'] = 'Apenas Imagem';
$string['gen_style_image_desc'] = 'Ícone flutuante clicável.';
$string['gen_style_text'] = 'Apenas Texto';
$string['gen_style_text_desc'] = 'Um link de texto simples.';
$string['gen_title'] = 'Gerador de Código';
$string['gen_yours'] = 'Possui: 0';
$string['grant_item_select'] = 'Conceder Item';
$string['great'] = 'Legal!';
$string['groq_apikey'] = 'Chave da API Groq';
$string['groq_apikey_desc'] = 'Insira sua chave da Groq Cloud para redundância gratuita.';
$string['group'] = 'Grupo';
$string['help_btn'] = 'Ajuda';
$string['help_content_label'] = 'Conteúdo de Ajuda Personalizado';
$string['help_content_label_help'] = 'Personalize as instruções que os alunos veem na aba Ajuda. Limpe este campo ou marque a caixa abaixo para restaurar o padrão do sistema.';
$string['help_pagedefault'] = '<div class="alert alert-info shadow-sm mb-4">
    <div class="d-flex align-items-center">
        <div class="me-3">
            <i class="fa fa-gamepad fa-2x" aria-hidden="true"></i>
        </div>
        <div>
            <h5 class="alert-heading fw-bold m-0">Bem-vindo(a) ao PlayerHUD!</h5>
            <p class="mb-0">
                Este curso utiliza um sistema de gamificação para acompanhar seu progresso,
                recompensar sua participação e tornar sua jornada de aprendizagem mais envolvente.
            </p>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-star fa-3x text-primary mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">XP & Níveis</h5>
                <p class="small text-muted">
                    Ao coletar itens ou completar desafios, você acumula XP (Experiência).
                    Conforme seu XP aumenta, seu nível evolui e sua barra de progresso avança.
                    Dependendo da configuração do professor, o XP pode ou não influenciar sua avaliação.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-cube fa-3x text-success mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Itens & Drops</h5>
                <p class="small text-muted">
                    Durante o curso, você poderá encontrar itens escondidos em atividades,
                    descrições ou desafios específicos. Alguns itens possuem limite de coleta
                    ou tempo de reaparecimento (cooldown).
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-clock fa-3x text-warning mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Tempo & Limites</h5>
                <p class="small text-muted">
                    Se um item exibir um temporizador, significa que ele está temporariamente
                    indisponível. Após o tempo indicado, poderá ser coletado novamente,
                    caso o professor tenha permitido.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-trophy fa-3x text-danger mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Ranking</h5>
                <p class="small text-muted">
                    O ranking mostra sua posição em relação aos colegas.
                    Você pode optar por não aparecer publicamente.
                    Ele funciona como ferramenta de motivação, não como competição obrigatória.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-pause-circle fa-3x text-secondary mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Pausar Gamificação</h5>
                <p class="small text-muted">
                    Você pode desativar temporariamente sua participação na gamificação.
                    Seu progresso ficará pausado e poderá ser reativado posteriormente.
                </p>
            </div>
        </div>
    </div>
</div>
<div class="alert alert-light border shadow-sm mt-4">
    <div class="d-flex align-items-center">
        <div class="me-3">
            <i class="fa fa-lightbulb fa-2x text-warning" aria-hidden="true"></i>
        </div>
        <div>
            <h6 class="fw-bold m-0">Dica Importante</h6>
            <p class="mb-0 small text-muted">
                Explore o curso com atenção, participe das atividades e interaja com os conteúdos.
                O XP é consequência do seu envolvimento — o aprendizado é o verdadeiro objetivo.
            </p>
        </div>
    </div>
</div>';
$string['help_reset_checkbox'] = 'Restaurar conteúdo de ajuda padrão ao salvar';
$string['help_title'] = 'Guia do Jogo';
$string['hidden'] = 'Oculto';
$string['hidden_desc'] = 'Apenas o professor vê você.';
$string['history_desc'] = 'Acompanhe o registro detalhado de suas aventuras e aquisições.';
$string['history_empty'] = 'Nenhum registro encontrado em sua jornada ainda.';
$string['infinite'] = 'Infinito';
$string['infinite_item_title'] = 'Item Infinito';
$string['item'] = 'Item';
$string['item_archived'] = 'Arquivado';
$string['item_desc'] = 'Descrição';
$string['item_details'] = 'Detalhes do Item';
$string['item_granted'] = 'Item concedido manualmente ao aluno!';
$string['item_image'] = 'Ícone / Emoji';
$string['item_n'] = 'Item {$a}';
$string['item_name'] = 'Nome do Item';
$string['item_new'] = 'Novo Item';
$string['item_revoked'] = 'Item removido e XP recalculado com sucesso.';
$string['item_xp'] = 'Valor de XP';
$string['itemimage_emoji'] = 'Emoji ou URL da Imagem';
$string['itemimage_emoji_help'] = 'Use este campo se não quiser enviar um arquivo.<br>Você pode colar um Emoji (ex: 🛡️, 🧪) ou um link direto para uma imagem na web.<br><b>Nota:</b> Se você enviar um arquivo abaixo, este campo será ignorado.';
$string['itemname'] = 'Nome do Item';
$string['itemnotfound'] = 'Item não encontrado ou inativo.';
$string['items'] = 'Itens:';
$string['items_none'] = 'Nenhum item criado';
$string['last_collected'] = 'Última coleta em:';
$string['latest_items'] = 'Últimas coletas';
$string['leaderboard_desc'] = 'Veja quem são os mestres do curso.';
$string['leaderboard_title'] = 'Ranking';
$string['legend'] = 'Legenda:';
$string['legend_finite'] = 'Obtenção de item finito';
$string['legend_infinite'] = 'Obtenção de item infinito';
$string['legend_legacy'] = 'Outros';
$string['legend_origins'] = 'Encontrado no curso';
$string['level'] = 'Nível';
$string['level_settings'] = 'Configurações de Nível';
$string['limitreached'] = '🏆 Parabéns! Você completou esta coleção de itens!';
$string['loading'] = 'Carregando...';
$string['manage_drops_title'] = 'Gerenciar Drops para: {$a}';
$string['master_panel'] = 'Painel do Mestre';
$string['max'] = 'Máximo';
$string['max_levels'] = 'Nível Máximo';
$string['max_levels_help'] = 'O nível máximo que um aluno pode alcançar (Teto). As cores dos níveis serão distribuídas proporcionalmente até este número.';
$string['maxusage'] = 'Limite de Coleta';
$string['maxusage_help'] = 'Defina quantas vezes o aluno pode pegar este item. Se marcar "Ilimitado", o aluno ganha pontos (bônus) toda vez que clica, mas não conta para a meta total do curso.';
$string['members'] = 'membros';
$string['modulename'] = 'PlayerHUD';
$string['modulename_help'] = 'Gamificação e Inventário para seus alunos.';
$string['modulenameplural'] = 'PlayerHUDs';
$string['my_visibility'] = 'Minha Visibilidade:';
$string['new_item_badge'] = 'NOVO!';
$string['next_collection_in'] = 'Próxima coleta em:';
$string['no'] = 'Não';
$string['no_description'] = '- Sem descrição -';
$string['no_groups_data'] = 'Este curso não tem grupos definidos para competição.';
$string['no_ranking_data'] = 'Sem dados de ranking ainda.';
$string['one_time_trade'] = 'Troca única?';
$string['openbackpack'] = 'Abrir Mochila';
$string['optin_hello'] = 'Olá, {$a}!';
$string['optin_message'] = 'Este curso possui um sistema de gamificação com itens, níveis e conquistas. Você gostaria de participar desta jornada?';
$string['optin_no'] = 'Não, obrigado. Voltar ao curso.';
$string['optin_yes'] = 'Sim, eu quero participar!';
$string['playerhud:addinstance'] = 'Adicionar um novo PlayerHUD';
$string['playerhud:manage'] = 'Gerenciar Conteúdo do Jogo';
$string['playerhud:myaddinstance'] = 'Adicionar um novo bloco PlayerHUD ao Painel';
$string['playerhud:view'] = 'Ver PlayerHUD';
$string['pluginadministration'] = 'Administração do PlayerHUD';
$string['pluginname'] = 'PlayerHUD';
$string['privacy:metadata:ai_logs'] = 'Registros de interações com a IA para geração de conteúdo.';
$string['privacy:metadata:ai_logs:action'] = 'O tipo de ação realizada pela IA (ex: criar item).';
$string['privacy:metadata:external:gemini_summary'] = 'O plugin envia textos (prompts) para a API do Google Gemini para gerar itens educativos do jogo.';
$string['privacy:metadata:external:groq_summary'] = 'O plugin envia textos (prompts) para a API Groq para gerar itens educativos do jogo.';
$string['privacy:metadata:external:prompt'] = 'O texto base (prompt) e o tema fornecidos pelo professor para gerar conteúdo.';
$string['privacy:metadata:inventory'] = 'O inventário de itens coletados pelo usuário.';
$string['privacy:metadata:inventory:itemid'] = 'O ID do item que foi coletado.';
$string['privacy:metadata:playerhud_user'] = 'Armazena informações básicas do perfil do jogador e progresso no jogo.';
$string['privacy:metadata:playerhud_user:currentxp'] = 'A quantidade atual de pontos de experiência (XP) do usuário.';
$string['privacy:metadata:playerhud_user:ranking_visibility'] = 'Preferência do usuário sobre aparecer ou não no ranking público.';
$string['privacy:metadata:quest_log'] = 'Registro de missões completadas pelo usuário.';
$string['privacy:metadata:quest_log:questid'] = 'O ID da missão completada.';
$string['privacy:metadata:rpg'] = 'Dados de progresso na história RPG e escolhas do usuário.';
$string['privacy:metadata:rpg:chapters'] = 'Lista de capítulos concluídos.';
$string['privacy:metadata:rpg:classid'] = 'A classe de personagem escolhida.';
$string['privacy:metadata:rpg:karma'] = 'O valor atual de Karma (moralidade) do jogador.';
$string['privacy:metadata:rpg:nodes'] = 'Histórico de cenas visitadas e escolhas feitas.';
$string['privacy:metadata:timecreated'] = 'O momento em que o registro foi criado.';
$string['privacy:metadata:trade_log'] = 'Histórico de trocas e compras realizadas na loja.';
$string['privacy:metadata:trade_log:tradeid'] = 'O ID da transação de troca.';
$string['privacy_export_rpg'] = 'Progresso do RPG';
$string['privacy_updated'] = 'Preferência de privacidade atualizada.';
$string['qty'] = 'Quantidade';
$string['quest_status_completed'] = 'Concluído';
$string['quest_status_pending'] = 'Pendente';
$string['quest_status_removed'] = 'Atividade removida';
$string['rank_groups'] = 'Grupos (Média XP)';
$string['rank_individual'] = 'Individual';
$string['ranking_disable'] = 'Desativar Ranking';
$string['ranking_filter_hide'] = 'Ocultar Inativos (Visão do Aluno)';
$string['ranking_filter_show'] = 'Mostrar Ocultos/Pausados';
$string['ranking_hdr'] = 'Ranking & Competition';
$string['ranking_hidden_help'] = 'Clique em <strong>{$a}</strong> acima para voltar a competir.';
$string['ready'] = 'Pronto!';
$string['report_action'] = 'Ação';
$string['report_ai_subtitle'] = 'Últimas 50 gerações';
$string['report_ai_title'] = 'Auditoria do Oráculo IA';
$string['report_audit'] = 'Auditoria / Histórico';
$string['report_chart_title'] = 'Distribuição de Alunos por Nível';
$string['report_col_ai'] = 'Motor IA';
$string['report_col_date'] = 'Data / Hora';
$string['report_col_desc'] = 'Elemento';
$string['report_col_details'] = 'Detalhes';
$string['report_col_object'] = 'Objeto Gerado';
$string['report_col_type'] = 'Tipo';
$string['report_collected_times'] = 'Coletado {$a} vezes';
$string['report_karma'] = 'Karma';
$string['report_last_action'] = 'Última Ação';
$string['report_leader'] = 'Líder';
$string['report_most_collected'] = 'Item Mais Coletado';
$string['report_no_logs'] = 'Nenhum registro encontrado.';
$string['report_select_user'] = '--- Selecione um estudante ---';
$string['report_show_less'] = 'Mostrar menos';
$string['report_show_more'] = 'Mostrar registros antigos';
$string['report_src_map'] = 'Encontrado no mapa';
$string['report_src_quest'] = 'Recompensa de missão';
$string['report_src_revoked'] = 'Revogado pelo Professor';
$string['report_src_shop'] = 'Comprado na loja';
$string['report_src_teacher'] = 'Concedido pelo professor';
$string['report_status_completed'] = 'Concluído';
$string['report_status_level'] = 'Nível / XP';
$string['report_status_transaction'] = 'Transação Executada';
$string['report_total_xp'] = 'XP Total Gerado';
$string['report_type_item'] = 'Item';
$string['report_type_other'] = 'Outro';
$string['report_type_quest'] = 'Missão';
$string['report_type_revoked'] = 'Revogado';
$string['report_type_trade'] = 'Troca';
$string['respawntime'] = 'Tempo de recarga';
$string['respawntime_help'] = 'Esta configuração define quanto tempo o aluno deve esperar para coletar o item novamente neste local específico.<br><br>Defina como <b>0</b> se quiser que a coleta seja única (o aluno pega uma vez e nunca mais aparece), ou se for um item infinito sem tempo de espera.';
$string['restrict_group'] = 'Restringir a grupo/agrupamento';
$string['revoke_item'] = 'Remover item';
$string['save_keys'] = 'Salvar Minhas Chaves';
$string['save_trade'] = 'Salvar troca';
$string['savechanges'] = 'Salvar alterações';
$string['search_any_term'] = 'Digite qualquer termo para pesquisar...';
$string['secret'] = 'Item Secreto';
$string['secret_desc'] = 'Um item misterioso. Colete-o e descubra!';
$string['secret_help'] = 'Se marcado, este item aparecerá como "???" (Item Misterioso) na lista de itens disponíveis até que o aluno o colete pela primeira vez.';
$string['secret_name'] = '???';
$string['secretdesc'] = 'Esconder da lista até o aluno encontrar.';
$string['select'] = 'Selecionar';
$string['selectall'] = 'Selecionar todos';
$string['shop_empty'] = 'Nenhuma troca disponível no momento.';
$string['shop_pay'] = 'Você paga';
$string['shop_receive'] = 'Você recebe';
$string['shop_xp_warning'] = '<strong>Equilíbrio do jogo:</strong> Itens adquiridos na loja não concedem XP. Eles são usados para criação, missões ou coleções.';
$string['show_in_shop'] = 'Exibir na loja centralizada?';
$string['single_collection'] = 'Coleta única';
$string['sort'] = 'Ordenar coluna';
$string['sort_acquired'] = 'Adquiridos Primeiro';
$string['sort_by'] = 'Ordenar por...';
$string['sort_count_asc'] = 'Menor Quantidade';
$string['sort_count_desc'] = 'Maior Quantidade';
$string['sort_missing'] = 'Faltantes Primeiro';
$string['sort_name_asc'] = 'Nome (A-Z)';
$string['sort_name_desc'] = 'Nome (Z-A)';
$string['sort_recent'] = 'Mais Recentes';
$string['sort_xp_asc'] = 'Menor XP';
$string['sort_xp_desc'] = 'Maior XP';
$string['status_active'] = 'Você está participando da Gamificação.';
$string['status_off'] = 'Desligado';
$string['status_paused'] = 'Gamificação pausada.';
$string['status_paused_title'] = 'Gamificação Desativada';
$string['str_col_date'] = 'Última Pontuação';
$string['student'] = 'Estudante';
$string['success'] = 'Sucesso!';
$string['summary'] = 'Resumo';
$string['summary_stats'] = 'Você possui {$a->items} itens criados e {$a->drops} drops espalhados.';
$string['tab_collection'] = 'Coleção';
$string['tab_config'] = 'Configurações';
$string['tab_history'] = 'Histórico';
$string['tab_items'] = 'Biblioteca de Itens';
$string['tab_maintenance'] = 'A aba "{$a}" está atualmente em manutenção ou construção.';
$string['tab_ranking'] = 'Ranking';
$string['tab_reports'] = 'Relatórios';
$string['tab_rules'] = 'Ajuda & Regras';
$string['tab_shop'] = 'Loja';
$string['tab_trades'] = 'Trocas (NPC)';
$string['take'] = 'Pegar';
$string['time_min'] = 'min';
$string['time_sec'] = 'seg';
$string['total_items_xp'] = 'Total XP em Itens';
$string['tradable'] = 'Trocável?';
$string['tradable_help'] = 'Define se este item pode ser negociado.<br><br><b>Sim:</b> O aluno pode vender este item na loja ou trocá-lo com outros alunos.<br><b>Não:</b> O item é vinculado ao aluno (ideal para itens únicos, chaves de missão ou bens intransferíveis).';
$string['trade_config_hdr'] = 'Configuração da troca';
$string['trade_cost'] = 'Custo:';
$string['trade_default_name'] = 'Nova oferta de troca';
$string['trade_give_desc'] = 'Selecione os itens que o aluno receberá nesta transação.';
$string['trade_give_hdr'] = 'Recompensas (aluno recebe)';
$string['trade_missing_items'] = 'Itens insuficientes';
$string['trade_name'] = 'Nome da troca';
$string['trade_perform'] = 'Realizar troca';
$string['trade_redeemed'] = 'Já resgatado';
$string['trade_req_desc'] = 'Selecione os itens que o aluno deve pagar para concluir esta transação.';
$string['trade_req_hdr'] = 'Requisitos (aluno paga)';
$string['trade_saved'] = 'Troca salva com sucesso.';
$string['trade_success_msg'] = 'Troca realizada com sucesso! Você recebeu: {$a}';
$string['trade_you_have'] = '(Você tem: {$a})';
$string['unlimited'] = 'Ilimitado';
$string['uploadfile'] = 'Upload de Arquivo';
$string['view_ranking'] = 'Ver Ranking';
$string['visible'] = 'Visível';
$string['visible_desc'] = 'Você aparece para seus colegas.';
$string['visual_content'] = 'Conteúdo visual';
$string['visualrules'] = 'Visualização & Regras';
$string['waitmore'] = 'Você já coletou isso! Espere {$a} minutos para o próximo.';
$string['widget_code_desc'] = 'O PlayerHUD funciona nativamente como um Bloco Lateral. Use este código se desejar fixar o painel no meio do conteúdo (ex: Tópico Zero). No App Moodle Mobile, ele gera um botão de atalho rápido para a versão web.';
$string['widget_code_tip'] = '<strong>Dica Pro:</strong> Cole este código em um <strong>Rótulo</strong> no topo do curso para que os usuários do App Mobile tenham um botão de acesso rápido para abrir a Mochila no navegador.';
$string['widget_code_title'] = 'Widget Incorporado & Mobile';
$string['xp'] = 'XP';
$string['xp_help'] = 'Quantos Pontos de Experiência (XP) o aluno ganha ao coletar este item.';
$string['xp_per_level'] = 'XP por Nível';
$string['xp_per_level_help'] = 'Quantos pontos de XP o aluno precisa acumular para ganhar 1 nível. (Padrão: 100)';
$string['xp_required_max'] = 'XP para o Nível Máximo';
$string['xp_warning_msg'] = 'Itens trocáveis não podem conceder XP para evitar fraudes. O valor será definido como 0.';
$string['yes'] = 'Sim';
$string['yours'] = 'Possui: {$a}';
