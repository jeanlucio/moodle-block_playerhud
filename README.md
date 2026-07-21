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

📚 **[Full documentation](https://jeanlucio.github.io/moodle-block_playerhud/)** — features, group ranking, the Economy Health panel, the PlayerGames ecosystem, the gamification wizard, AI tools, the demo environment, the full 527-case test suite, and security details.

### 🔎 Third-party Service Disclosure

AI features (Content Generator and Game Master Assistant) are **optional** and disabled until a
provider is available. PlayerHUD resolves a tiered chain of personal/site keys before falling
back to Moodle's own `core_ai` subsystem, and never sends student data — only teacher-entered
prompts when a feature is explicitly used.

* **Cost:** None required. Gemini and Groq currently offer free usage tiers (subject to change);
  any cost beyond that is set entirely by the chosen provider, not by PlayerHUD. The `core_ai`
  fallback may be entirely free if the site admin has configured a no-cost institutional provider.
* **API keys:** Not provided by PlayerHUD. Obtain a key directly from the provider (Google
  Gemini, Groq, or your OpenAI-compatible endpoint) and configure it as a personal key in the
  block's own Management Panel, as a site key at **Site administration > Plugins > Blocks >
  PlayerHUD**, or in `local_aihub` if installed — see the full resolution order in the docs.
* **Demo credentials:** Not applicable — no credentials are required to install or use
  PlayerHUD; every AI feature is entirely opt-in.

Full disclosure:
[Security & Compliance](https://jeanlucio.github.io/moodle-block_playerhud/#security).

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

### 🛠️ Installation & Configuration

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `blocks/` directory.
3. Rename the folder to `playerhud` (if necessary).
   Final path:
   `your-moodle/blocks/playerhud/`
4. Install the required **PlayerHUD Filter** plugin.
5. Visit **Site administration > Notifications** to complete installation.
6. Add the block to a course.

Site-level configuration is optional: AI provider keys (Gemini, Groq, or a custom
OpenAI-compatible endpoint) can be set at **Site administration > Plugins > Blocks >
PlayerHUD**, only if you want the AI features. Everything else — items, XP, levels, quests,
ranking — is configured per course through the block's own Management Panel, as covered in the
[Usage](https://jeanlucio.github.io/moodle-block_playerhud/#usage) section of the full
documentation.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-block_playerhud/issues).

### 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Maintainer

Maintained by [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Back to top](#english)

---

## Português

O **Bloco PlayerHUD** é um sistema modular de gamificação para o Moodle que introduz mecânicas estruturadas de progressão baseadas em **XP, Níveis, Inventário e Ranking**.

Ele fornece um **HUD (Head-Up Display)** dinâmico dentro do curso, permitindo que os alunos acompanhem seu progresso em tempo real, enquanto o professor configura as mecânicas de engajamento de acordo com seus objetivos pedagógicos.

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-block_playerhud/pt.html)** — funcionalidades, ranking de grupos, o painel de Saúde da Economia, o ecossistema PlayerGames, o assistente de gamificação, ferramentas de IA, o ambiente de demonstração, a suíte completa de 527 testes, e detalhes de segurança.

### 🔎 Divulgação de Serviço de Terceiros

Os recursos de IA (Gerador de Conteúdo e Assistente Game Master) são **opcionais** e ficam
desativados até que um provedor esteja disponível. O PlayerHUD percorre uma cadeia de níveis de
chaves pessoais/de site antes de recorrer ao subsistema `core_ai` do próprio Moodle, e nunca
envia dados de estudante — apenas os prompts digitados pelo professor quando um recurso é
usado explicitamente.

* **Custo:** Nenhum é exigido. Gemini e Groq atualmente oferecem camadas gratuitas de uso
  (sujeitas a mudança); qualquer custo além disso é definido inteiramente pelo provedor
  escolhido, não pelo PlayerHUD. O fallback `core_ai` pode ser totalmente gratuito se o
  administrador do site tiver configurado um provedor institucional sem custo.
* **Chaves de API:** Não são fornecidas pelo PlayerHUD. Obtenha uma chave diretamente do
  provedor (Google Gemini, Groq, ou seu endpoint compatível com OpenAI) e configure-a como
  chave pessoal no próprio Painel de Gerenciamento do bloco, como chave de site em
  **Administração do site > Plugins > Blocos > PlayerHUD**, ou no `local_aihub` se instalado
  — veja a ordem completa de resolução na documentação.
* **Credenciais de demonstração:** Não aplicável — nenhuma credencial é exigida para instalar ou
  usar o PlayerHUD; todo recurso de IA é totalmente opcional.

Divulgação completa:
[Segurança e Conformidade](https://jeanlucio.github.io/moodle-block_playerhud/pt.html#security).

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

### 🛠️ Instalação e Configuração

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `blocks/` do seu Moodle.
3. Renomeie para `playerhud` (se necessário).
   Caminho final:
   `seu-moodle/blocks/playerhud/`
4. Instale o plugin obrigatório **Filtro PlayerHUD**.
5. Acesse **Administração do site > Notificações** para concluir a instalação.
6. Adicione o bloco ao curso.

A configuração em nível de site é opcional: as chaves de provedor de IA (Gemini, Groq, ou um
endpoint compatível com OpenAI) podem ser definidas em **Administração do site > Plugins >
Blocos > PlayerHUD**, apenas se você quiser os recursos de IA. Todo o resto — itens, XP,
níveis, quests, ranking — é configurado por curso através do próprio Painel de Gerenciamento
do bloco, conforme explicado na seção
[Como Usar](https://jeanlucio.github.io/moodle-block_playerhud/pt.html#usage) da documentação
completa.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-block_playerhud/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
