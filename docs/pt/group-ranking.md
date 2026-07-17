# 🏆 Comportamento do Ranking de Grupos

Quando o ranking de grupos está habilitado, a média de XP de cada grupo é calculada **apenas com os membros que estão participando ativamente** — ou seja, membros que tenham simultaneamente:

* **Gamificação ativa** (`enable_gamification = 1`)
* **Ranking visível** (`ranking_visibility = 1`)

Membros que optaram por não participar da gamificação ou que ocultaram seu ranking são completamente excluídos da soma e da contagem do grupo. O denominador usado para calcular a média reflete apenas a quantidade de participantes ativos, não o total de membros do grupo.

**Implicação prática:** um grupo com muitos membros inativos pode apresentar uma média mais alta do que o esperado, pois o cálculo é feito sobre um subconjunto menor. Professores devem ter em mente que a média exibida não representa todos os matriculados no grupo — apenas os que estão participando ativamente do ranking.

### Integração com o PlayerGroup

O ranking de grupos lê diretamente das tabelas nativas de grupos do Moodle (`{groups}` / `{groups_members}`). Funciona com **qualquer** grupo do Moodle — criado manualmente pelo professor ou automaticamente pela atividade **PlayerGroup**.

Quando o **PlayerGroup** (`mod_playergroup`) está instalado junto ao PlayerHUD, uma integração adicional é ativada **no cabeçalho do bloco** (não na aba de ranking): o badge do grupo do estudante, o nome do grupo, a quantidade de membros e a capacidade (ex.: `3/5`) são exibidos no topo do bloco. Essa informação é obtida via API pública do PlayerGroup (`\mod_playergroup\api\group_info`) e está disponível apenas para grupos criados por atividades do PlayerGroup — grupos manuais do Moodle não aparecem ali.

As duas funcionalidades são independentes:

| Cenário | Aba de Ranking de Grupos | Info de grupo no cabeçalho do HUD |
|---|---|---|
| PlayerGroup não instalado | ✅ Funciona com qualquer grupo do Moodle | — Não exibido |
| PlayerGroup instalado, estudante tem grupo do PlayerGroup | ✅ Grupo aparece no ranking | ✅ Badge + nome + vagas exibidos |
| PlayerGroup instalado, estudante está só em grupo manual | ✅ Grupo aparece no ranking | — Não exibido (grupos manuais não estão na API do PlayerGroup) |
