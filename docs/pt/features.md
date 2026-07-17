# ✨ Funcionalidades

* 🎮 **Sistema de XP e Níveis:** Progressão automática baseada no XP acumulado.
* 🏅 **Tiers de Nível:** Sistema visual de progressão com código de cores a cada 5 níveis.
* 🎛 **Progressão Configurável:** O professor define a quantidade de níveis e o XP necessário para cada nível.
* 🎒 **Sistema de Inventário:** Itens colecionáveis com **Tempo de Recarga (intervalo mínimo entre coletas)** e limite configurável.
* 🎯 **Poderes de Item:** Um item pode carregar um efeito especial além do XP — virar o avatar de perfil do aluno, conceder uma extensão de prazo numa atividade escolhida (requer o plugin opcional [Late Penalty](latepenalty.html)), ou funcionar como a PlayerCoin colecionável.
* 📜 **Sistema de Missões:** Missões manuais (nível/XP), de coleção, de conclusão de atividade, de comércio e de capítulo, com uma ferramenta de sugestão heurística.
* 📍 **Sistema de Drops:** Posicione itens nas seções do curso via shortcodes.
* 🎁 **Distribuição Automática de Drops:** Insira em lote os drops pendentes na atividade do curso com melhor correspondência de nome, com um clique — com desfazer por item.
* 🏪 **Loja NPC:** Sistema de trocas configurável — itens por recompensas.
* 🏆 **Ranking:** Classificação com critério de desempate e controle de visibilidade.
* 🔐 **Participação Opcional:** O aluno pode escolher participar ou não da gamificação.
* ⚡ **Atualização em Tempo Real:** Coleta via `core/ajax`.
* 🎉 **Pop-ups Comemorativos com o Mascote:** Pop-ups animados com o mascote Huddy marcam momentos-chave — o Huddy **se apresenta** na primeira visita do aluno ao painel, e depois comemora **subir de nível** (mostrando o nível alcançado), **zerar o jogo** (alcançar 100% da pontuação do curso), **concluir a primeira missão** (um aviso único para ir resgatar a recompensa) e **encontrar a primeira PlayerCoin**. Totalmente acessível (foco preso no teclado, devolução de foco, rótulos para leitor de tela). Os pop-ups de apresentação, primeira missão e primeira PlayerCoin aparecem uma única vez cada. Toda a arte do mascote é distribuída em WebP leve. O professor pode desativar todas as animações do mascote nas configurações do bloco (seção Mascote).
  * *Personalizando a PlayerCoin:* você pode trocar a imagem ou o emoji do item PlayerCoin à vontade — o pop-up não é afetado e sempre mostra o mascote. Já o texto do pop-up é fixo no nome **”PlayerCoin”**; portanto, se renomear o item, mantenha esse nome ou o texto do pop-up deixará de corresponder.
* 🧙 **Personagens RPG:** Defina personagens com retratos, alinhamento de reputação e imagens de evolução por tier.
* 📖 **História e Capítulos:** Sistema narrativo ramificado com nós de escolha e caminhos por personagem.
* ⚖️ **Sistema de Reputação:** Mecânica de alinhamento moral que evolui o retrato do personagem do aluno ao longo do tempo.
* 📊 **Analytics:** Logs de auditoria, rastreamento da economia do jogo, um histograma de distribuição de níveis e um gráfico de conclusão de missões, além de um painel de Saúde da Economia que sinaliza um orçamento de XP desequilibrado.
* 🪄 **Assistente de Gamificação:** Um assistente passo a passo que monta a estrutura gamificada do curso inteiro numa única rodada, com progresso ao vivo, nova tentativa em caso de falha e desfazer com um clique por rodada a partir de uma lista de histórico.
  * **Onze mecânicas em três níveis** — Itens, PlayerCoin, Pacote de Avatares, Comércio, Ranking, Missões, Colecionável de Conhecimento, Item de Extensão de Prazo, Item RPG, RPG (personagens + história completa) e um Item Secreto oculto, agrupados em **Básico / Intermediário / Avançado** pela sofisticação da mecânica, não pelo que ela tecnicamente faz.
  * **Orçamento de XP compartilhado** — mantém toda mecânica gerada dentro do teto de níveis do curso.
  * **Distribuição automática de drops** — insere os itens gerados nas atividades existentes do curso (ou no próprio fórum de avisos, no caso de PlayerCoin/Item Secreto).
  * **Octógono de cobertura Octalysis ao vivo** — fiel às 8 Core Drives originais de Yu-Kai Chou, geometria inclusive, mostra quais motivações a configuração atual realmente cobre.
* 🤖 **Ferramentas de IA (Opcional):** Dois recursos com cadeia de quatro níveis de provedores (veja [Cadeia de Provedores de IA](security.html#cadeia-de-provedores-de-ia) abaixo):
  * **Gerador de Conteúdo** — cria itens, capítulos de história com nós ramificados e backstories de personagens RPG sob demanda.
  * **Assistente Game Master** — aba de chat conversacional para professores. Tire dúvidas sobre design de jogo, receba sugestões e acione ações (criar item, missão, capítulo) com uma etapa de confirmação antes de salvar.
* 📱 **Compatível com Mobile.**
