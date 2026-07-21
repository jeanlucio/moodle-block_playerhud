# 🔐 Security & Compliance

* Capability-based access control
* Server-side validation of recharge time and limits
* `require_sesskey()` protection
* Moodle External API compliant
* Privacy-aware ranking participation

## 🔎 Third-party Service Disclosure

PlayerHUD includes optional AI-powered features: a **Content Generator** (items, chapters, class backstories) and a **Game Master Assistant** (a conversational chat for teachers that can also trigger game actions).

### Is the AI feature required?

No. The plugin works fully without any external AI service.
All content can be created manually inside Moodle.
The AI features are productivity tools — the assistant also accepts confirmation before saving anything.

### AI Provider Chain

PlayerHUD resolves the AI provider **tier by tier**, following the shared PlayerGames
ecosystem ladder. An explicitly configured key always wins over the institutional
default; `core_ai` sits at the bottom.

**Resolution ladder (highest priority first):**

| Tier | Source |
|------|--------|
| 1 | **Own personal key** — teacher’s own key set in PlayerHUD (*Configurações* tab → API keys) |
| 2 | **Hub personal key** — teacher’s own key set in **local_aihub** (if installed) |
| 3 | **Own site key** — admin key set in PlayerHUD settings |
| 4 | **Hub site key** — admin key set in **local_aihub** settings (if installed) |
| 5 | **Moodle `core_ai`** — providers configured in *Site administration → AI → AI providers*. No API key stored in PlayerHUD. |

**Tier-first, not provider-first.** Each tier above is evaluated as a whole: the
first tier that holds *any* key is used exclusively. So a teacher’s own personal key
(tier 1) always wins over a hub key (tier 2) — even a hub key for a higher-priority
provider. For example, a teacher’s own custom-endpoint key is not overridden by a
Gemini key that happens to live in the hub. `core_ai` is consulted only when no tier
holds a key.

**Within the chosen tier**, the direct providers are tried in the order Gemini →
Groq → OpenAI-compatible (first key found is used; if its call fails, the next is tried).

This also means: if a teacher configured their own key in the AI Hub,
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
2. **AI Hub personal key** — set by each teacher in *local_aihub → My AI keys* (if the hub is installed).
3. **PlayerHUD site key** — set by the site admin in *Site administration → Plugins → Blocks → PlayerHUD*.
4. **AI Hub site key** — set by the site admin in *local_aihub* settings (if the hub is installed).
5. **Moodle `core_ai`** — configured by the site admin in *Site administration → AI → AI providers* (no key stored in PlayerHUD; used only when no key above is set).

### Data Transmission

When the AI feature is used, user-entered prompts are transmitted to the selected provider for processing.

The plugin:
- Does not store prompts or conversation history (chat history is session-only, in the browser)
- Does not store raw AI responses
- Only stores the game objects created inside Moodle (items, quests, chapters)

No external communication occurs unless an AI feature is explicitly used.

### Demo Credentials

Not applicable — PlayerHUD requires no credentials to install or use. Every AI feature is
entirely opt-in and the block works fully without any key configured.
