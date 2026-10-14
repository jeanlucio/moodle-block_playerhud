# ⚖️ Painel de Saúde da Economia

A aba **Configurações** do painel de gerenciamento inclui um widget de **Saúde da Economia** que compara o total de XP que um estudante pode ganhar (todos os itens × seus limites de drop + recompensas de missões) com o teto de XP configurado (XP por nível × número de níveis).

| Cobertura | Status |
|---|---|
| Exatamente 100% | ✅ Verde — "Configuração equilibrada" |
| Abaixo de 100% | ⚠️ Amarelo — os estudantes não conseguem atingir o nível máximo; adicione mais itens ou missões, ou reduza o teto |
| Acima de 100% | 🔴 Vermelho — os estudantes podem ultrapassar o teto; reduza o XP de itens/missões ou aumente o teto |

O widget também exibe uma tabela expansível com o detalhamento de cada item e missão e sua contribuição individual de XP, facilitando a identificação do conteúdo que está contribuindo mais ou menos para a economia.

## Drops que ainda não foram colocados

Os números acima contam todos os drops, mas um drop só paga XP quando o shortcode dele está num lugar que os estudantes enxergam. Quando o painel encontra drops que não estão na descrição de nenhuma atividade, no conteúdo de nenhuma página nem no fórum de avisos, ele acrescenta um aviso abaixo da mensagem de equilíbrio, com quantos drops são e quanto XP eles pagariam, e um link para **Distribuir drops**. Esse XP faz parte do total acima, mas não pode ser ganho enquanto os drops não forem colocados.

A verificação lê os mesmos lugares que a tela Distribuir drops. Um drop colocado onde ela não olha (um capítulo de livro, o resumo de uma seção, uma postagem de fórum) aparece como "não encontrado"; nesse caso, o aviso pode ser ignorado.
