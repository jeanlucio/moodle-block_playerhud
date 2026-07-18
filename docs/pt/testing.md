# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `ai/generator_test.php` | 2 | `save_item()` (via reflection, sem rede): limita um nome gerado por IA acima do tamanho; converte campos não-string antes de persistir |
| `backup_restore_test.php` | 3 | Definições de backup/restore cobrem todas as tabelas RPG; round-trip completo de curso (incl. uma atividade real) preserva dados de classe/capítulo/história RPG, poderes de item (`action_type`/`action_value`), tiers de emoji da classe, e o requisito de uma quest `TYPE_SPECIFIC_TRADE` remapeado contra a troca restaurada em vez do mapeamento de item; um cmid fixado no `deadline_extension` e o requisito de uma quest `TYPE_ACTIVITY` são ambos remapeados para a atividade do curso restaurado |
| `collection_tab_test.php` | 8 | Aba Coleção: mapeamento de `filter_type` (avatar/prazo/nenhum), `power_hint_avatar` exibido para item não-secreto não possuído e oculto para secreto, flag `is_equipped`; classificação de origem para a fonte de uma linha de inventário (map é reconhecida como própria do PlayerHUD; qualquer coisa fora das 4 fontes conhecidas cai para uma origem genérica "game") |
| `content_crud_test.php` | 13 | CRUD de itens, capítulos e trocas: criação persiste todos os campos, atualização altera campos, exclusão remove registro, listagem escoped por instância |
| `cross_instance_security_test.php` | 12 | Isolamento cross-instance: guardas de item, quest, capítulo e troca aceitam IDs da própria instância e rejeitam IDs alheios sem modificar o registro alvo |
| `drop_guard_test.php` | 7 | Limites de coleta, itens consumidos por troca, aplicação de cooldown |
| `game_test.php` | 36 | `get_game_stats()` totaliza XP/nível mais a inclusão de XP de quests (e exclusão quando a quest está desabilitada), verificado contra o próprio total de `analytics::economy_health()`; anti-farm de coleta e cooldown; `get_avatar_item` (habilitado, desabilitado, instância estrangeira, não encontrado); XP concedido ao coletar drop com uso finito; exclusão de gerentes do ranking; flags de milestone de level-up, vitória no jogo e primeira PlayerCoin na coleta; `xp_to_level`; criação automática de jogador, alternância de gamificação e visibilidade no ranking, inventário (exclui revogados/consumidos), `has_item`; `get_user_rank` ordem por XP, desempate por chegada, exclusão de gerentes e de não matriculados; hidratação de requisitos/recompensas em `get_full_trades`, caso vazio, e bloqueio de disponibilidade quando o item de qualquer um dos lados está desabilitado; heurística de sugestões de troca (avatares com desconto, pulo de avatar já coberto, pré-requisitos) e persistência; `change_xp` emite o evento `xp_changed` ao conceder, ao deduzir (piso em zero) e fica em silêncio num no-op de verdade |
| `gamemaster_test.php` | 6 | Conceder/revogar/excluir item e quest preservando timestamps do ranking; XP mínimo em zero |
| `instance_delete_test.php` | 1 | Excluir uma instância do bloco limpa todas as tabelas próprias do plugin (`instance_cleanup`) |
| `item_delete_cascade_test.php` | 17 | Detecção de trocas órfãs ao excluir item (único req, um de dois, único reward, combinado req+reward); verificações em lote; isolamento cross-instance; exclusão remove o item e cascateia trocas órfãs sem afetar as não-órfãs; excluir um item (único ou em lote) reverte XP só das cópias que realmente ganharam XP, deixando intactas as cópias de drop infinito (XP zero) |
| `karma_test.php` | 11 | Leitura/escrita de karma, deltas positivos/negativos, clamping nos limites ±999, acumulação sucessiva |
| `privacy_provider_test.php` | 10 | LGPD com cobertura completa: descoberta de contexto/usuário (`get_contexts_for_userid`, `get_users_in_context`); `export_user_data` nas seis subárvores (perfil, RPG, inventário, missões, trocas, logs de IA); exclusão por usuário, multiusuário e de contexto inteiro com garantia de isolamento; exportação/exclusão de toda chave de API e preferência de avatar; declaração de metadados; guardas de contexto não-bloco como no-ops |
| `quest_test.php` | 34 | Verificações de conclusão (nível, XP, itens, trocas, conclusão de atividade); reivindicar recompensas; quest desabilitada; idempotência; flags de comemoração de level-up e vitória no jogo ao reivindicar recompensa; `has_claimable_quests` em todos os tipos de requisito incl. conclusão de atividade, com curto-circuito de reivindicadas/não reivindicadas; mapeamento de `build_record_from_suggestion`, transporte de item-ids e piso do override de XP; `get_heuristic_suggestions` milestones de nível/coleção/economia/atividade com pulo de duplicatas; uma atividade com acompanhamento de conclusão oferecida como missão heurística é detectada como cumprida assim que a atividade é realmente concluída |
| `rpg_classes_test.php` | 7 | Atribuição de classe, proteção contra duplicatas, inicialização de karma, limites de tier de retrato |
| `story_manager_test.php` | 15 | Carregamento de cena, persistência de progresso, navegação de escolhas, delta de karma, conclusão de capítulo, casos de erro |
| `suggest_trades_state_test.php` | 4 | Botão Sugerir Trocas: desabilitado sem pré-requisitos, desabilitado só com moeda, desabilitado quando todos os avatares cobertos, habilitado com cobertura parcial |
| `trade_test.php` | 8 | Montagem de trocas, fundos insuficientes, sucesso atômico, limite único, restrição por grupo; uma troca que referencia um item de recompensa desabilitado é rejeitada de imediato mesmo com saldo suficiente |
| `utils_test.php` | 4 | `get_avatar_html`: emoji gera div `ph-avatar-emoji` com span aria-hidden; URL HTTP gera tag img `ph-avatar-img`; imagem nula não lança exceção em `get_avatar_html` nem em `get_items_display_data` |
| **Subtotal** | **198** | |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

