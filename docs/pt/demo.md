# 🌱 Ambiente de Demonstração (Quick Start)

O plugin inclui dois scripts CLI de seed que criam um curso de demonstração completamente configurado em minutos — útil para desenvolvimento local ou para avaliar o conjunto completo de funcionalidades sem configuração manual.

| Script | Idioma do curso |
|--------|----------------|
| `cli/seed.php` | Inglês |
| `cli/seed_pt_br.php` | Português (Brasil) |

**O que é criado:**

* 1 curso (`playerhud-demo`) com 3 seções e acompanhamento de conclusão
* 1 professor (`seed_teacher`) + 5 alunos (`seed_alice` … `seed_eve`)
* 3 classes RPG com retratos evolutivos de 5 etapas: Guerreiro, Mago, Ladino
* 5 itens com diferentes valores de XP, cooldowns e limites de coleta
* 5 drops inseridos em atividades do curso via shortcodes (modos de exibição: card, imagem e texto)
* 9 quests cobrindo todos os tipos de conclusão (nível, XP total, itens únicos/específicos, trocas)
* 2 capítulos de história com escolhas ramificadas e efeitos de reputação
* 2 ofertas de troca (loja NPC), uma delas já concluída por um aluno
* Um esquadrão de ranking de grupos (3 dos 5 alunos agrupados, 2 deixados sem grupo de propósito)
* Um item "Extensão de Prazo" ligado a uma penalidade real já aplicada pelo [Late Penalty](latepenalty.html) — só é semeado se `local_latepenalty` estiver instalado
* Inventário, log de quests e conclusão de atividades pré-populados — o ranking já está pronto para navegar imediatamente

**Ranking resultante após o seed:**

| Pos. | Usuário | Nome | XP |
|-----:|---------|------|----|
| 1 | `seed_carol` | Carol Staff | 195 |
| 2 | `seed_bob` | Bob Bow | 150 |
| 3 | `seed_alice` | Alice Sword | 65 |
| 4 | `seed_dave` | Dave Shield | 60 |
| 5 | `seed_eve` | Eve Dagger | 10 |

**Uso:**

```bash
# Executar uma vez
php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev

# Apagar e recriar do zero
php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev --reset

# Ignorar o guard de site não-desenvolvimento (domínios customizados)
php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev --force
```

O parâmetro `--password` é **obrigatório** e define a senha de login de todas as contas seed. O script recusa executar em URLs que não sejam de desenvolvimento (`localhost`, `*.local`, `*.test`), a menos que `--force` seja passado.

> Via Docker Compose: `docker compose exec <servico-webserver> php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev`
