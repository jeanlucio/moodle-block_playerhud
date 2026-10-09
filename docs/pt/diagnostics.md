# 🩺 Página de Uso e Diagnóstico

Administradores do site têm uma página em **Administração do site > Relatórios > PlayerHUD: uso e diagnóstico** (permissão `moodle/site:config`). Todos os números são contados no próprio site — **nada é enviado para fora dele**.

### Adoção

Quanto o bloco é usado, contado por curso (o bloco permite uma instância por curso):

* Cursos com o bloco, divididos em cursos com itens, com jogadores mas sem itens e com o bloco vazio — os três somam o total.
* Cursos com cada recurso ativado: modo RPG, missões, ranking, itens.
* Blocos que versões anteriores deixaram no Painel de usuários, mostrados só quando existem. Eles não funcionam mais, e cada usuário pode excluir o seu no modo de edição do Painel.

### Engajamento

* Jogadores, jogadores que desativaram a gamificação e o XP acumulado pelos jogadores.
* Nos **últimos 90 dias**: jogadores que ganharam XP, itens recebidos, missões concluídas, trocas realizadas, solicitações à IA (por provedor) e execuções do assistente.

Os números ficam em cache por 10 minutos; o botão **Atualizar** recalcula na hora.

### Integridade dos dados e limpeza

Sites que removeram o bloco antes da v1.7.0 podem ainda guardar os dados desses blocos, e versões anteriores podiam deixar registros para trás ao apagar um item, missão, capítulo, cena, troca ou execução do assistente. Nada disso aparece em nenhuma tela nem entra em nenhuma conta do jogo.

* **Instâncias de blocos removidos:** uma linha por bloco removido, com jogadores, XP, itens, datas de atividade e curso. O curso é recuperado do log quando possível; instâncias cujo **curso ainda existe** começam desmarcadas, porque o bloco pode ter sido removido por engano. Só as marcadas são apagadas. São listadas no máximo 100 por vez — as demais aparecem depois dessa limpeza.
* **Restos de exclusões antigas:** descritos em palavras (por exemplo, "Cenas de história cujo capítulo não existe mais"). São sempre incluídos na limpeza, e apagá-los não muda nada nos cursos em uso.

A exclusão exige confirmação explícita e roda numa única transação. **Antes de apagar, uma cópia de cada registro é salva num arquivo JSON** no diretório de dados do site (`moodledata/block_playerhud/orphan_backups/`). A página lista essas cópias com os botões **Baixar** e **Apagar**. As cópias contêm dados pessoais (identificadores de usuários e XP) dos registros apagados, então apague-as quando não precisar mais delas; elas não são removidas automaticamente.
