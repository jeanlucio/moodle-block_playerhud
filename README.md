# Moodle Block PlayerHUD

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)
[![PlayerHUD Ecosystem](https://img.shields.io/badge/PlayerHUD-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://github.com/jeanlucio/moodle-block_playerhud)
![Core Component](https://img.shields.io/badge/Role-Core_Component-198754?style=flat-square)

[English](#english) | [Português](#português)

---

## English

The **PlayerHUD Block** is a modular gamification system for Moodle that introduces structured progression mechanics based on **XP, Levels, Inventory, and Ranking**.

It provides a dynamic **HUD (Head-Up Display)** inside courses, allowing students to track their progress in real time while teachers configure engagement mechanics aligned with pedagogical objectives.

---

### ✨ Features

* 🎮 **XP & Level System:** Automatic level progression based on earned XP.
* 🏅 **Level Tiers:** Visual progression system.
* 🎛 **Configurable Progression:** Teachers define the number of levels and XP required for each level.
* 🎒 **Inventory System:** Collectible items with configurable **Cooldown (Recharge Time)** and usage limits.
* 📍 **Drop System:** Place collectible items across course sections.
* 🏆 **Ranking System:** Leaderboard with tie-breaker logic.
* 🔐 **Optional Participation:** Students may choose to opt in or opt out of the gamification system.
* ⚡ **Real-Time Updates:** AJAX-based collection using Moodle’s `core/ajax`.
* 🤖 **AI Item Generator (Optional).**
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

* **Moodle:** 4.5 or higher
* **PHP:** Compatible with your Moodle version

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
* 🏅 **Tiers de Nível:** Sistema visual de progressão.
* 🎛 **Progressão Configurável:** O professor define a quantidade de níveis e o XP necessário para cada nível.
* 🎒 **Sistema de Inventário:** Itens colecionáveis com **Tempo de Recarga (intervalo mínimo entre coletas)** e limite configurável.
* 📍 **Sistema de Drops:** Posicione itens nas seções do curso.
* 🏆 **Ranking:** Classificação com critério de desempate.
* 🔐 **Participação Opcional:** O aluno pode escolher participar ou não da gamificação.
* ⚡ **Atualização em Tempo Real:** Coleta via `core/ajax`.
* 🤖 **Gerador de Itens com IA (Opcional).**
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

* **Moodle:** 4.5 ou superior
* **PHP:** Compatível com a versão do Moodle

### 🔗 Plugins Complementares

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

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
