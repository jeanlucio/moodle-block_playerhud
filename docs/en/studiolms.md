# 🧩 Optional Integration: StudioLMS Course Builder

The **StudioLMS Course Builder** (`local_studiolms`, by the same author, part of the StudioLMS family rather than the PlayerGames one) can set PlayerHUD up for you while it builds a course. Its wizard offers two modes, **Standard** and **Gamified**; the Gamified mode appears only when `block_playerhud` is installed and the teacher may add blocks to the course. In that mode StudioLMS adds a PlayerHUD block to the generated course and configures it by reusing PlayerHUD's own generators: a PlayerCoin dropped in the news forum, an avatar pack, coin-to-avatar trades and quests, plus an AI-written narrative chapter and themed item drops. Three profiles (Conquest, Narrative and Social) tune XP, quests, ranking and drops. Everything created is ordinary PlayerHUD content, editable afterwards in the Game Master panel.

The integration is soft on both sides: StudioLMS checks that PlayerHUD is installed before offering the Gamified mode, and a failure in any single gamification step is recorded as a warning instead of aborting the course generation. Without StudioLMS installed, PlayerHUD is unaffected.

👉 <https://github.com/jeanlucio/moodle-local_studiolms>