Lógica reutilizada por mais de um ponto de entrada (as próprias web services do assistente, a tela manual de "Distribuir Drops", o painel Economy Health), testada diretamente em vez de só indiretamente através de quem quer que a chame.

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `analytics_test.php` | 11 | Economy Health: razão entre XP total ganhável e o teto (vazio/difícil/perfeito/fácil), recompensas de quest e itens infinitos/sem drop no detalhamento, guarda de teto zero; histograma de distribuição de níveis, ordenação do overflow do cap (`N+`), percentual da barra mais alta, guarda de XP-por-nível zero, conjunto de jogadores vazio não produz linhas; o XP atual de `balance_context()` sempre bate com o total do próprio `economy_health()` |
| `audit_log_test.php` | 5 | Consulta compartilhada de log de auditoria (`get_logs()`) usada pela aba Relatórios do professor e pela aba Histórico do estudante: o `xp_gained` de um item reflete o valor `xpawarded` registrado no momento da concessão, não o XP atual do item (e bate quando nunca editado); um item concedido por quest reporta `xp_gained` zero, já que seu próprio XP nunca é pago por esse caminho; uma linha revogada reporta o negativo do valor originalmente registrado, não o XP atual do item; a reivindicação de uma quest reflete o valor registrado, não o `reward_xp` atual da quest |
| `drop_distribution_test.php` | 12 | Descoberta de módulos elegíveis: inclui fóruns, exclui módulos em exclusão e o fórum de avisos do curso (reservado para PlayerCoin/Item Secreto), vazio para curso sem atividades; sugestão por melhor correspondência de nome incl. caso sem correspondência; busca de cmid por shortcode já inserido incl. não encontrado e entrada vazia; divisão de cotas por atividade sempre soma o alvo, limita ao número de atividades, casos de borda |
| `external_items_test.php` | 18 | API de itens entre plugins usada por outros plugins da família Player (ex.: PlayerWords): `belongs_to_instance()` aceita a própria instância de um item (habilitado ou desabilitado) e rejeita instância estrangeira, id inexistente, ou ids zero/negativos sem consultar o banco; `grant()` insere uma linha de inventário por unidade com seu próprio `xpawarded` e credita o XP total uma vez, retém XP quando quem chama sinaliza a origem como sem limite, e é no-op para item de instância estrangeira ou desabilitado; `consume()` marca as linhas mais antigas como consumidas em caso de sucesso, retorna false quando o saldo é insuficiente, e retorna null (não false) para item de instância estrangeira, para que quem chama dispense o custo em vez de bloquear o estudante para sempre; `get_name()`/`get_xp()` resolvem para a própria instância do item e retornam vazio/zero para uma estrangeira; `get_available_quantity()` conta só linhas ativas (não revogadas/não consumidas) e é zero para item de instância estrangeira mesmo que o usuário possua unidades dele |
| `wizard_test.php` | 17 | Manifesto da rodada: status de início/fim; desfazer exclui objetos registrados em todas as tabelas, remove o shortcode registrado, reverte XP e limpa o histórico de jogo, rejeita instância incompatível; listagem de rodadas ativas com contagens e limite; detecção de "já gerado" por mecânica incl. rodadas obsoletas sem conteúdo, itens só no manifesto, itens só logados pela IA e a checagem só-de-config do Ranking; `ensure_config_flag` liga uma flag sem tocar em config irmã e não faz nada quando já está ligada |
| `xp_budget_test.php` | 15 | Contagens de item/missão/capítulo por tamanho de jornada incl. fallback pra curta; `distribute_share` divide a folga igualmente, espalha o resto nos primeiros elementos, limita à folga quando há mais elementos que ela, casos de borda; mapeamento de níveis-máximos sugeridos; rodízio balanceado de missões entre tipos, preservação de ordem dentro de um tipo, todas selecionadas quando o limite as cobre, casos de borda |
| **Subtotal** | **78** | |

