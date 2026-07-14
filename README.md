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

<a id="toc-en"></a>
**📑 Table of Contents**

- [✨ Features](#-features)
- [🏆 Group Ranking Behavior](#-group-ranking-behavior)
- [⚖️ Economy Health Panel](#-economy-health-panel)
- [🎓 Educational Purpose](#-educational-purpose)
- [🕹️ PlayerGames Ecosystem](#-playergames-ecosystem)
- [🧩 Optional Integration: Late Penalty](#-optional-integration-late-penalty)
- [📦 Requirements](#-requirements)
- [🛠️ Installation](#-installation)
- [📖 Usage](#-usage)
- [🌱 Demo Environment (Quick Start)](#-demo-environment-quick-start)
- [🧪 Automated Tests](#-automated-tests)
- [🔐 Security & Compliance](#-security--compliance)
- [🔎 Third-party Service Disclosure](#-third-party-service-disclosure)
  - [Is the AI feature required?](#is-the-ai-feature-required)
  - [AI Provider Chain](#ai-provider-chain)
  - [Supported Direct Providers](#supported-direct-providers)
  - [How to obtain an API key](#how-to-obtain-an-api-key)
  - [Where API keys are configured](#where-api-keys-are-configured)
  - [Data Transmission](#data-transmission)
- [📄 License / Licença](#-license--licença)

---

### ✨ Features

* 🎮 **XP & Level System:** Automatic level progression based on earned XP.
* 🏅 **Level Tiers:** Visual color-coded progression (every 5 levels).
* 🎛 **Configurable Progression:** Teachers define the number of levels and XP required for each level.
* 🎒 **Inventory System:** Collectible items with configurable **Cooldown (Recharge Time)** and usage limits.
* 🎯 **Item Powers:** An item can carry a special effect beyond XP — become the student's profile avatar, grant a deadline extension on a chosen activity (requires the optional [Late Penalty](#-optional-integration-late-penalty) plugin), or act as the collectible PlayerCoin.
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
* 🤖 **AI Tools (Optional):** Two AI-powered features with a tiered provider ladder (see [AI Provider Chain](#ai-provider-chain) below):
  * **Content Generator** — creates items, story chapters with branching nodes, and RPG character backstories on demand.
  * **Game Master Assistant** — a conversational chat tab for teachers. Ask questions about game design, get suggestions, and trigger actions (create item, create quest, generate chapter) with a confirmation step before anything is saved.
* 📱 **Mobile-Ready:** Compatible with Moodle web services.

[⬆️ Back to index](#toc-en)

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

[⬆️ Back to index](#toc-en)

---

### ⚖️ Economy Health Panel

The **Config** tab in the management panel includes an **Economy Health** widget that compares the total XP a student can earn (all items × their drop limits + quest rewards) against the configured XP cap (XP per level × number of levels).

| Coverage | Status |
|---|---|
| Exactly 100% | ✅ Green — "Balanced configuration" |
| Below 100% | ⚠️ Yellow — students cannot reach the maximum level; add more items or quests, or lower the cap |
| Above 100% | 🔴 Red — students can exceed the cap; reduce item/quest XP or increase the cap |

The widget also shows a collapsible breakdown table listing every item and quest with its individual XP contribution, making it easy to identify which content is over- or under-contributing to the economy.

[⬆️ Back to index](#toc-en)

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

[⬆️ Back to index](#toc-en)

---

### 🕹️ PlayerGames Ecosystem

PlayerHUD is part of the **PlayerGames** gamification ecosystem. Together, these plugins transform Moodle into an immersive experience:

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the student's current level or collected items.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity page — no teacher intervention needed.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

[⬆️ Back to index](#toc-en)

---

### 🧩 Optional Integration: Late Penalty

The **Deadline Extension** item power (see [Features](#-features)) does not work on its own — it requires the separate **Late Penalty** plugin (`local_latepenalty`, by the same author, but **not** part of the PlayerGames family). When installed, redeeming a Deadline Extension item pushes back the activity's effective deadline for that student and triggers Late Penalty's automatic recalculation, waiving or reducing any late-submission grade penalty already applied. Without Late Penalty installed, the item power fails gracefully with a "not installed" message instead of granting the extension.

👉 https://github.com/jeanlucio/moodle-local_latepenalty

[⬆️ Back to index](#toc-en)

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

[⬆️ Back to index](#toc-en)

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

[⬆️ Back to index](#toc-en)

---

### 📖 Usage

1. Add the **PlayerHUD Block** to your course.
2. Access the **Management Panel** (Teacher role required).
3. Choose how to set it up:
   * **Quick start:** run the **Gamification Wizard** to auto-generate items, quests, ranking, and other mechanics in one pass (see [Features](#-features) above).
   * **Manual setup:** configure each mechanic yourself:
     * Items
     * XP values
     * Number of levels
     * XP thresholds
     * Drop placements
     * Recharge time (Cooldown)
     * Collection limits
4. Students collect items directly within course sections.
5. XP, levels, and ranking update automatically.

[⬆️ Back to index](#toc-en)

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
* 3 RPG classes with 5-stage evolving portraits: Warrior, Mage, Rogue
* 5 items with different XP values, cooldowns and collection limits
* 5 drops embedded in course activities via shortcodes (card, image and text render modes)
* 9 quests covering every completion type (level, total XP, unique/specific items, trades)
* 2 story chapters with branching choices and reputation effects
* 2 trade offers (NPC shop), one of them already completed by a student
* A group ranking squad (3 of the 5 students grouped, 2 left ungrouped on purpose)
* A "Deadline Extension" item wired to a real, already-applied [Late Penalty](#-optional-integration-late-penalty) deduction — only seeded if `local_latepenalty` is installed
* Pre-seeded inventory, quest logs and activity completions — ranking is ready to browse immediately

**Resulting ranking after seed:**

| Rank | Username | Name | XP |
|-----:|----------|------|----|
| 1 | `seed_carol` | Carol Staff | 195 |
| 2 | `seed_bob` | Bob Bow | 150 |
| 3 | `seed_alice` | Alice Sword | 65 |
| 4 | `seed_dave` | Dave Shield | 60 |
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

[⬆️ Back to index](#toc-en)

---

### 🧪 Automated Tests

PlayerHUD ships with an extensive test suite covering both business logic (PHPUnit) and browser acceptance (Behat). Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai/generator_test.php` | 2 | `save_item()` (reached via reflection, no network): clamps an overlong AI-provided name; coerces non-string fields before persisting |
| `backup_restore_test.php` | 3 | Backup/restore step definitions cover all RPG tables; full course round-trip (incl. a real activity) preserves RPG class/chapter/story data, item powers (`action_type`/`action_value`), class emoji tiers, and a `TYPE_SPECIFIC_TRADE` quest's requirement remapped against the restored trade rather than the item mapping; a pinned `deadline_extension` cmid and a `TYPE_ACTIVITY` quest's requirement are both remapped to the restored course's own activity |
| `collection_tab_test.php` | 6 | Collection tab: `filter_type` mapping (avatar/deadline/none), `power_hint_avatar` shown for unowned non-secret item and hidden for secret item, `is_equipped` flag |
| `content_crud_test.php` | 13 | Item, chapter and trade CRUD: create persists all fields, update changes fields, delete removes record, listing scoped to instance |
| `cross_instance_security_test.php` | 12 | Cross-instance isolation: item, quest, chapter and trade guards accept own-instance IDs and reject foreign ones without modifying the target record |
| `drop_guard_test.php` | 7 | Collection limits, trade-consumed items, cooldown enforcement |
| `game_test.php` | 32 | XP and level aggregation, quest XP inclusion/exclusion, collection anti-farm and cooldown; `get_avatar_item` (enabled, disabled, foreign instance, not found); XP award on finite drop; leaderboard manager exclusion; level-up, beat-the-game and first-PlayerCoin milestone flags on collection; `xp_to_level`; player auto-creation, gamification and ranking-visibility toggles, inventory (revoked/consumed excluded), `has_item`; `get_user_rank` XP order, tie-break by arrival, manager and enrolment exclusion; `get_full_trades` requirement/reward hydration; trade-suggestion heuristics (discounted avatars, covered-avatar skip, prerequisites) and persistence; `change_xp` emits the `xp_changed` event on award, on deduction (floored at zero) and stays silent on a true no-op |
| `gamemaster_test.php` | 6 | Grant/revoke/delete item and quest while preserving leaderboard timestamps; XP floor at zero |
| `instance_delete_test.php` | 1 | Deleting a block instance cleans every one of this plugin's own tables (`instance_cleanup`) |
| `item_delete_cascade_test.php` | 15 | Trade orphan detection when item deleted (sole req, one-of-two, sole reward, combined req+reward); bulk orphan checks; cross-instance isolation; delete removes item record and cascades orphaned trades without touching non-orphaned ones |
| `karma_test.php` | 11 | Karma read/write, positive/negative deltas, clamping at ±999 boundaries, successive accumulation |
| `privacy_provider_test.php` | 10 | GDPR full coverage: context/user discovery (`get_contexts_for_userid`, `get_users_in_context`); `export_user_data` across all six subtrees (profile, RPG, inventory, quests, trades, AI logs); per-user, multi-user and whole-context deletion with isolation guarantees; export/delete of every API-key and avatar preference; metadata declaration; non-block context guards are no-ops |
| `quest_test.php` | 33 | Completion checks (level, XP, items, trades, activity completion); claim rewards; disabled quest; idempotency; level-up and beat-the-game celebration flags on reward claim; `has_claimable_quests` across every requirement type incl. activity completion, with claimed/unclaimed short-circuit; `build_record_from_suggestion` mapping, item-id carrying and XP override floor; `get_heuristic_suggestions` level/collection/economy/activity milestones with duplicate skipping |
| `rpg_classes_test.php` | 7 | Class assignment, duplicate guard, karma initialisation, portrait tier boundaries |
| `story_manager_test.php` | 15 | Scene loading, progress persistence, choice navigation, karma delta, chapter completion, error cases |
| `suggest_trades_state_test.php` | 4 | Suggest Trades button: disabled without prereqs, disabled with coin only, disabled when all avatars covered, enabled on partial coverage |
| `trade_test.php` | 7 | Trade assembly, insufficient funds, atomic success, one-time limit, group restriction |
| `utils_test.php` | 4 | `get_avatar_html`: emoji produces `ph-avatar-emoji` div with aria-hidden span; HTTP URL produces `ph-avatar-img` img tag; a null image does not throw for `get_avatar_html` nor `get_items_display_data` |
| **Subtotal** | **188** | |

#### Local Business-Logic Tests (`tests/local/`)

Shared logic reused by more than one entry point (the wizard's own web services, the manual "Distribute Drops" screen, the Economy Health panel), tested directly rather than only indirectly through whichever controller happens to call it.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `analytics_test.php` | 9 | Economy Health: total earnable XP vs ceiling ratio (empty/hard/perfect/easy), quest rewards and infinite/dropless items in the breakdown, zero-ceiling guard; level-distribution histogram bucketing, cap overflow (`N+`) ordering, percent of tallest bar, zero-XP-per-level guard |
| `drop_distribution_test.php` | 12 | Eligible-modules discovery: includes forums, excludes modules pending deletion and the course's own news forum (reserved for PlayerCoin/Secret Item), empty for an activity-less course; best-name-match suggestion incl. no-match case; inserted-shortcode cmid lookup incl. not-found and empty-input cases; activity-quota splitting always sums to target, caps at activity count, edge cases |
| `wizard_test.php` | 17 | Run manifest: start/finish status; rollback deletes recorded objects across tables, strips the recorded shortcode, reverts XP and clears play history, rejects a mismatched instance; active-runs listing with counts and a limit; per-module "already generated" detection incl. stale runs without content, manifest-only items, AI-logged-only items and Ranking's config-only check; `ensure_config_flag` turns a flag on without touching sibling config and is a no-op when already on |
| `xp_budget_test.php` | 15 | Item/mission/chapter counts per journey size incl. fallback to short; `distribute_share` divides a gap evenly, spreads the remainder on the first elements, caps at the gap when elements outnumber it, edge cases; suggested max-levels mapping; balanced-mission round-robin across types, order preservation within a type, all-selected when the limit covers them, edge cases |
| **Subtotal** | **53** | |

#### Web Services Tests (`tests/external/`)

One test class per web service function, each validating the external API contract, parameter/return structure conformance (`external_api::clean_returnvalue`), and capability gates. AI functions are tested without network — with no API key configured, the `try/catch` path returns `success=false`, which is asserted directly.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `chat_message_test.php` | 2 | No API key → `moodle_exception`; capability guard (`manage`) |
| `collect_item_test.php` | 4 | Item collected + inventory record created; invalid drop → `success=false`; limit reached → `success=false`; capability guard (`view`) |
| `create_avatar_pack_test.php` | 6 | 17 items created; ids and names returned in lockstep; all have `action_type=avatar`; emoji deduplication; second call creates 0 (idempotency); capability guard |
| `create_class_pack_test.php` | 7 | Creates 3 classes; base-HP tiers match expectations; skips an already-existing name; second call creates 0 (idempotency); different tones produce different names; unknown tone falls back to fantasy; capability guard |
| `create_playercoin_test.php` | 3 | New item created; second call returns existing item (idempotency); capability guard |
| `execute_chat_action_test.php` | 4 | `action_open_tab` returns redirect URL (deterministic, no AI); unknown action type → `success=false`; invalid params → `success=false`; capability guard |
| `generate_ai_content_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `generate_class_oracle_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `generate_story_test.php` | 2 | No API key → `success=false`; capability guard (`manage`) |
| `insert_drop_shortcode_test.php` | 7 | Shortcode prepended to module content field; duplicate insert rejected; drop from another instance rejected; drop renamed to the activity it lands in; `mode=text` with a custom label; unknown mode falls back to card; capability guard |
| `load_recap_test.php` | 3 | Recap HTML returned after scene visit; no history → exception; capability guard (`view`) |
| `load_scene_test.php` | 3 | Start node and choices returned; invalid chapter → exception; capability guard (`view`) |
| `make_choice_test.php` | 3 | Advances story to destination node; invalid choice → exception; capability guard (`view`) |
| `remove_drop_shortcode_test.php` | 5 | Existing shortcode stripped; `<br>`-separated shortcode stripped; shortcode carrying `mode=`/`text=` attributes stripped; absent shortcode is a no-op success; capability guard |
| `setup_playercoin_drop_test.php` | 6 | Success path; no forum → `success=false`; item from another instance rejected; course not owning the instance rejected; shortcode prepended to existing intro; capability guard |
| `use_item_test.php` | 6 | Capability guard (`view`); not-owned item → exception; deadline power: no activity selected, no rule found, creates override and consumes item, updates existing override |
| `wizard_apply_suggested_levels_test.php` | 3 | Applies the suggestion when config is at defaults; still applies when config was already customised; preserves every other config field untouched |
| `wizard_generate_helpers_test.php` | 9 | `build_step_types()` matches selected modules in order, skips `auto_distribute` when Items' own distribute flag is off, empty when nothing selected; `compute_shared_xp_shares()` empty without Items/Missions, Pill/Latepenalty use their own defaults alone, share the budget with Items when combined; `resolve_or_create_progress_item()` idempotent and creates a complete item when missing; `resolve_previous_chapter_context()` reads the latest chapter |
| `wizard_list_runs_test.php` | 4 | Summary for an active run; RPG run summarised; rolled-back runs excluded; capability guard |
| `wizard_run_step_test.php` | 56 | One live-progress step at a time, per mechanic (PlayerCoin, Avatars, Missions, Trade, Knowledge Pill, Secret Item, Ranking, Deadline Extension, RPG, Item RPG, auto-distribute): item/quest/trade creation with manifest recording, idempotent retries, rollback per mechanic, distribute-flag gating, tone/journey-size flavouring, and the news-forum-only placement for PlayerCoin and Secret Item (incl. no-op without a news forum); unknown step type, capability guard, cross-instance `runid` rejection, failed step does not finish the run, final step reports the economy only when requested |
| `wizard_start_test.php` | 8 | One plan step per selected module; the "slow step" flag reflects whether Next Chapter was selected; XP shares split matches selected modules; Pill's bonus XP present when selected alone; the story-arc module expands into an outline + one step per chapter, step count grows with journey size, manifest keeps the logical module name; capability guard |
| **Subtotal** | **145** | |

#### Controller Tests (`tests/controller/`)

These cover the business logic extracted from `manage.php` into the controllers (MVC refactor), each exercised with explicit inputs and instance isolation.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `aikeys_test.php` | 4 | AI key storage: keys trimmed and saved as user preferences, empty default for a missing field, legacy keys stripped from block config, clean config left untouched |
| `chapters_test.php` | 13 | Chapter persistence and ordering: save (insert, update, defaults, isolation), delete cascading scenes/choices, reorder/move with full-list renumbering, edge no-op |
| `classes_test.php` | 7 | RPG class persistence: insert (base HP, instance binding, emoji tiers), update preserves base HP, emoji trimming, isolation; delete removes record and tier portraits, isolation, siblings kept |
| `collect_test.php` | 3 | Item collection transaction: finite drop awards XP, infinite drop awards 0 XP (golden rule), zero-XP item stored without XP change |
| `drops_test.php` | 11 | Drop persistence: save (insert + code, unlimited, update preserves ownership, isolation, foreign item); delete single and foreign no-op; bulk deletes only owned with count, empty input; `get_owned_item` returns for the owning instance and rejects a foreign one |
| `export_test.php` | 7 | Grade export builder: row fields and derived level, XP ordering, level cap, teacher/manager exclusion, localized columns with no players, unenrolled exclusion, XP tie-break by last action |
| `items_test.php` | 11 | Item lifecycle: enable toggle and foreign no-op; grant adds inventory + XP, zero-XP, foreign rejection; revoke deducts XP, infinite-drop preservation, foreign no-op; surviving-trade detection (trimmed trade, orphaned excluded, unrelated ignored) |
| `quests_test.php` | 7 | Quest lifecycle: toggle and foreign no-op; delete reverts XP per completion, zero-reward, foreign no-op; bulk deletes only owned with aggregated XP revert and count, empty input |
| `scenes_test.php` | 6 | Story scene/choice persistence: save choices, class assignment with string/int ID normalisation (`set_class_id` regression), required class, next node, item cost, follow-up node creation |
| `suggestions_test.php` | 4 | Suggestion persistence: only ticked quest suggestions inserted (and none selected), only ticked trade suggestions created with reqs/rewards (and none selected) |
| `trades_test.php` | 7 | Trade persistence: save (insert with reqs + rewards, update replaces, isolation, foreign item filtered); delete cascading reqs/rewards/log, isolation, siblings kept |
| **Subtotal** | **80** | |

#### Output / Renderer Tests (`tests/output/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `manage/item_delete_confirm_test.php` | 6 | Item-deletion confirmation context: single vs bulk action and id payload, singular/plural/simple confirm labels, surviving-only and orphaned+surviving sections |
| `manage/tab_chapters_test.php` | 4 | Chapter-card visibility warnings: missing start-scene flag, required-level-above-maximum warning text and bounds |
| **Subtotal** | **10** | |

| **Grand Total** | **476** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `ai\generator` | 6% |
| `controller\aikeys` | 100% |
| `controller\chapters` | 40% |
| `controller\classes` | 41% |
| `controller\collect` | 13% |
| `controller\drops` | 18% |
| `controller\export` | 90% |
| `controller\items` | 79% |
| `controller\quests` | 71% |
| `controller\scenes` | 15% |
| `controller\suggestions` | 100% |
| `controller\trades` | 39% |
| `drop_guard` | 100% |
| `event\xp_changed` | 43% |
| `external\chat_message` | 67% |
| `external\collect_item` | 100% |
| `external\create_avatar_pack` | 84% |
| `external\create_class_pack` | 79% |
| `external\create_playercoin` | 91% |
| `external\execute_chat_action` | 27% |
| `external\generate_ai_content` | 76% |
| `external\generate_class_oracle` | 67% |
| `external\generate_story` | 75% |
| `external\insert_drop_shortcode` | 87% |
| `external\load_recap` | 100% |
| `external\load_scene` | 79% |
| `external\make_choice` | 79% |
| `external\remove_drop_shortcode` | 84% |
| `external\setup_playercoin_drop` | 90% |
| `external\use_item` | 75% |
| `external\wizard_apply_suggested_levels` | 83% |
| `external\wizard_generate` | 18%¹ |
| `external\wizard_list_runs` | 100% |
| `external\wizard_run_step` | 86% |
| `external\wizard_start` | 99% |
| `game` | 84% |
| `instance_cleanup` | 100% |
| `local\analytics` | 81% |
| `local\drop_distribution` | 97% |
| `local\wizard` | 76% |
| `local\xp_budget` | 98% |
| `output\manage\item_delete_confirm` | 100% |
| `output\manage\tab_chapters` | 7% |
| `output\view\tab_collection` | 68% |
| `privacy\provider` | 96% |
| `quest` | 90% |
| `story_manager` | 37% |
| `trade_manager` | 90% |
| `utils` | 35% |
| **Overall** | **37%** |

¹ Undercounted by the coverage tool: `wizard_generate`'s `generate_*()` methods are only ever called statically from `wizard_run_step`, which the line-coverage instrumentation attributes to the *caller's* line, not the callee's — `wizard_run_step_test.php`'s 56 cases exercise every one of them directly.

#### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `block_playerhud_access.feature` | 3 | Role-based block visibility (teacher adds block, student sees HUD, non-enrolled user cannot) |
| `block_playerhud_student.feature` | 4 | HUD active on first visit, disable/re-enable gamification, dismiss confirmation |
| `block_playerhud_teacher.feature` | 7 | Game Master Panel button, management panel access, tab navigation, return to course; opening a student's audit log in Reports does not error |
| `block_playerhud_modals.feature` | 5 | Item detail modal open/close, duplicate-open guard, AJAX collect without redirect, no raw placeholders |
| `block_playerhud_celebrations.feature` | 2 | Huddy introduction shown once on the dashboard; first-quest nudge shown once when a reward is claimable |
| `block_playerhud_wizard.feature` | 6 | Wizard opens showing the generation form; Help and External recommendations side views; generating PlayerCoin end-to-end shows the success report; the PlayerCoin card locks after being generated; undoing a run from the History view unlocks it again |
| **Total** | **27** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```

[⬆️ Back to index](#toc-en)

---

### 🔐 Security & Compliance

* Capability-based access control
* Server-side validation of recharge time and limits
* `require_sesskey()` protection
* Moodle External API compliant
* Privacy-aware ranking participation

[⬆️ Back to index](#toc-en)

---

### 🔎 Third-party Service Disclosure

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

[⬆️ Back to index](#toc-en)

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Back to index](#toc-en)

---

## Português

O **Bloco PlayerHUD** é um sistema modular de gamificação para Moodle que introduz mecânicas estruturadas de progressão baseadas em **XP, Níveis, Inventário e Ranking**.

Ele fornece um **HUD (Head-Up Display)** dinâmico dentro do curso, permitindo que os alunos acompanhem seu progresso em tempo real, enquanto o professor configura as mecânicas de engajamento de acordo com seus objetivos pedagógicos.

<a id="toc-pt"></a>
**📑 Índice**

- [✨ Funcionalidades](#-funcionalidades)
- [🏆 Comportamento do Ranking de Grupos](#-comportamento-do-ranking-de-grupos)
- [⚖️ Painel de Saúde da Economia](#-painel-de-saúde-da-economia)
- [🎓 Finalidade Educacional](#-finalidade-educacional)
- [🕹️ Ecossistema PlayerGames](#-ecossistema-playergames)
- [🧩 Integração Opcional: Late Penalty](#-integração-opcional-late-penalty)
- [📦 Requisitos](#-requisitos)
- [🛠️ Instalação](#-instalação)
- [📖 Como Usar](#-como-usar)
- [🌱 Ambiente de Demonstração (Quick Start)](#-ambiente-de-demonstração-quick-start)
- [🧪 Testes Automatizados](#-testes-automatizados)
- [🔐 Segurança e Conformidade](#-segurança-e-conformidade)
- [🔎 Divulgação de Serviço de Terceiros](#-divulgação-de-serviço-de-terceiros)
  - [O recurso de IA é obrigatório?](#o-recurso-de-ia-é-obrigatório)
  - [Cadeia de Provedores de IA](#cadeia-de-provedores-de-ia)
  - [Provedores diretos suportados](#provedores-diretos-suportados)
  - [Como obter a chave de API](#como-obter-a-chave-de-api)
  - [Onde a chave é configurada](#onde-a-chave-é-configurada)
  - [Transmissão de dados](#transmissão-de-dados)
- [📄 Licença](#-licença)

---

### ✨ Funcionalidades

* 🎮 **Sistema de XP e Níveis:** Progressão automática baseada no XP acumulado.
* 🏅 **Tiers de Nível:** Sistema visual de progressão com código de cores a cada 5 níveis.
* 🎛 **Progressão Configurável:** O professor define a quantidade de níveis e o XP necessário para cada nível.
* 🎒 **Sistema de Inventário:** Itens colecionáveis com **Tempo de Recarga (intervalo mínimo entre coletas)** e limite configurável.
* 🎯 **Poderes de Item:** Um item pode carregar um efeito especial além do XP — virar o avatar de perfil do aluno, conceder uma extensão de prazo numa atividade escolhida (requer o plugin opcional [Late Penalty](#-integração-opcional-late-penalty)), ou funcionar como a PlayerCoin colecionável.
* 📜 **Sistema de Missões:** Missões manuais (nível/XP), de coleção, de conclusão de atividade, de comércio e de capítulo, com uma ferramenta de sugestão heurística.
* 📍 **Sistema de Drops:** Posicione itens nas seções do curso via shortcodes.
* 🎁 **Distribuição Automática de Drops:** Insira em lote os drops pendentes na atividade do curso com melhor correspondência de nome, com um clique — com desfazer por item.
* 🏪 **Loja NPC:** Sistema de trocas configurável — itens por recompensas.
* 🏆 **Ranking:** Classificação com critério de desempate e controle de visibilidade.
* 🔐 **Participação Opcional:** O aluno pode escolher participar ou não da gamificação.
* ⚡ **Atualização em Tempo Real:** Coleta via `core/ajax`.
* 🎉 **Pop-ups Comemorativos com o Mascote:** Pop-ups animados com o mascote Huddy marcam momentos-chave — o Huddy **se apresenta** na primeira visita do aluno ao painel, e depois comemora **subir de nível** (mostrando o nível alcançado), **zerar o jogo** (alcançar 100% da pontuação do curso), **concluir a primeira missão** (um aviso único para ir resgatar a recompensa) e **encontrar a primeira PlayerCoin**. Totalmente acessível (foco preso no teclado, devolução de foco, rótulos para leitor de tela). Os pop-ups de apresentação, primeira missão e primeira PlayerCoin aparecem uma única vez cada. Toda a arte do mascote é distribuída em WebP leve. O professor pode desativar todas as animações do mascote nas configurações do bloco (seção Mascote).
  * *Personalizando a PlayerCoin:* você pode trocar a imagem ou o emoji do item PlayerCoin à vontade — o pop-up não é afetado e sempre mostra o mascote. Já o texto do pop-up é fixo no nome **”PlayerCoin”**; portanto, se renomear o item, mantenha esse nome ou o texto do pop-up deixará de corresponder.
* 🧙 **Personagens RPG:** Defina personagens com retratos, alinhamento de reputação e imagens de evolução por tier.
* 📖 **História e Capítulos:** Sistema narrativo ramificado com nós de escolha e caminhos por personagem.
* ⚖️ **Sistema de Reputação:** Mecânica de alinhamento moral que evolui o retrato do personagem do aluno ao longo do tempo.
* 📊 **Analytics:** Logs de auditoria, rastreamento da economia do jogo, um histograma de distribuição de níveis e um gráfico de conclusão de missões, além de um painel de Saúde da Economia que sinaliza um orçamento de XP desequilibrado.
* 🪄 **Assistente de Gamificação:** Um assistente passo a passo que monta a estrutura gamificada do curso inteiro numa única rodada, com progresso ao vivo, nova tentativa em caso de falha e desfazer com um clique por rodada a partir de uma lista de histórico.
  * **Onze mecânicas em três níveis** — Itens, PlayerCoin, Pacote de Avatares, Comércio, Ranking, Missões, Colecionável de Conhecimento, Item de Extensão de Prazo, Item RPG, RPG (personagens + história completa) e um Item Secreto oculto, agrupados em **Básico / Intermediário / Avançado** pela sofisticação da mecânica, não pelo que ela tecnicamente faz.
  * **Orçamento de XP compartilhado** — mantém toda mecânica gerada dentro do teto de níveis do curso.
  * **Distribuição automática de drops** — insere os itens gerados nas atividades existentes do curso (ou no próprio fórum de avisos, no caso de PlayerCoin/Item Secreto).
  * **Octógono de cobertura Octalysis ao vivo** — fiel às 8 Core Drives originais de Yu-Kai Chou, geometria inclusive, mostra quais motivações a configuração atual realmente cobre.
* 🤖 **Ferramentas de IA (Opcional):** Dois recursos com cadeia de quatro níveis de provedores (veja [Cadeia de Provedores de IA](#cadeia-de-provedores-de-ia) abaixo):
  * **Gerador de Conteúdo** — cria itens, capítulos de história com nós ramificados e backstories de personagens RPG sob demanda.
  * **Assistente Game Master** — aba de chat conversacional para professores. Tire dúvidas sobre design de jogo, receba sugestões e acione ações (criar item, missão, capítulo) com uma etapa de confirmação antes de salvar.
* 📱 **Compatível com Mobile.**

[⬆️ Voltar ao índice](#toc-pt)

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

[⬆️ Voltar ao índice](#toc-pt)

---

### ⚖️ Painel de Saúde da Economia

A aba **Configurações** do painel de gerenciamento inclui um widget de **Saúde da Economia** que compara o total de XP que um estudante pode ganhar (todos os itens × seus limites de drop + recompensas de missões) com o teto de XP configurado (XP por nível × número de níveis).

| Cobertura | Status |
|---|---|
| Exatamente 100% | ✅ Verde — "Configuração equilibrada" |
| Abaixo de 100% | ⚠️ Amarelo — os estudantes não conseguem atingir o nível máximo; adicione mais itens ou missões, ou reduza o teto |
| Acima de 100% | 🔴 Vermelho — os estudantes podem ultrapassar o teto; reduza o XP de itens/missões ou aumente o teto |

O widget também exibe uma tabela expansível com o detalhamento de cada item e missão e sua contribuição individual de XP, facilitando a identificação do conteúdo que está contribuindo mais ou menos para a economia.

[⬆️ Voltar ao índice](#toc-pt)

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

[⬆️ Voltar ao índice](#toc-pt)

---

### 🕹️ Ecossistema PlayerGames

O PlayerHUD faz parte do ecossistema de gamificação **PlayerGames**. Juntos, esses plugins transformam o Moodle em uma experiência imersiva:

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do aluno ou nos itens coletados.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

* **PlayerGroup:** Permite que os alunos formem seus próprios grupos de forma autônoma diretamente na página da atividade — sem necessidade de intervenção do professor.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

[⬆️ Voltar ao índice](#toc-pt)

---

### 🧩 Integração Opcional: Late Penalty

O poder de item **Extensão de Prazo** (veja [Funcionalidades](#-funcionalidades)) não funciona sozinho — ele depende do plugin separado **Late Penalty** (`local_latepenalty`, do mesmo autor, mas que **não** faz parte da família PlayerGames). Quando instalado, resgatar um item de Extensão de Prazo adia o prazo efetivo da atividade para aquele estudante e aciona o recálculo automático do Late Penalty, dispensando ou reduzindo qualquer penalidade de nota por atraso já aplicada. Sem o Late Penalty instalado, o poder do item falha de forma controlada, mostrando uma mensagem de "não instalado" em vez de conceder a extensão.

👉 https://github.com/jeanlucio/moodle-local_latepenalty

[⬆️ Voltar ao índice](#toc-pt)

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

[⬆️ Voltar ao índice](#toc-pt)

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

[⬆️ Voltar ao índice](#toc-pt)

---

### 📖 Como Usar

1. Adicione o **Bloco PlayerHUD** ao seu curso.
2. Acesse o **Painel de Gerenciamento** (necessário perfil de Professor).
3. Escolha como configurar:
   - **Início rápido:** rode o **Assistente de Gamificação** para gerar automaticamente itens, missões, ranking e outras mecânicas em uma única rodada (veja [Funcionalidades](#-funcionalidades) acima).
   - **Configuração manual:** configure cada mecânica você mesmo:
     - Itens
     - Valores de XP
     - Quantidade de níveis
     - Limiares de XP para progressão
     - Posicionamento de drops
     - Tempo de Recarga (intervalo entre coletas)
     - Limites de coleta
4. Os alunos coletam itens diretamente nas seções do curso.
5. O sistema atualiza automaticamente XP, níveis e ranking.

[⬆️ Voltar ao índice](#toc-pt)

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
* 3 classes RPG com retratos evolutivos de 5 etapas: Guerreiro, Mago, Ladino
* 5 itens com diferentes valores de XP, cooldowns e limites de coleta
* 5 drops inseridos em atividades do curso via shortcodes (modos de exibição: card, imagem e texto)
* 9 quests cobrindo todos os tipos de conclusão (nível, XP total, itens únicos/específicos, trocas)
* 2 capítulos de história com escolhas ramificadas e efeitos de reputação
* 2 ofertas de troca (loja NPC), uma delas já concluída por um aluno
* Um esquadrão de ranking de grupos (3 dos 5 alunos agrupados, 2 deixados sem grupo de propósito)
* Um item "Extensão de Prazo" ligado a uma penalidade real já aplicada pelo [Late Penalty](#-integração-opcional-late-penalty) — só é semeado se `local_latepenalty` estiver instalado
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

[⬆️ Voltar ao índice](#toc-pt)

---

### 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `ai/generator_test.php` | 2 | `save_item()` (via reflection, sem rede): limita um nome gerado por IA acima do tamanho; converte campos não-string antes de persistir |
| `backup_restore_test.php` | 3 | Definições de backup/restore cobrem todas as tabelas RPG; round-trip completo de curso (incl. uma atividade real) preserva dados de classe/capítulo/história RPG, poderes de item (`action_type`/`action_value`), tiers de emoji da classe, e o requisito de uma quest `TYPE_SPECIFIC_TRADE` remapeado contra a troca restaurada em vez do mapeamento de item; um cmid fixado no `deadline_extension` e o requisito de uma quest `TYPE_ACTIVITY` são ambos remapeados para a atividade do curso restaurado |
| `collection_tab_test.php` | 6 | Aba Coleção: mapeamento de `filter_type` (avatar/prazo/nenhum), `power_hint_avatar` exibido para item não-secreto não possuído e oculto para secreto, flag `is_equipped` |
| `content_crud_test.php` | 13 | CRUD de itens, capítulos e trocas: criação persiste todos os campos, atualização altera campos, exclusão remove registro, listagem escoped por instância |
| `cross_instance_security_test.php` | 12 | Isolamento cross-instance: guardas de item, quest, capítulo e troca aceitam IDs da própria instância e rejeitam IDs alheios sem modificar o registro alvo |
| `drop_guard_test.php` | 7 | Limites de coleta, itens consumidos por troca, aplicação de cooldown |
| `game_test.php` | 32 | Agregação de XP e nível, XP de quests (inclusão/exclusão), anti-farm de coleta e cooldown; `get_avatar_item` (habilitado, desabilitado, instância estrangeira, não encontrado); XP concedido ao coletar drop com uso finito; exclusão de gerentes do ranking; flags de milestone de level-up, vitória no jogo e primeira PlayerCoin na coleta; `xp_to_level`; criação automática de jogador, alternância de gamificação e visibilidade no ranking, inventário (exclui revogados/consumidos), `has_item`; `get_user_rank` ordem por XP, desempate por chegada, exclusão de gerentes e de não matriculados; hidratação de requisitos/recompensas em `get_full_trades`; heurística de sugestões de troca (avatares com desconto, pulo de avatar já coberto, pré-requisitos) e persistência; `change_xp` emite o evento `xp_changed` ao conceder, ao deduzir (piso em zero) e fica em silêncio num no-op de verdade |
| `gamemaster_test.php` | 6 | Conceder/revogar/excluir item e quest preservando timestamps do ranking; XP mínimo em zero |
| `instance_delete_test.php` | 1 | Excluir uma instância do bloco limpa todas as tabelas próprias do plugin (`instance_cleanup`) |
| `item_delete_cascade_test.php` | 15 | Detecção de trocas órfãs ao excluir item (único req, um de dois, único reward, combinado req+reward); verificações em lote; isolamento cross-instance; exclusão remove o item e cascateia trocas órfãs sem afetar as não-órfãs |
| `karma_test.php` | 11 | Leitura/escrita de karma, deltas positivos/negativos, clamping nos limites ±999, acumulação sucessiva |
| `privacy_provider_test.php` | 10 | LGPD com cobertura completa: descoberta de contexto/usuário (`get_contexts_for_userid`, `get_users_in_context`); `export_user_data` nas seis subárvores (perfil, RPG, inventário, missões, trocas, logs de IA); exclusão por usuário, multiusuário e de contexto inteiro com garantia de isolamento; exportação/exclusão de toda chave de API e preferência de avatar; declaração de metadados; guardas de contexto não-bloco como no-ops |
| `quest_test.php` | 33 | Verificações de conclusão (nível, XP, itens, trocas, conclusão de atividade); reivindicar recompensas; quest desabilitada; idempotência; flags de comemoração de level-up e vitória no jogo ao reivindicar recompensa; `has_claimable_quests` em todos os tipos de requisito incl. conclusão de atividade, com curto-circuito de reivindicadas/não reivindicadas; mapeamento de `build_record_from_suggestion`, transporte de item-ids e piso do override de XP; `get_heuristic_suggestions` milestones de nível/coleção/economia/atividade com pulo de duplicatas |
| `rpg_classes_test.php` | 7 | Atribuição de classe, proteção contra duplicatas, inicialização de karma, limites de tier de retrato |
| `story_manager_test.php` | 15 | Carregamento de cena, persistência de progresso, navegação de escolhas, delta de karma, conclusão de capítulo, casos de erro |
| `suggest_trades_state_test.php` | 4 | Botão Sugerir Trocas: desabilitado sem pré-requisitos, desabilitado só com moeda, desabilitado quando todos os avatares cobertos, habilitado com cobertura parcial |
| `trade_test.php` | 7 | Montagem de trocas, fundos insuficientes, sucesso atômico, limite único, restrição por grupo |
| `utils_test.php` | 4 | `get_avatar_html`: emoji gera div `ph-avatar-emoji` com span aria-hidden; URL HTTP gera tag img `ph-avatar-img`; imagem nula não lança exceção em `get_avatar_html` nem em `get_items_display_data` |
| **Subtotal** | **188** | |

#### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

Lógica reutilizada por mais de um ponto de entrada (as próprias web services do assistente, a tela manual de "Distribuir Drops", o painel Economy Health), testada diretamente em vez de só indiretamente através de quem quer que a chame.

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `analytics_test.php` | 9 | Economy Health: razão entre XP total ganhável e o teto (vazio/difícil/perfeito/fácil), recompensas de quest e itens infinitos/sem drop no detalhamento, guarda de teto zero; histograma de distribuição de níveis, ordenação do overflow do cap (`N+`), percentual da barra mais alta, guarda de XP-por-nível zero |
| `drop_distribution_test.php` | 12 | Descoberta de módulos elegíveis: inclui fóruns, exclui módulos em exclusão e o fórum de avisos do curso (reservado para PlayerCoin/Item Secreto), vazio para curso sem atividades; sugestão por melhor correspondência de nome incl. caso sem correspondência; busca de cmid por shortcode já inserido incl. não encontrado e entrada vazia; divisão de cotas por atividade sempre soma o alvo, limita ao número de atividades, casos de borda |
| `wizard_test.php` | 17 | Manifesto da rodada: status de início/fim; desfazer exclui objetos registrados em todas as tabelas, remove o shortcode registrado, reverte XP e limpa o histórico de jogo, rejeita instância incompatível; listagem de rodadas ativas com contagens e limite; detecção de "já gerado" por mecânica incl. rodadas obsoletas sem conteúdo, itens só no manifesto, itens só logados pela IA e a checagem só-de-config do Ranking; `ensure_config_flag` liga uma flag sem tocar em config irmã e não faz nada quando já está ligada |
| `xp_budget_test.php` | 15 | Contagens de item/missão/capítulo por tamanho de jornada incl. fallback pra curta; `distribute_share` divide a folga igualmente, espalha o resto nos primeiros elementos, limita à folga quando há mais elementos que ela, casos de borda; mapeamento de níveis-máximos sugeridos; rodízio balanceado de missões entre tipos, preservação de ordem dentro de um tipo, todas selecionadas quando o limite as cobre, casos de borda |
| **Subtotal** | **53** | |

#### Testes de Web Services (`tests/external/`)

Uma classe de teste por função de web service, validando o contrato da API externa, conformidade de parâmetros e estrutura de retorno (`external_api::clean_returnvalue`), e guardas de capability. As funções de IA são testadas sem rede — sem chave de API configurada, o bloco `try/catch` retorna `success=false`, que é assegurado diretamente.

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `chat_message_test.php` | 2 | Sem chave de API → `moodle_exception`; guarda de capability (`manage`) |
| `collect_item_test.php` | 4 | Item coletado + registro de inventário criado; drop inválido → `success=false`; limite atingido → `success=false`; guarda de capability (`view`) |
| `create_avatar_pack_test.php` | 6 | 17 itens criados; ids e nomes retornados em lockstep; todos com `action_type=avatar`; deduplicação por emoji; segunda chamada cria 0 (idempotência); guarda de capability |
| `create_class_pack_test.php` | 7 | Cria 3 classes; tiers de HP base conforme esperado; pula nome já existente; segunda chamada cria 0 (idempotência); tons diferentes geram nomes diferentes; tom desconhecido cai no fallback fantasia; guarda de capability |
| `create_playercoin_test.php` | 3 | Novo item criado; segunda chamada retorna existente (idempotência); guarda de capability |
| `execute_chat_action_test.php` | 4 | `action_open_tab` retorna URL de redirect (determinístico, sem IA); tipo de ação desconhecido → `success=false`; parâmetros inválidos → `success=false`; guarda de capability |
| `generate_ai_content_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_class_oracle_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `generate_story_test.php` | 2 | Sem chave de API → `success=false`; guarda de capability (`manage`) |
| `insert_drop_shortcode_test.php` | 7 | Shortcode inserido no campo de conteúdo do módulo; inserção duplicada rejeitada; drop de outra instância rejeitado; drop renomeado pra atividade em que caiu; `mode=text` com rótulo customizado; modo desconhecido cai pra card; guarda de capability |
| `load_recap_test.php` | 3 | HTML de recap gerado após visita à cena; sem histórico → exceção; guarda de capability (`view`) |
| `load_scene_test.php` | 3 | Nó inicial e escolhas retornados; capítulo inválido → exceção; guarda de capability (`view`) |
| `make_choice_test.php` | 3 | Avança a história até o nó de destino; escolha inválida → exceção; guarda de capability (`view`) |
| `remove_drop_shortcode_test.php` | 5 | Shortcode existente removido; shortcode separado por `<br>` removido; shortcode com atributos `mode=`/`text=` removido; ausência de shortcode é noop sem erro; guarda de capability |
| `setup_playercoin_drop_test.php` | 6 | Sucesso; sem fórum → `success=false`; item de outra instância rejeitado; curso que não é dono da instância rejeitado; shortcode anteposto ao intro existente; guarda de capability |
| `use_item_test.php` | 6 | Guarda de capability (`view`); item não possuído → exceção; poder de prazo: sem atividade, sem regra, cria override e consome item, atualiza override existente |
| `wizard_apply_suggested_levels_test.php` | 3 | Aplica a sugestão quando a config está nos padrões; ainda aplica quando a config já foi customizada; preserva todo outro campo de config intocado |
| `wizard_generate_helpers_test.php` | 9 | `build_step_types()` bate com os módulos selecionados na ordem, pula `auto_distribute` quando o distribuir de Itens está desligado, vazio quando nada selecionado; `compute_shared_xp_shares()` vazio sem Itens/Missões, Pill/Extensão de Prazo usam seus próprios padrões sozinhos, dividem o orçamento com Itens quando combinados; `resolve_or_create_progress_item()` idempotente e cria um item completo quando falta; `resolve_previous_chapter_context()` lê o capítulo mais recente |
| `wizard_list_runs_test.php` | 4 | Resumo de uma rodada ativa; rodada de RPG resumida; rodadas desfeitas excluídas; guarda de capability |
| `wizard_run_step_test.php` | 56 | Um passo de progresso ao vivo por vez, por mecânica (PlayerCoin, Avatares, Missões, Comércio, Colecionável de Conhecimento, Item Secreto, Ranking, Extensão de Prazo, RPG, Item RPG, auto-distribuir): criação de item/quest/troca com registro no manifesto, retentativas idempotentes, desfazer por mecânica, controle pela flag de distribuir, tom/tamanho de jornada influenciando o conteúdo, e a inserção exclusiva no fórum de avisos pra PlayerCoin e Item Secreto (incl. no-op sem fórum de avisos); tipo de passo desconhecido, guarda de capability, rejeição de `runid` de outra instância, passo com falha não finaliza a rodada, passo final reporta a economia só quando solicitado |
| `wizard_start_test.php` | 8 | Um passo de plano por módulo selecionado; a flag de "passo lento" reflete se Próximo Capítulo foi selecionado; a divisão de cotas de XP bate com os módulos selecionados; o XP bônus da Pill presente quando selecionada sozinha; o módulo de arco da história se expande num outline + um passo por capítulo, a quantidade de passos cresce com o tamanho da jornada, o manifesto mantém o nome lógico do módulo; guarda de capability |
| **Subtotal** | **145** | |

#### Testes de Controlador (`tests/controller/`)

Cobrem a lógica de negócio extraída do `manage.php` para os controladores (refatoração MVC), cada um exercitado com entradas explícitas e isolamento de instância.

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|----------------|
| `aikeys_test.php` | 4 | Armazenamento de chaves de IA: chaves aparadas e salvas como preferências do usuário, padrão vazio para campo ausente, chaves legadas removidas do config do bloco, config limpo intocado |
| `chapters_test.php` | 13 | Persistência e ordenação de capítulos: salvar (inserir, atualizar, padrões, isolamento), excluir em cascata cenas/escolhas, mover/reordenar com renumeração da lista completa, no-op na borda |
| `classes_test.php` | 7 | Persistência de classe RPG: inserção (HP base, vínculo de instância, emojis por tier), atualização preserva HP base, trim de emoji, isolamento; exclusão remove registro e retratos por tier, isolamento, irmãos preservados |
| `collect_test.php` | 3 | Transação de coleta de item: drop finito concede XP, drop infinito concede 0 XP (regra de ouro), item de 0 XP armazenado sem alterar XP |
| `drops_test.php` | 11 | Persistência de drop: salvar (inserir + código, ilimitado, atualizar preserva propriedade, isolamento, item estrangeiro); excluir único e no-op estrangeiro; exclusão em massa só dos próprios com contagem, entrada vazia; `get_owned_item` retorna para a instância dona e rejeita instância estrangeira |
| `export_test.php` | 7 | Construtor da exportação de notas: campos da linha e nível derivado, ordenação por XP, teto de nível, exclusão de professores/gerentes, colunas localizadas sem jogadores, exclusão de não matriculados, desempate por última ação |
| `items_test.php` | 11 | Ciclo de vida do item: toggle de ativação e no-op estrangeiro; conceder adiciona inventário + XP, 0 XP, rejeição estrangeira; revogar desconta XP, preserva drop infinito, no-op estrangeiro; detecção de trocas sobreviventes (troca aparada, órfã excluída, não relacionada ignorada) |
| `quests_test.php` | 7 | Ciclo de vida da missão: toggle e no-op estrangeiro; excluir reverte XP por conclusão, sem recompensa, no-op estrangeiro; massa só dos próprios com reversão agregada de XP e contagem, entrada vazia |
| `scenes_test.php` | 6 | Persistência de cena/escolha da história: salvar escolhas, atribuição de classe com normalização de ID string/int (regressão `set_class_id`), classe requerida, próximo nó, custo de item, criação de nó de continuação |
| `suggestions_test.php` | 4 | Persistência de sugestões: só as missões marcadas são inseridas (e nenhuma selecionada), só as trocas marcadas são criadas com reqs/recompensas (e nenhuma selecionada) |
| `trades_test.php` | 7 | Persistência de troca: salvar (inserir com reqs + recompensas, atualizar substitui, isolamento, item estrangeiro filtrado); excluir em cascata reqs/recompensas/log, isolamento, irmãos preservados |
| **Subtotal** | **80** | |

#### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos | O que é coberto |
|------------------|------:|----------------|
| `manage/item_delete_confirm_test.php` | 6 | Contexto da confirmação de exclusão de item: ação única vs massa e payload de IDs, rótulos de confirmação singular/plural/simples, seções só-sobreviventes e órfãs+sobreviventes |
| `manage/tab_chapters_test.php` | 4 | Avisos de visibilidade do card de capítulo: sinalização de cena inicial ausente, texto e limites do aviso de nível acima do máximo |
| **Subtotal** | **10** | |

| **Total geral** | **476** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
| `ai\generator` | 6% |
| `controller\aikeys` | 100% |
| `controller\chapters` | 40% |
| `controller\classes` | 41% |
| `controller\collect` | 13% |
| `controller\drops` | 18% |
| `controller\export` | 90% |
| `controller\items` | 79% |
| `controller\quests` | 71% |
| `controller\scenes` | 15% |
| `controller\suggestions` | 100% |
| `controller\trades` | 39% |
| `drop_guard` | 100% |
| `event\xp_changed` | 43% |
| `external\chat_message` | 67% |
| `external\collect_item` | 100% |
| `external\create_avatar_pack` | 84% |
| `external\create_class_pack` | 79% |
| `external\create_playercoin` | 91% |
| `external\execute_chat_action` | 27% |
| `external\generate_ai_content` | 76% |
| `external\generate_class_oracle` | 67% |
| `external\generate_story` | 75% |
| `external\insert_drop_shortcode` | 87% |
| `external\load_recap` | 100% |
| `external\load_scene` | 79% |
| `external\make_choice` | 79% |
| `external\remove_drop_shortcode` | 84% |
| `external\setup_playercoin_drop` | 90% |
| `external\use_item` | 75% |
| `external\wizard_apply_suggested_levels` | 83% |
| `external\wizard_generate` | 18%¹ |
| `external\wizard_list_runs` | 100% |
| `external\wizard_run_step` | 86% |
| `external\wizard_start` | 99% |
| `game` | 84% |
| `instance_cleanup` | 100% |
| `local\analytics` | 81% |
| `local\drop_distribution` | 97% |
| `local\wizard` | 76% |
| `local\xp_budget` | 98% |
| `output\manage\item_delete_confirm` | 100% |
| `output\manage\tab_chapters` | 7% |
| `output\view\tab_collection` | 68% |
| `privacy\provider` | 96% |
| `quest` | 90% |
| `story_manager` | 37% |
| `trade_manager` | 90% |
| `utils` | 35% |
| **Total** | **37%** |

¹ Subestimado pela ferramenta de cobertura: os métodos `generate_*()` de `wizard_generate` só são chamados estaticamente a partir de `wizard_run_step`, e a instrumentação de linha atribui a chamada à linha de quem CHAMA, não de quem é chamado — os 56 casos de `wizard_run_step_test.php` exercitam cada um deles diretamente.

#### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|-------------------|--------:|----------------|
| `block_playerhud_access.feature` | 3 | Visibilidade do bloco por perfil (professor adiciona bloco, aluno vê HUD, não matriculado não vê) |
| `block_playerhud_student.feature` | 4 | HUD ativo na primeira visita, desativar/reativar gamificação, dispensar confirmação |
| `block_playerhud_teacher.feature` | 7 | Botão do Painel do Mestre, acesso ao painel de gerenciamento, navegação entre abas, retorno ao curso; abrir o log de auditoria de um aluno em Relatórios não dá erro |
| `block_playerhud_modals.feature` | 5 | Abrir/fechar modal de detalhes do item, proteção contra abertura duplicada, coleta AJAX sem redirecionamento, sem placeholders brutos |
| `block_playerhud_celebrations.feature` | 2 | Introdução do Huddy exibida uma única vez no painel; aviso de primeira quest exibido uma única vez quando há recompensa a reivindicar |
| `block_playerhud_wizard.feature` | 6 | Assistente abre mostrando o formulário de geração; abas laterais de Ajuda e Recomendações externas; gerar PlayerCoin de ponta a ponta mostra o relatório de sucesso; o card do PlayerCoin trava depois de gerado; desfazer uma rodada pelo Histórico destrava de novo |
| **Total** | **27** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```

[⬆️ Voltar ao índice](#toc-pt)

---

### 🔐 Segurança e Conformidade

- Controle de acesso baseado em capabilities
- Validação no servidor do tempo de recarga e limites
- Proteção com `require_sesskey()`
- Compatível com a API externa do Moodle
- Participação no ranking com controle de privacidade

[⬆️ Voltar ao índice](#toc-pt)

---

### 🔎 Divulgação de Serviço de Terceiros

O PlayerHUD inclui recursos opcionais de IA: um **Gerador de Conteúdo** (itens, capítulos, backstories de classes) e um **Assistente Game Master** (chat conversacional para professores que também pode acionar ações no jogo).

### O recurso de IA é obrigatório?

Não. O plugin funciona de forma completa sem qualquer serviço externo.
Todo o conteúdo pode ser criado manualmente dentro do Moodle.
Os recursos de IA são ferramentas de produtividade — o assistente exige confirmação antes de salvar qualquer coisa.

### Cadeia de Provedores de IA

O PlayerHUD seleciona o provedor de IA **nível por nível**, seguindo a escada compartilhada do ecossistema PlayerGames. Uma chave explicitamente configurada sempre prevalece sobre o padrão institucional; o `core_ai` fica na base.

**Escada de resolução (maior prioridade primeiro):**

| Nível | Origem |
|-------|--------|
| 1 | **Chave pessoal própria** — chave do professor cadastrada no PlayerHUD (aba *Configurações* → Chaves de API) |
| 2 | **Chave pessoal do hub** — chave do professor cadastrada no **local_aihub** (se instalado) |
| 3 | **Chave de site própria** — chave cadastrada pelo admin nas configurações do PlayerHUD |
| 4 | **Chave de site do hub** — chave cadastrada pelo admin nas configurações do **local_aihub** (se instalado) |
| 5 | **Moodle `core_ai`** — provedores configurados em *Administração do site → IA → Provedores de IA*. Nenhuma chave armazenada no PlayerHUD. |

**Nível primeiro, não provedor primeiro.** Cada nível acima é avaliado como um todo: o primeiro nível que contiver *qualquer* chave é usado exclusivamente. Assim, a chave pessoal do professor (nível 1) sempre prevalece sobre uma chave do hub (nível 2) — mesmo que a chave do hub seja de um provedor de maior prioridade. Por exemplo, a chave de endpoint personalizado do professor não é substituída por uma chave Gemini que esteja no hub. O `core_ai` é consultado apenas quando nenhum nível possui uma chave.

**Dentro do nível escolhido**, os provedores diretos são testados na ordem Gemini → Groq → OpenAI-compatível (a primeira chave encontrada é usada; se a chamada falhar, o próximo é tentado).

Isso também significa: se o professor configurou sua própria chave no hub PlayerGames, o PlayerHUD a utiliza automaticamente — sem necessidade de recadastrar no PlayerHUD.

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

1. **Chave pessoal no PlayerHUD** — configurada individualmente por cada professor na aba *Configurações* do painel de gerenciamento.
2. **Chave pessoal na Central de IA** — configurada pelo professor em *local_aihub → Minhas chaves de IA* (se o hub estiver instalado).
3. **Chave de site no PlayerHUD** — configurada pelo admin em *Administração do site → Plugins → Blocos → PlayerHUD*.
4. **Chave de site na Central de IA** — configurada pelo admin nas configurações do *local_aihub* (se o hub estiver instalado).
5. **Moodle `core_ai`** — configurado pelo admin em *Administração do site → IA → Provedores de IA* (nenhuma chave armazenada no PlayerHUD; consultado apenas quando nenhuma das origens acima tiver chave configurada).

### Transmissão de dados

Quando o recurso de IA é utilizado, os prompts informados são enviados ao provedor selecionado para processamento.

O plugin:
- Não armazena prompts nem histórico de conversa (o histórico do chat é apenas da sessão, no navegador)
- Não armazena respostas brutas da IA
- Apenas salva os objetos do jogo criados dentro do Moodle (itens, missões, capítulos)

Nenhuma comunicação externa ocorre sem ativação explícita de um recurso de IA.

[⬆️ Voltar ao índice](#toc-pt)

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Voltar ao índice](#toc-pt)
