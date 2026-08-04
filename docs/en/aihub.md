# 🧩 Optional Integration: AI Hub

PlayerHUD's optional AI features (Content Generator and Game Master Assistant) can draw on **AI Hub** (`local_aihub`, by the same author, part of the shared PlayerGames ecosystem services). When AI Hub is installed, any personal or site key a teacher or admin has already configured there is automatically available to PlayerHUD — no key needs to be re-entered. AI Hub's keys sit between PlayerHUD's own keys and Moodle's `core_ai` fallback in the [AI Provider Chain](security.html#ai-provider-chain). Without AI Hub installed, PlayerHUD simply skips those two tiers and resolves from its own keys or `core_ai`.

👉 <https://github.com/jeanlucio/moodle-local_aihub>
