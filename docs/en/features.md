# ✨ Features

* 🎮 **XP & Level System:** Automatic level progression based on earned XP.
* 🏅 **Level Tiers:** Visual color-coded progression (every 5 levels).
* 🎛 **Configurable Progression:** Teachers define the number of levels and XP required for each level.
* 🎒 **Inventory System:** Collectible items with configurable **Cooldown (Recharge Time)** and usage limits.
* 🎯 **Item Powers:** An item can carry a special effect beyond XP — become the student's profile avatar, grant a deadline extension on a chosen activity (requires the optional [Late Penalty](latepenalty.html) plugin), or act as the collectible PlayerCoin.
* 📜 **Quest System:** Manual (level/XP), collection, activity-completion, trade, and chapter quests, with a built-in heuristic suggestion tool.
* 📍 **Drop System:** Place collectible items across course sections via shortcodes.
* 🎁 **Auto Drop Distribution:** Bulk-insert pending drops into the best-matching course activity in one click, with per-item undo.
* 🏪 **NPC Shop:** Item-to-reward exchange with configurable trade rules.
* 🏆 **Ranking System:** Leaderboard with tie-breaker logic and visibility controls.
* 🔐 **Optional Participation:** Students may choose to opt in or opt out of the gamification system.
* ⚡ **Real-Time Updates:** AJAX-based collection using Moodle’s `core/ajax`.
* 🎉 **Mascot Celebration Popups:** Animated popups featuring the Huddy mascot mark key moments — Huddy **introduces himself** on the student's first visit to the dashboard, and then celebrates **leveling up** (showing the level reached), **beating the game** (reaching 100% of the course score), **completing your first quest** (a one-time nudge to go claim its reward), and **finding your first PlayerCoin**. Fully accessible (keyboard focus trap, focus restore, screen-reader labels). The introduction, first-quest and first-PlayerCoin popups are each shown only once. All mascot art ships as lightweight WebP. Teachers can disable all mascot animations via the block's configuration form (Mascot section).
  * *Customizing the PlayerCoin:* you may freely change the PlayerCoin item's image or emoji — the popup is unaffected and always shows the mascot. The popup text, however, is fixed to the name **”PlayerCoin”**, so if you rename the item, keep that name or the popup wording will no longer match.
* 🧙 **RPG Characters:** Define characters with portraits, reputation alignment, and multi-tier evolution images.
* 📖 **Story & Chapters:** Branching narrative system with choice nodes and per-character story paths.
* ⚖️ **Reputation System:** Moral alignment mechanic that evolves the student’s character portrait over time.
* 📊 **Analytics:** Audit logs, game economy tracking, a level-distribution histogram and a quest-completion chart, plus an Economy Health panel that flags an unbalanced XP budget.
* 🪄 **Gamification Wizard:** A step-by-step assistant that builds a course's entire gamified structure in one run, with live progress, retry-on-failure and one-click undo per run from a history list.
  * **Eleven mechanics in three tiers** — Items, PlayerCoin, Avatar Pack, Trade, Ranking, Missions, Knowledge Collectible, Deadline Extension, Item RPG, RPG (characters + full story) and a hidden Secret Item, grouped into **Basic / Intermediate / Advanced** by how sophisticated the mechanic is, not by what it technically does.
  * **Shared XP budget** — keeps every generated mechanic inside the course's level ceiling.
  * **Automatic drop distribution** — inserts generated items across existing course activities (or into the course's own news forum for PlayerCoin/Secret Item).
  * **Live Octalysis coverage octagon** — faithful to Yu-Kai Chou's original 8 Core Drives, geometry included, shows which motivational drives the current setup actually covers.
* 🤖 **AI Tools (Optional):** Two AI-powered features with a tiered provider ladder (see [AI Provider Chain](security.html#ai-provider-chain) below):
  * **Content Generator** — creates items, story chapters with branching nodes, and RPG character backstories on demand.
  * **Game Master Assistant** — a conversational chat tab for teachers. Ask questions about game design, get suggestions, and trigger actions (create item, create quest, generate chapter) with a confirmation step before anything is saved.
* 📱 **Mobile-Ready:** Compatible with Moodle web services.