### Testes de Web Services (`tests/external/`)

Uma classe de teste por função de web service, validando o contrato da API externa, conformidade de parâmetros e estrutura de retorno (`external_api::clean_returnvalue`), e guardas de capability. As funções de IA são testadas sem rede — sem chave de API configurada, o bloco `try/catch` retorna `success=false`, que é assegurado diretamente.

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `chat_message_test.php` | 2 | Sem chave de API → `moodle_exception`; guarda de capability (`manage`) |
| `collect_item_test.php` | 4 | Item coletado + registro de inventário criado; drop inválido → `success=false`; limite atingido → `success=false`; guarda de capability (`view`) |
| `create_avatar_pack_test.php` | 6 | 17 itens criados; ids e nomes retornados em lockstep; todos com `action_type=avatar`; deduplicação por emoji; segunda chamada cria 0 (idempotência); guarda de capability |
| `create_class_pack_test.php` | 7 | Cria 3 classes; tiers de HP base conforme esperado; pula nome já existente; segunda chamada cria 0 (idempotência); tons diferentes geram nomes diferentes; tom desconhecido cai no fallback fantasia; guarda de capability |
| `create_playercoin_test.php` | 3 | Novo item criado; segunda chamada retorna existente (idempotência); guarda de capability |
| `execute_chat_action_test.php` | 4 | `action_open_tab` retorna URL de redirect (determinístico, sem IA); tipo de ação desconhecido → `success=false`; parâmetros inválidos → `success=false`; guarda de capability |
| `generate_ai_content_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_class_oracle_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_story_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `insert_drop_shortcode_test.php` | 7 | Shortcode inserido no campo de conteúdo do módulo; inserção duplicada rejeitada; drop de outra instância rejeitado; drop renomeado pra atividade em que caiu; `mode=text` com rótulo customizado; modo desconhecido cai pra card; guarda de capability |
| `load_recap_test.php` | 3 | HTML de recap gerado após visita à cena; sem histórico → exceção; guarda de capability (`view`) |
| `load_scene_test.php` | 3 | Nó inicial e escolhas retornados; capítulo inválido → exceção; guarda de capability (`view`) |
| `make_choice_test.php` | 3 | Avança a história até o nó de destino; escolha inválida → exceção; guarda de capability (`view`) |
| `remove_drop_shortcode_test.php` | 5 | Shortcode existente removido; shortcode separado por `<br>` removido; shortcode com atributos `mode=`/`text=` removido; ausência de shortcode é noop sem erro; guarda de capability |
| `setup_playercoin_drop_test.php` | 6 | Sucesso; sem fórum → `success=false`; item de outra instância rejeitado; curso que não é dono da instância rejeitado; shortcode anteposto ao intro existente; guarda de capability |
| `use_item_test.php` | 6 | Guarda de capability (`view`); item não possuído → exceção; poder de prazo: sem atividade, sem regra, cria override e consome item, atualiza override existente |
| `wizard_apply_suggested_levels_test.php` | 3 | Aplica a sugestão quando a config está nos padrões; ainda aplica quando a config já foi customizada; preserva todo outro campo de config intocado |
| `wizard_generate_helpers_test.php` | 10 | `build_step_types()` bate com os módulos selecionados na ordem, pula `auto_distribute` quando o distribuir de Itens está desligado, vazio quando nada selecionado; `compute_shared_xp_shares()` vazio sem Itens/Missões, Pill/Extensão de Prazo usam seus próprios padrões sozinhos, dividem o orçamento com Itens quando combinados; `resolve_or_create_progress_item()` idempotente e cria um item completo quando falta; `resolve_previous_chapter_context()` lê o capítulo mais recente; `distribute_drops()` limita cada atividade à sua cota calculada em vez de deixar só a correspondência de nome empilhar todo drop numa única atividade |
| `wizard_list_runs_test.php` | 4 | Resumo de uma rodada ativa; rodada de RPG resumida; rodadas desfeitas excluídas; guarda de capability |
| `wizard_run_step_test.php` | 56 | Um passo de progresso ao vivo por vez, por mecânica (PlayerCoin, Avatares, Missões, Comércio, Colecionável de Conhecimento, Item Secreto, Ranking, Extensão de Prazo, RPG, Item RPG, auto-distribuir): criação de item/quest/troca com registro no manifesto, retentativas idempotentes, desfazer por mecânica, controle pela flag de distribuir, tom/tamanho de jornada influenciando o conteúdo, e a inserção exclusiva no fórum de avisos pra PlayerCoin e Item Secreto (incl. no-op sem fórum de avisos); tipo de passo desconhecido, guarda de capability, rejeição de `runid` de outra instância, passo com falha não finaliza a rodada, passo final reporta a economia só quando solicitado |
| `wizard_start_test.php` | 8 | Um passo de plano por módulo selecionado; a flag de "passo lento" reflete se Próximo Capítulo foi selecionado; a divisão de cotas de XP bate com os módulos selecionados; o XP bônus da Pill presente quando selecionada sozinha; o módulo de arco da história se expande num outline + um passo por capítulo, a quantidade de passos cresce com o tamanho da jornada, o manifesto mantém o nome lógico do módulo; guarda de capability |
| **Subtotal** | **146** | |

### Testes de Controlador (`tests/controller/`)

Cobrem a lógica de negócio extraída do `manage.php` para os controladores (refatoração MVC), cada um exercitado com entradas explícitas e isolamento de instância.

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|----------------|
| `aikeys_test.php` | 4 | Armazenamento de chaves de IA: chaves aparadas e salvas como preferências do usuário, padrão vazio para campo ausente, chaves legadas removidas do config do bloco, config limpo intocado |
| `chapters_test.php` | 13 | Persistência e ordenação de capítulos: salvar (inserir, atualizar, padrões, isolamento), excluir em cascata cenas/escolhas, mover/reordenar com renumeração da lista completa, no-op na borda |
| `classes_test.php` | 7 | Persistência de classe RPG: inserção (HP base, vínculo de instância, emojis por tier), atualização preserva HP base, trim de emoji, isolamento; exclusão remove registro e retratos por tier, isolamento, irmãos preservados |
| `collect_test.php` | 3 | Transação de coleta de item: drop finito concede XP, drop infinito concede 0 XP (regra de ouro), item de 0 XP armazenado sem alterar XP |
| `drops_test.php` | 11 | Persistência de drop: salvar (inserir + código, ilimitado, atualizar preserva propriedade, isolamento, item estrangeiro); excluir único e no-op estrangeiro; exclusão em massa só dos próprios com contagem, entrada vazia; `get_owned_item` retorna para a instância dona e rejeita instância estrangeira |
| `export_test.php` | 7 | Construtor da exportação de notas: campos da linha e nível derivado, ordenação por XP, teto de nível, exclusão de professores/gerentes, colunas localizadas sem jogadores, exclusão de não matriculados, desempate por última ação |
| `items_test.php` | 15 | Ciclo de vida do item: toggle de ativação e no-op estrangeiro; conceder adiciona inventário + XP, 0 XP, rejeição estrangeira; revogar desconta XP, preserva drop infinito, no-op estrangeiro; revogar desconta o XP realmente registrado no momento da concessão, não o XP atual do item; detecção de trocas sobreviventes (troca aparada, órfã excluída, não relacionada ignorada); `find_xp_impact` soma só as cópias que realmente ganharam XP entre todos os detentores, vazio para item nunca possuído, e no-op para lista de ids vazia |
| `quests_test.php` | 12 | Ciclo de vida da missão: toggle e no-op estrangeiro; excluir reverte XP por conclusão, sem recompensa, no-op estrangeiro; excluir e excluir em lote revertem o XP realmente registrado por conclusão, não a recompensa atual da quest; massa só dos próprios com reversão agregada de XP e contagem, entrada vazia; `find_xp_impact` soma só as conclusões que realmente ganharam XP entre todos os reivindicantes, vazio para quest nunca reivindicada, e no-op para lista de ids vazia |
| `scenes_test.php` | 6 | Persistência de cena/escolha da história: salvar escolhas, atribuição de classe com normalização de ID string/int (regressão `set_class_id`), classe requerida, próximo nó, custo de item, criação de nó de continuação |
| `suggestions_test.php` | 4 | Persistência de sugestões: só as missões marcadas são inseridas (e nenhuma selecionada), só as trocas marcadas são criadas com reqs/recompensas (e nenhuma selecionada) |
| `trades_test.php` | 7 | Persistência de troca: salvar (inserir com reqs + recompensas, atualizar substitui, isolamento, item estrangeiro filtrado); excluir em cascata reqs/recompensas/log, isolamento, irmãos preservados |
| **Subtotal** | **89** | |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|----------------|
| `manage/item_delete_confirm_test.php` | 9 | Contexto da confirmação de exclusão de item: ação única vs massa e payload de IDs, rótulos de confirmação singular/plural/simples, seções só-sobreviventes e órfãs+sobreviventes; aviso de impacto de XP exibido numa exclusão única com link de desabilitar-em-vez-de, nunca exibido numa exclusão em lote mesmo com URL de alternância fornecida, e omitido por completo quando não há impacto de XP |
| `manage/quest_delete_confirm_test.php` | 3 | Contexto de confirmação de exclusão de quest: exclusão única produz a ação `delete_quest_force` com o aviso de impacto de XP e o link de desabilitar-em-vez-de; exclusão em lote produz `bulk_delete_quests_force` com a lista de ids e nunca mostra o link de desabilitar mesmo com uma URL de alternância fornecida; sem impacto de XP omite tanto o aviso quanto o link |
| `manage/tab_chapters_test.php` | 4 | Avisos de visibilidade do card de capítulo: sinalização de cena inicial ausente, texto e limites do aviso de nível acima do máximo |
| **Subtotal** | **16** | |

| **Total geral** | **527** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
| `ai\generator` | 6% |
| `controller\aikeys` | 100% |
| `controller\chapters` | 40% |
| `controller\classes` | 41% |
| `controller\collect` | 13% |
| `controller\drops` | 20% |
| `controller\export` | 90% |
| `controller\items` | 99% |
| `controller\quests` | 76% |
| `controller\scenes` | 13% |
| `controller\suggestions` | 100% |
| `controller\trades` | 39% |
| `drop_guard` | 100% |
| `event\xp_changed` | 43% |
| `external\chat_message` | 67% |
| `external\collect_item` | 100% |
| `external\create_avatar_pack` | 84% |
| `external\create_class_pack` | 79% |
| `external\create_playercoin` | 91% |
| `external\execute_chat_action` | 27% |
| `external\generate_ai_content` | 77% |
| `external\generate_class_oracle` | 67% |
| `external\generate_story` | 75% |
| `external\insert_drop_shortcode` | 87% |
| `external\load_recap` | 100% |
| `external\load_scene` | 79% |
| `external\make_choice` | 79% |
| `external\remove_drop_shortcode` | 84% |
| `external\setup_playercoin_drop` | 90% |
| `external\use_item` | 75% |
| `external\wizard_apply_suggested_levels` | 83% |
| `external\wizard_generate` | 85% |
| `external\wizard_list_runs` | 100% |
| `external\wizard_run_step` | 86% |
| `external\wizard_start` | 99% |
| `game` | 84% |
| `instance_cleanup` | 100% |
| `local\analytics` | 90% |
| `local\audit_log` | 78% |
| `local\drop_distribution` | 97% |
| `local\external_items` | 97% |
| `local\wizard` | 76% |
| `local\xp_budget` | 98% |
| `output\manage\item_delete_confirm` | 100% |
| `output\manage\quest_delete_confirm` | 100% |
| `output\manage\tab_chapters` | 7% |
| `output\view\tab_collection` | 68% |
| `privacy\provider` | 96% |
| `quest` | 90% |
| `story_manager` | 37% |
| `trade_manager` | 90% |
| `utils` | 35% |
| **Total** | **42%** |

49 das 82 classes do plugin aparecem acima — as demais (majoritariamente classes de exceção,
observadores de evento e wrappers finos de output nunca carregados via `require` durante a
execução desta suíte) não têm nenhum dado de cobertura e são omitidas em vez de aparecerem
como um 0% enganoso.

### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|-------------------|--------:|----------------|
| `block_playerhud_access.feature` | 3 | Visibilidade do bloco por perfil (professor adiciona bloco, aluno vê HUD, não matriculado não vê) |
| `block_playerhud_student.feature` | 4 | HUD ativo na primeira visita, desativar/reativar gamificação, dispensar confirmação |
| `block_playerhud_teacher.feature` | 7 | Botão do Painel do Mestre, acesso ao painel de gerenciamento, navegação entre abas, retorno ao curso; abrir o log de auditoria de um aluno em Relatórios não dá erro |
| `block_playerhud_modals.feature` | 5 | Abrir/fechar modal de detalhes do item, proteção contra abertura duplicada, coleta AJAX sem redirecionamento, sem placeholders brutos |
| `block_playerhud_celebrations.feature` | 2 | Introdução do Huddy exibida uma única vez no painel; aviso de primeira quest exibido uma única vez quando há recompensa a reivindicar |
| `block_playerhud_wizard.feature` | 6 | Assistente abre mostrando o formulário de geração; abas laterais de Ajuda e Recomendações externas; gerar PlayerCoin de ponta a ponta mostra o relatório de sucesso; o card do PlayerCoin trava depois de gerado; desfazer uma rodada pelo Histórico destrava de novo |
| **Total** | **27** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```
