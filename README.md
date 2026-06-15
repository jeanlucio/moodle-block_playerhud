# Moodle Block PlayerHUD

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-block_playerhud/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-block_playerhud/actions/workflows/ci.yml)
[![MDL Shield](https://img.shields.io/endpoint?url=https%3A%2F%2Fmdlshield.com%2Fapi%2Fbadge%2Fblock_playerhud)](https://mdlshield.com/plugins/block_playerhud)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Core Component](https://img.shields.io/badge/Role-Core_Component-198754?style=flat-square)

[English](#english) | [Português](#português)

---

## English

The **PlayerHUD Block** is a modular gamification system for Moodle that introduces structured progression mechanics based on **XP, Levels, Inventory, and Ranking**.

It provides a dynamic **HUD (Head-Up Display)** inside courses, allowing students to track their progress in real time while teachers configure engagement mechanics aligned with pedagogical objectives.

---

### ✨ Features

* 🎮 **XP & Level System:** Automatic level progression based on earned XP.
* 🏅 **Level Tiers:** Visual color-coded progression (every 5 levels).
* 🎛 **Configurable Progression:** Teachers define the number of levels and XP required for each level.
* 🎒 **Inventory System:** Collectible items with configurable **Cooldown (Recharge Time)** and usage limits.
* 📍 **Drop System:** Place collectible items across course sections via shortcodes.
* 🏪 **NPC Shop:** Item-to-reward exchange with configurable trade rules.
* 🏆 **Ranking System:** Leaderboard with tie-breaker logic and visibility controls.
* 🔐 **Optional Participation:** Students may choose to opt in or opt out of the gamification system.
* ⚡ **Real-Time Updates:** AJAX-based collection using Moodle’s `core/ajax`.
* 🧙 **RPG Classes:** Define character classes with portraits, karma alignment, and multi-tier evolution images.
* 📖 **Story & Chapters:** Branching narrative system with choice nodes and per-class story paths.
* ⚖️ **Karma System:** Moral alignment mechanic that evolves the student’s class portrait over time.
* 📊 **Analytics:** Audit logs and game economy tracking for teacher oversight.
* 🤖 **AI Tools (Optional):** Two AI-powered features with a level-first provider ladder (see [AI Provider Chain](#-ai-provider-chain) below):
  * **Content Generator** — creates items, story chapters with branching nodes, and RPG class backstories on demand.
  * **Game Master Assistant** — a conversational chat tab for teachers. Ask questions about game design, get suggestions, and trigger actions (create item, create quest, generate chapter) with a confirmation step before anything is saved.
* 📱 **Mobile-Ready:** Compatible with Moodle web services.

---

### 🏆 Group Ranking Behavior

When the group ranking is enabled, each group's average XP is calculated **only from members who are actively participating** — meaning members who have both:

* **Gamification enabled** (`enable_gamification = 1`)
* **Ranking visible** (`ranking_visibility = 1`)

Members who have opted out of gamification or hidden their ranking are completely excluded from the group's sum and count. The denominator used to calculate the average reflects only the number of active participants, not the total group size.

**Practical implication:** a group with many opted-out members may show a higher average than expected, because the average is computed over a smaller subset. Teachers should be aware that a group's displayed average does not represent all enrolled members — only those actively participating in the ranking.

#### Integration with PlayerGroup

The group ranking reads directly from Moodle's native group tables (`{groups}` / `{groups_members}`). It works with **any** Moodle group — whether created manually by a teacher or automatically via the **PlayerGroup** activity module.

When **PlayerGroup** (`mod_playergroup`) is installed alongside PlayerHUD, an additional integration activates **inside the HUD header** (not the ranking tab): the student's group badge, group name, member count, and capacity (e.g. `3/5`) are displayed at the top of the block. This information is fetched via PlayerGroup's public API (`\mod_playergroup\api\group_info`) and is only available for groups created through PlayerGroup activities — manually created Moodle groups are not shown there.

The two features are independent:

| Scenario | Group Ranking tab | HUD header group info |
|---|---|---|
| No PlayerGroup installed | ✅ Works with any Moodle group | — Not shown |
| PlayerGroup installed, student has a PlayerGroup group | ✅ Group appears in ranking | ✅ Badge + name + slots displayed |
| PlayerGroup installed, student is in a manual group only | ✅ Group appears in ranking | — Not shown (manual groups not in PlayerGroup API) |

---

### 🎓 Educational Purpose

PlayerHUD is designed to:

* Encourage active engagement
* Reinforce mastery-based progression
* Provide structured reward systems
* Support competitive and cooperative learning dynamics
* Allow voluntary participation in gamification

Suitable for:

* Gamified academic courses
* Technical and vocational training
* Certification pathways
* Engagement reinforcement strategies

---

### 🕹️ PlayerGames Ecosystem

PlayerHUD is part of the **PlayerGames** gamification ecosystem. Together, these plugins transform Moodle into an immersive experience:

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the student's current level or collected items.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity page — no teacher intervention needed.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `blocks/` directory.
3. Rename the folder to `playerhud` (if necessary).
   Final path:
   `your-moodle/blocks/playerhud/`
4. Install the required **PlayerHUD Filter** plugin.
5. Visit **Site administration > Notifications** to complete installation.
6. Add the block to a course.

---

### 📖 Usage

1. Add the **PlayerHUD Block** to your course.
2. Access the **Management Panel** (Teacher role required).
3. Configure:
   * Items
   * XP values
   * Number of levels
   * XP thresholds
   * Drop placements
   * Recharge time (Cooldown)
   * Collection limits
4. Students collect items directly within course sections.
5. XP, levels, and ranking update automatically.

---

### 🌱 Demo Environment (Quick Start)

The plugin includes two CLI seed scripts that create a fully configured demo course in minutes — useful for local development or for evaluating the full feature set without manual setup.

| Script | Course language |
|--------|----------------|
| `cli/seed.php` | English |
| `cli/seed_pt_br.php` | Brazilian Portuguese |

**What is created:**

* 1 course (`playerhud-demo`) with 3 sections and completion tracking
* 1 teacher (`seed_teacher`) + 5 students (`seed_alice` … `seed_eve`)
* 3 RPG classes: Warrior, Mage, Rogue
* 5 items with different XP values, cooldowns and collection limits
* 5 drops embedded in course activities via shortcodes (card, image and text render modes)
* 7 quests covering all completion types (level, XP, items, trades)
* 2 story chapters with branching choices and karma effects
* 2 trade offers (NPC shop)
* Pre-seeded inventory, quest logs and activity completions — ranking is ready to browse immediately

**Resulting ranking after seed:**

| Rank | Username | Name | XP |
|-----:|----------|------|----|
| 1 | `seed_carol` | Carol Staff | 155 |
| 2 | `seed_bob` | Bob Bow | 150 |
| 3 | `seed_alice` | Alice Sword | 65 |
| 4 | `seed_dave` | Dave Shield | 10 |
| 5 | `seed_eve` | Eve Dagger | 10 |

**Usage:**

```bash
# Run once
php blocks/playerhud/cli/seed.php --password=YourDevPassword

# Wipe and recreate from scratch
php blocks/playerhud/cli/seed.php --password=YourDevPassword --reset

# Bypass the non-development-site guard (custom dev domains)
php blocks/playerhud/cli/seed.php --password=YourDevPassword --force
```

The `--password` flag is **required** and sets the login password for all seed accounts. The script refuses to run on non-development URLs (`localhost`, `*.local`, `*.test`) unless `--force` is passed.

> Via Docker Compose: `docker compose exec <webserver-service> php blocks/playerhud/cli/seed.php --password=YourDevPassword`

---

### 🧪 Automated Tests

PlayerHUD ships with an extensive test suite covering both business logic (PHPUnit) and browser acceptance (Behat). Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_restore_test.php` | 3 | Backup/restore step definitions cover all RPG tables; round-trip preserves data |
| `collection_tab_test.php` | 6 | Collection tab: `filter_type` mapping (avatar/deadline/none), `power_hint_avatar` shown for unowned non-secret item and hidden for secret item, `is_equipped` flag |
| `content_crud_test.php` | 13 | Item, chapter and trade CRUD: create persists all fields, update changes fields, delete removes record, listing scoped to instance |
| `cross_instance_security_test.php` | 12 | Cross-instance isolation: item, quest, chapter and trade guards accept own-instance IDs and reject foreign ones without modifying the target record |
| `drop_guard_test.php` | 7 | Collection limits, trade-consumed items, cooldown enforcement |
| `game_test.php` | 12 | XP and level aggregation, quest XP inclusion/exclusion, collection anti-farm and cooldown; `get_avatar_item` (enabled, disabled, foreign instance, not found); XP award on finite drop; leaderboard manager exclusion |
| `gamemaster_test.php` | 6 | Grant/revoke/delete item and quest while preserving leaderboard timestamps; XP floor at zero |
| `item_delete_cascade_test.php` | 15 | Trade orphan detection when item deleted (sole req, one-of-two, sole reward, combined req+reward); bulk orphan checks; cross-instance isolation; delete removes item record and cascades orphaned trades without touching non-orphaned ones |
| `karma_test.php` | 11 | Karma read/write, positive/negative deltas, clamping at ±999 boundaries, successive accumulation |
| `privacy_provider_test.php` | 4 | GDPR: delete all data for user; delete user preferences; avatar preference included in export; metadata declaration coverage |
| `quest_test.php` | 22 | Completion checks (level, XP, items, trades, activity completion); claim rewards; disabled quest; idempotency |
| `rpg_classes_test.php` | 7 | Class assignment, duplicate guard, karma initialisation, portrait tier boundaries |
| `story_manager_test.php` | 15 | Scene loading, progress persistence, choice navigation, karma delta, chapter completion, error cases |
| `suggest_trades_state_test.php` | 4 | Suggest Trades button: disabled without prereqs, disabled with coin only, disabled when all avatars covered, enabled on partial coverage |
| `trade_test.php` | 7 | Trade assembly, insufficient funds, atomic success, one-time limit, group restriction |
| `utils_test.php` | 2 | `get_avatar_html`: emoji produces `ph-avatar-emoji` div with aria-hidden span; HTTP URL produces `ph-avatar-img` img tag |
| **Subtotal** | **146** | |

#### Web Services Tests (`tests/external/`)

One test class per web service function, each validating the external API contract, parameter/return structure conformance (`external_api::clean_returnvalue`), and capability gates. AI functions are tested without network — with no API key configured, the `try/catch` path returns `success=false`, which is asserted directly.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `chat_message_test.php` | 2 | No API key → `moodle_exception`; capability guard (`manage`) |
| `collect_item_test.php` | 4 | Item collected + inventory record created; invalid drop → `success=false`; limit reached → `success=false`; capability guard (`view`) |
| `create_avatar_pack_test.php` | 5 | 17 items created; all have `action_type=avatar`; emoji deduplication; second call creates 0 (idempotency); capability guard |
| `create_playercoin_test.php` | 3 | New item created; second call returns existing item (idempotency); capability guard |
| `execute_chat_action_test.php` | 4 | `action_open_tab` returns redirect URL (deterministic, no AI); unknown action type → `success=false`; invalid params → `success=false`; capability guard |
| `generate_ai_content_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `generate_class_oracle_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `generate_story_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `insert_drop_shortcode_test.php` | 4 | Shortcode prepended to module content field; duplicate insert rejected; drop from another instance rejected; capability guard |
| `load_recap_test.php` | 3 | Recap HTML returned after scene visit; no history → exception; capability guard (`view`) |
| `load_scene_test.php` | 3 | Start node and choices returned; invalid chapter → exception; capability guard (`view`) |
| `make_choice_test.php` | 3 | Advances story to destination node; invalid choice → exception; capability guard (`view`) |
| `remove_drop_shortcode_test.php` | 3 | Existing shortcode stripped; absent shortcode is a no-op success; capability guard |
| `setup_playercoin_drop_test.php` | 5 | Success path; no forum → `success=false`; item from another instance rejected; shortcode prepended to existing intro; capability guard |
| `use_item_test.php` | 5 | Not-owned item → exception; deadline power: no activity selected, no rule found, creates override and consumes item, updates existing override |
| **Subtotal** | **50** | |

| **Grand Total** | **196** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

#### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `block_playerhud_access.feature` | 3 | Role-based block visibility (teacher adds block, student sees HUD, non-enrolled user cannot) |
| `block_playerhud_student.feature` | 4 | HUD active on first visit, disable/re-enable gamification, dismiss confirmation |
| `block_playerhud_teacher.feature` | 6 | Game Master Panel button, management panel access, tab navigation, return to course |
| `block_playerhud_modals.feature` | 5 | Item detail modal open/close, duplicate-open guard, AJAX collect without redirect, no raw placeholders |
| **Total** | **18** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```

---

### 🔐 Security & Compliance

* Capability-based access control
* Server-side validation of recharge time and limits
* `require_sesskey()` protection
* Moodle External API compliant
* Privacy-aware ranking participation

---

### 🔎 Third-party Service Disclosure

PlayerHUD includes optional AI-powered features: a **Content Generator** (items, chapters, class backstories) and a **Game Master Assistant** (a conversational chat for teachers that can also trigger game actions).

### Is the AI feature required?

No. The plugin works fully without any external AI service.
All content can be created manually inside Moodle.
The AI features are productivity tools — the assistant also accepts confirmation before saving anything.

### 🔗 AI Provider Chain

PlayerHUD resolves the AI provider **level-first**, following the shared PlayerGames
ecosystem ladder. An explicitly configured key always wins over the institutional
default; `core_ai` sits at the bottom.

**Resolution ladder (highest priority first):**

| Level | Source |
|-------|--------|
| 1 | **Personal key** — teacher’s own key set in PlayerHUD (*Configurações* tab → API keys) |
| 2 | **Hub personal key** — teacher’s own key set in **local_playergames** (if installed) |
| 3 | **Site key** — admin key set in PlayerHUD settings |
| 4 | **Hub site key** — admin key set in **local_playergames** settings (if installed) |
| 5 | **Moodle `core_ai`** — providers configured in *Site administration → AI → AI providers*. No API key stored in PlayerHUD. |

**Level-first, not provider-first.** If *any* personal key is set (in PlayerHUD or
in the hub), only personal keys are used; otherwise the site level is used; `core_ai`
is consulted only when no key is configured at any level. This means a teacher’s
personal Groq key is never overridden by an institution Gemini key just because
Gemini has higher provider priority — the level wins first.

**Within a level**, the direct providers are tried in the order Gemini → Groq →
OpenAI-compatible (first key found is used; if its call fails, the next is tried).

This also means: if a teacher configured their own key in the PlayerGames hub,
PlayerHUD uses it automatically — no need to re-enter the key in PlayerHUD.

### Supported Direct Providers

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **OpenAI-compatible APIs** — Any provider that follows the OpenAI API format (e.g. OpenRouter, self-hosted models via LM Studio, Ollama proxy, etc.)

These services operate under their own terms of service and privacy policies.

### How to obtain an API key

API keys must be created directly on the provider’s official website:

- Google Gemini: https://ai.google.dev/
- Groq: https://console.groq.com/
- OpenAI-compatible: refer to your specific provider’s documentation

Both Gemini and Groq currently offer free usage tiers. However, pricing policies may change and paid plans may apply depending on usage limits.

The PlayerHUD plugin does not provide API keys.

### Where API keys are configured

API keys may be configured through any of the following sources (in decreasing priority):

1. **PlayerHUD personal key** — set by each teacher individually in the *Configurações* tab of the management panel.
2. **PlayerGames hub personal key** — set by each teacher in *local_playergames → My AI Keys* (if the hub is installed).
3. **PlayerHUD site key** — set by the site admin in *Site administration → Plugins → Blocks → PlayerHUD*.
4. **PlayerGames hub site key** — set by the site admin in *local_playergames* settings (if the hub is installed).
5. **Moodle `core_ai`** — configured by the site admin in *Site administration → AI → AI providers* (no key stored in PlayerHUD; used only when no key above is set).

### Data Transmission

When the AI feature is used, user-entered prompts are transmitted to the selected provider for processing.

The plugin:
- Does not store prompts or conversation history (chat history is session-only, in the browser)
- Does not store raw AI responses
- Only stores the game objects created inside Moodle (items, quests, chapters)

No external communication occurs unless an AI feature is explicitly used.

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **Bloco PlayerHUD** é um sistema modular de gamificação para Moodle que introduz mecânicas estruturadas de progressão baseadas em **XP, Níveis, Inventário e Ranking**.

Ele fornece um **HUD (Head-Up Display)** dinâmico dentro do curso, permitindo que os alunos acompanhem seu progresso em tempo real, enquanto o professor configura as mecânicas de engajamento de acordo com seus objetivos pedagógicos.

---

### ✨ Funcionalidades

* 🎮 **Sistema de XP e Níveis:** Progressão automática baseada no XP acumulado.
* 🏅 **Tiers de Nível:** Sistema visual de progressão com código de cores a cada 5 níveis.
* 🎛 **Progressão Configurável:** O professor define a quantidade de níveis e o XP necessário para cada nível.
* 🎒 **Sistema de Inventário:** Itens colecionáveis com **Tempo de Recarga (intervalo mínimo entre coletas)** e limite configurável.
* 📍 **Sistema de Drops:** Posicione itens nas seções do curso via shortcodes.
* 🏪 **Loja NPC:** Sistema de trocas configurável — itens por recompensas.
* 🏆 **Ranking:** Classificação com critério de desempate e controle de visibilidade.
* 🔐 **Participação Opcional:** O aluno pode escolher participar ou não da gamificação.
* ⚡ **Atualização em Tempo Real:** Coleta via `core/ajax`.
* 🧙 **Classes RPG:** Defina classes de personagem com retratos, alinhamento de karma e imagens de evolução por tier.
* 📖 **História e Capítulos:** Sistema narrativo ramificado com nós de escolha e caminhos por classe.
* ⚖️ **Sistema de Karma:** Mecânica de alinhamento moral que evolui o retrato da classe do aluno ao longo do tempo.
* 📊 **Analytics:** Logs de auditoria e rastreamento da economia do jogo para controle do professor.
* 🤖 **Ferramentas de IA (Opcional):** Dois recursos com cadeia de quatro níveis de provedores (veja [Cadeia de Provedores de IA](#-cadeia-de-provedores-de-ia) abaixo):
  * **Gerador de Conteúdo** — cria itens, capítulos de história com nós ramificados e backstories de classes RPG sob demanda.
  * **Assistente Game Master** — aba de chat conversacional para professores. Tire dúvidas sobre design de jogo, receba sugestões e acione ações (criar item, missão, capítulo) com uma etapa de confirmação antes de salvar.
* 📱 **Compatível com Mobile.**

---

### 🏆 Comportamento do Ranking de Grupos

Quando o ranking de grupos está habilitado, a média de XP de cada grupo é calculada **apenas com os membros que estão participando ativamente** — ou seja, membros que tenham simultaneamente:

* **Gamificação ativa** (`enable_gamification = 1`)
* **Ranking visível** (`ranking_visibility = 1`)

Membros que optaram por não participar da gamificação ou que ocultaram seu ranking são completamente excluídos da soma e da contagem do grupo. O denominador usado para calcular a média reflete apenas a quantidade de participantes ativos, não o total de membros do grupo.

**Implicação prática:** um grupo com muitos membros inativos pode apresentar uma média mais alta do que o esperado, pois o cálculo é feito sobre um subconjunto menor. Professores devem ter em mente que a média exibida não representa todos os matriculados no grupo — apenas os que estão participando ativamente do ranking.

#### Integração com o PlayerGroup

O ranking de grupos lê diretamente das tabelas nativas de grupos do Moodle (`{groups}` / `{groups_members}`). Funciona com **qualquer** grupo do Moodle — criado manualmente pelo professor ou automaticamente pela atividade **PlayerGroup**.

Quando o **PlayerGroup** (`mod_playergroup`) está instalado junto ao PlayerHUD, uma integração adicional é ativada **no cabeçalho do bloco** (não na aba de ranking): o badge do grupo do estudante, o nome do grupo, a quantidade de membros e a capacidade (ex.: `3/5`) são exibidos no topo do bloco. Essa informação é obtida via API pública do PlayerGroup (`\mod_playergroup\api\group_info`) e está disponível apenas para grupos criados por atividades do PlayerGroup — grupos manuais do Moodle não aparecem ali.

As duas funcionalidades são independentes:

| Cenário | Aba de Ranking de Grupos | Info de grupo no cabeçalho do HUD |
|---|---|---|
| PlayerGroup não instalado | ✅ Funciona com qualquer grupo do Moodle | — Não exibido |
| PlayerGroup instalado, estudante tem grupo do PlayerGroup | ✅ Grupo aparece no ranking | ✅ Badge + nome + vagas exibidos |
| PlayerGroup instalado, estudante está só em grupo manual | ✅ Grupo aparece no ranking | — Não exibido (grupos manuais não estão na API do PlayerGroup) |

---

### 🎓 Finalidade Educacional

O PlayerHUD foi projetado para:

* Estimular engajamento ativo
* Reforçar progressão baseada em domínio
* Criar sistemas estruturados de recompensa
* Permitir dinâmicas competitivas e cooperativas
* Garantir participação voluntária

Indicado para:

* Cursos gamificados
* Formação técnica
* Trilhas de certificação
* Estratégias de reforço de engajamento

---

### 🕹️ Ecossistema PlayerGames

O PlayerHUD faz parte do ecossistema de gamificação **PlayerGames**. Juntos, esses plugins transformam o Moodle em uma experiência imersiva:

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do aluno ou nos itens coletados.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Permite que os alunos formem seus próprios grupos de forma autônoma diretamente na página da atividade — sem necessidade de intervenção do professor.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `blocks/` do seu Moodle.
3. Renomeie para `playerhud` (se necessário).
   Caminho final:
   `seu-moodle/blocks/playerhud/`
4. Instale o plugin obrigatório **Filtro PlayerHUD**.
5. Acesse **Administração do site > Notificações** para concluir a instalação.
6. Adicione o bloco ao curso.

---

### 📖 Como Usar

1. Adicione o **Bloco PlayerHUD** ao seu curso.
2. Acesse o **Painel de Gerenciamento** (necessário perfil de Professor).
3. Configure:
   - Itens
   - Valores de XP
   - Quantidade de níveis
   - Limiares de XP para progressão
   - Posicionamento de drops
   - Tempo de Recarga (intervalo entre coletas)
   - Limites de coleta
4. Os alunos coletam itens diretamente nas seções do curso.
5. O sistema atualiza automaticamente XP, níveis e ranking.

---

### 🌱 Ambiente de Demonstração (Quick Start)

O plugin inclui dois scripts CLI de seed que criam um curso de demonstração completamente configurado em minutos — útil para desenvolvimento local ou para avaliar o conjunto completo de funcionalidades sem configuração manual.

| Script | Idioma do curso |
|--------|----------------|
| `cli/seed.php` | Inglês |
| `cli/seed_pt_br.php` | Português (Brasil) |

**O que é criado:**

* 1 curso (`playerhud-demo`) com 3 seções e acompanhamento de conclusão
* 1 professor (`seed_teacher`) + 5 alunos (`seed_alice` … `seed_eve`)
* 3 classes RPG: Warrior, Mage, Rogue
* 5 itens com diferentes valores de XP, cooldowns e limites de coleta
* 5 drops inseridos em atividades do curso via shortcodes (modos de exibição: card, imagem e texto)
* 7 quests cobrindo todos os tipos de conclusão (nível, XP, itens, trocas)
* 2 capítulos de história com escolhas ramificadas e efeitos de karma
* 2 ofertas de troca (loja NPC)
* Inventário, log de quests e conclusão de atividades pré-populados — o ranking já está pronto para navegar imediatamente

**Ranking resultante após o seed:**

| Pos. | Usuário | Nome | XP |
|-----:|---------|------|----|
| 1 | `seed_carol` | Carol Staff | 155 |
| 2 | `seed_bob` | Bob Bow | 150 |
| 3 | `seed_alice` | Alice Sword | 65 |
| 4 | `seed_dave` | Dave Shield | 10 |
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

---

### 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup_restore_test.php` | 3 | Definições de backup/restore cobrem todas as tabelas RPG; round-trip preserva os dados |
| `collection_tab_test.php` | 6 | Aba Coleção: mapeamento de `filter_type` (avatar/prazo/nenhum), `power_hint_avatar` exibido para item não-secreto não possuído e oculto para secreto, flag `is_equipped` |
| `content_crud_test.php` | 13 | CRUD de itens, capítulos e trocas: criação persiste todos os campos, atualização altera campos, exclusão remove registro, listagem escoped por instância |
| `cross_instance_security_test.php` | 12 | Isolamento cross-instance: guardas de item, quest, capítulo e troca aceitam IDs da própria instância e rejeitam IDs alheios sem modificar o registro alvo |
| `drop_guard_test.php` | 7 | Limites de coleta, itens consumidos por troca, aplicação de cooldown |
| `game_test.php` | 12 | Agregação de XP e nível, XP de quests (inclusão/exclusão), anti-farm de coleta e cooldown; `get_avatar_item` (habilitado, desabilitado, instância estrangeira, não encontrado); XP concedido ao coletar drop com uso finito; exclusão de gerentes do ranking |
| `gamemaster_test.php` | 6 | Conceder/revogar/excluir item e quest preservando timestamps do ranking; XP mínimo em zero |
| `item_delete_cascade_test.php` | 15 | Detecção de trocas órfãs ao excluir item (único req, um de dois, único reward, combinado req+reward); verificações em lote; isolamento cross-instance; exclusão remove o item e cascateia trocas órfãs sem afetar as não-órfãs |
| `karma_test.php` | 11 | Leitura/escrita de karma, deltas positivos/negativos, clamping nos limites ±999, acumulação sucessiva |
| `privacy_provider_test.php` | 4 | LGPD: exclusão de todos os dados do usuário; exclusão de preferências; preferência de avatar incluída na exportação; cobertura da declaração de metadados |
| `quest_test.php` | 22 | Verificações de conclusão (nível, XP, itens, trocas, conclusão de atividade); reivindicar recompensas; quest desabilitada; idempotência |
| `rpg_classes_test.php` | 7 | Atribuição de classe, proteção contra duplicatas, inicialização de karma, limites de tier de retrato |
| `story_manager_test.php` | 15 | Carregamento de cena, persistência de progresso, navegação de escolhas, delta de karma, conclusão de capítulo, casos de erro |
| `suggest_trades_state_test.php` | 4 | Botão Sugerir Trocas: desabilitado sem pré-requisitos, desabilitado só com moeda, desabilitado quando todos os avatares cobertos, habilitado com cobertura parcial |
| `trade_test.php` | 7 | Montagem de trocas, fundos insuficientes, sucesso atômico, limite único, restrição por grupo |
| `utils_test.php` | 2 | `get_avatar_html`: emoji gera div `ph-avatar-emoji` com span aria-hidden; URL HTTP gera tag img `ph-avatar-img` |
| **Subtotal** | **146** | |

#### Testes de Web Services (`tests/external/`)

Uma classe de teste por função de web service, validando o contrato da API externa, conformidade de parâmetros e estrutura de retorno (`external_api::clean_returnvalue`), e guardas de capability. As funções de IA são testadas sem rede — sem chave de API configurada, o bloco `try/catch` retorna `success=false`, que é assegurado diretamente.

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `chat_message_test.php` | 2 | Sem chave de API → `moodle_exception`; guarda de capability (`manage`) |
| `collect_item_test.php` | 4 | Item coletado + registro de inventário criado; drop inválido → `success=false`; limite atingido → `success=false`; guarda de capability (`view`) |
| `create_avatar_pack_test.php` | 5 | 17 itens criados; todos com `action_type=avatar`; deduplicação por emoji; segunda chamada cria 0 (idempotência); guarda de capability |
| `create_playercoin_test.php` | 3 | Novo item criado; segunda chamada retorna existente (idempotência); guarda de capability |
| `execute_chat_action_test.php` | 4 | `action_open_tab` retorna URL de redirect (determinístico, sem IA); tipo de ação desconhecido → `success=false`; parâmetros inválidos → `success=false`; guarda de capability |
| `generate_ai_content_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_class_oracle_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_story_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `insert_drop_shortcode_test.php` | 4 | Shortcode inserido no campo de conteúdo do módulo; inserção duplicada rejeitada; drop de outra instância rejeitado; guarda de capability |
| `load_recap_test.php` | 3 | HTML de recap gerado após visita à cena; sem histórico → exceção; guarda de capability (`view`) |
| `load_scene_test.php` | 3 | Nó inicial e escolhas retornados; capítulo inválido → exceção; guarda de capability (`view`) |
| `make_choice_test.php` | 3 | Avança a história até o nó de destino; escolha inválida → exceção; guarda de capability (`view`) |
| `remove_drop_shortcode_test.php` | 3 | Shortcode existente removido; ausência de shortcode é noop sem erro; guarda de capability |
| `setup_playercoin_drop_test.php` | 5 | Sucesso; sem fórum → `success=false`; item de outra instância rejeitado; shortcode anteposto ao intro existente; guarda de capability |
| `use_item_test.php` | 5 | Item não possuído → exceção; poder de prazo: sem atividade, sem regra, cria override e consome item, atualiza override existente |
| **Subtotal** | **50** | |

| **Total geral** | **196** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

#### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|-------------------|--------:|----------------|
| `block_playerhud_access.feature` | 3 | Visibilidade do bloco por perfil (professor adiciona bloco, aluno vê HUD, não matriculado não vê) |
| `block_playerhud_student.feature` | 4 | HUD ativo na primeira visita, desativar/reativar gamificação, dispensar confirmação |
| `block_playerhud_teacher.feature` | 6 | Botão do Painel do Mestre, acesso ao painel de gerenciamento, navegação entre abas, retorno ao curso |
| `block_playerhud_modals.feature` | 5 | Abrir/fechar modal de detalhes do item, proteção contra abertura duplicada, coleta AJAX sem redirecionamento, sem placeholders brutos |
| **Total** | **18** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```

---

### 🔐 Segurança e Conformidade

- Controle de acesso baseado em capabilities
- Validação no servidor do tempo de recarga e limites
- Proteção com `require_sesskey()`
- Compatível com a API externa do Moodle
- Participação no ranking com controle de privacidade

---

### 🔎 Divulgação de Serviço de Terceiros

O PlayerHUD inclui recursos opcionais de IA: um **Gerador de Conteúdo** (itens, capítulos, backstories de classes) e um **Assistente Game Master** (chat conversacional para professores que também pode acionar ações no jogo).

### O recurso de IA é obrigatório?

Não. O plugin funciona de forma completa sem qualquer serviço externo.
Todo o conteúdo pode ser criado manualmente dentro do Moodle.
Os recursos de IA são ferramentas de produtividade — o assistente exige confirmação antes de salvar qualquer coisa.

### 🔗 Cadeia de Provedores de IA

O PlayerHUD seleciona um provedor de IA seguindo uma ordem de prioridade fixa. O primeiro provedor com configuração disponível é utilizado; se ele falhar, o próximo é tentado automaticamente.

**Ordem de seleção de provedor:**

| Prioridade | Provedor | Observações |
|------------|----------|-------------|
| 1 | **Moodle `core_ai`** | Usa os provedores configurados pelo admin em *Administração do site → IA → Provedores de IA*. No Moodle 5.2+, o `core_ai` já tem fallback interno entre múltiplos provedores configurados. Nenhuma chave precisa ser cadastrada no PlayerHUD. |
| 2 | **Google Gemini** | Chamada direta com modo de saída JSON forçado. |
| 3 | **Groq** | Chamada direta com modo de saída JSON forçado. |
| 4 | **OpenAI-compatível** | Qualquer provedor que siga o formato `/chat/completions` da OpenAI. |

**Resolução de chave por provedor direto (Gemini / Groq / OpenAI):**

Quando o PlayerHUD precisa chamar um provedor diretamente, a chave de API é buscada nesta ordem:

| Nível | Origem |
|-------|--------|
| 1 | Chave pessoal do professor cadastrada no PlayerHUD (aba *Configurações* → Chaves de API) |
| 2 | Chave global cadastrada pelo admin nas configurações do PlayerHUD |
| 3 | Chave pessoal do professor cadastrada no hub **local_playergames** (se instalado) |
| 4 | Chave global cadastrada pelo admin nas configurações do **local_playergames** (se instalado) |

Isso significa: se o professor já tem uma chave configurada no hub PlayerGames, o PlayerHUD a utiliza automaticamente — sem precisar recadastrar.

> **A ordem do provedor prevalece sobre a origem da chave.** Se uma chave Gemini existe apenas no nível 4 (config global do PlayerGames) e uma chave Groq existe no nível 2 (config global do PlayerHUD), o Gemini é utilizado porque é testado primeiro.

### Provedores diretos suportados

- **Google Gemini** — https://ai.google.dev/
- **Groq** — https://console.groq.com/
- **APIs compatíveis com OpenAI** — Qualquer provedor que siga o formato da API OpenAI (ex.: OpenRouter, modelos locais via LM Studio, proxy Ollama, etc.)

Esses serviços seguem seus próprios termos de uso e políticas de privacidade.

### Como obter a chave de API

As chaves de API devem ser criadas diretamente no site oficial do provedor:

- Google Gemini: https://ai.google.dev/
- Groq: https://console.groq.com/
- APIs compatíveis com OpenAI: consulte a documentação do provedor específico

Gemini e Groq atualmente oferecem planos gratuitos, porém as políticas de preços podem variar conforme o volume de uso.

O PlayerHUD não fornece chaves de API.

### Onde a chave é configurada

As chaves de API podem ser configuradas por qualquer uma das seguintes origens (em ordem decrescente de prioridade):

1. **Moodle `core_ai`** — configurado pelo admin em *Administração do site → IA → Provedores de IA* (nenhuma chave armazenada no PlayerHUD).
2. **Chave pessoal no PlayerHUD** — configurada individualmente por cada professor na aba *Configurações* do painel de gerenciamento.
3. **Chave global no PlayerHUD** — configurada pelo admin em *Administração do site → Plugins → Blocos → PlayerHUD*.
4. **Chave pessoal no hub PlayerGames** — configurada pelo professor em *local_playergames → Minhas chaves de IA* (se o hub estiver instalado).
5. **Chave global no hub PlayerGames** — configurada pelo admin nas configurações do *local_playergames* (se o hub estiver instalado).

### Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado para processamento.

O plugin:
- Não armazena prompts nem histórico de conversa (o histórico do chat é apenas da sessão, no navegador)
- Não armazena respostas brutas da IA
- Apenas salva os objetos do jogo criados dentro do Moodle (itens, missões, capítulos)

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
