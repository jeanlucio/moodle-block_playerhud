# Moodle Block PlayerHUD

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-block_playerhud/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-block_playerhud/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)
[![PlayerHUD Ecosystem](https://img.shields.io/badge/PlayerHUD-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://github.com/jeanlucio/moodle-block_playerhud)
![Core Component](https://img.shields.io/badge/Role-Core_Component-198754?style=flat-square)
![GitHub release](https://img.shields.io/github/v/release/jeanlucio/moodle-block_playerhud?style=flat-square)

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
* 🤖 **AI Item Generator (Optional):** Generates items, stories, and class backstories via external AI providers.
* 📱 **Mobile-Ready:** Compatible with Moodle web services.

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

### 🔗 PlayerHUD Ecosystem

PlayerHUD works together with complementary plugins:

* **PlayerHUD Filter (Required):** Enables item drops via shortcodes inside course content.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **PlayerHUD Availability Condition (Optional):** Allows restricting activities based on PlayerHUD level or collected items.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

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

### 🔐 Security & Compliance

* Capability-based access control
* Server-side validation of recharge time and limits
* `require_sesskey()` protection
* Moodle External API compliant
* Privacy-aware ranking participation

---

### 🔎 Third-party Service Disclosure

PlayerHUD includes an optional AI-powered item generation feature.

### Is the AI feature required?

No. The plugin works fully without any external AI service.
All items can be created manually inside Moodle.
The AI feature is only a productivity tool for automatic item generation.

### Supported Providers

The AI feature supports the following third-party providers:

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

API keys may be configured:

- Globally by the Moodle site administrator, and/or
- Individually by teachers within their courses.

API keys are stored within Moodle configuration settings.

### Data Transmission

When the AI feature is used, user-entered prompts are transmitted to the selected provider for processing.

The plugin:
- Does not store prompts
- Does not store raw AI responses
- Only stores the generated items created inside Moodle

No external communication occurs unless the AI feature is explicitly used.

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
* 🤖 **Gerador de Itens com IA (Opcional):** Gera itens, histórias e backstories de classes via provedores externos de IA.
* 📱 **Compatível com Mobile.**

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

### 🔗 Ecossistema PlayerHUD

O PlayerHUD funciona em conjunto com plugins complementares:

* **Filtro PlayerHUD (Obrigatório):** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **Restrição de Acesso PlayerHUD (Opcional):** Permite liberar atividades com base no nível do aluno ou na posse de itens.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

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

### 🔐 Segurança e Conformidade

- Controle de acesso baseado em capabilities
- Validação no servidor do tempo de recarga e limites
- Proteção com `require_sesskey()`
- Compatível com a API externa do Moodle
- Participação no ranking com controle de privacidade

---

### 🔎 Divulgação de Serviço de Terceiros

O PlayerHUD inclui um recurso opcional de geração automática de itens com IA.

### O recurso de IA é obrigatório?

Não. O plugin funciona de forma completa sem qualquer serviço externo.
Todos os itens podem ser criados manualmente dentro do Moodle.
A IA é apenas um recurso de produtividade para geração automática de itens.

### Provedores suportados

O recurso de IA oferece suporte aos seguintes provedores externos:

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

As chaves de API podem ser configuradas:

- Globalmente pelo administrador do Moodle, e/ou
- Individualmente pelo professor dentro de seus cursos.

As chaves são armazenadas nas configurações do Moodle.

### Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado para processamento.

O plugin:
- Não armazena os prompts
- Não armazena respostas brutas da IA
- Apenas salva os itens gerados dentro do Moodle

Nenhuma comunicação externa ocorre sem ativação explícita do recurso.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
