# 🧪 Automated Tests

PlayerHUD ships with an extensive test suite covering both business logic (PHPUnit) and browser acceptance (Behat). Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai/generator_test.php` | 2 | `save_item()` (reached via reflection, no network): clamps an overlong AI-provided name; coerces non-string fields before persisting |
| `backup_restore_test.php` | 3 | Backup/restore step definitions cover all RPG tables; full course round-trip (incl. a real activity) preserves RPG class/chapter/story data, item powers (`action_type`/`action_value`), class emoji tiers, and a `TYPE_SPECIFIC_TRADE` quest's requirement remapped against the restored trade rather than the item mapping; a pinned `deadline_extension` cmid and a `TYPE_ACTIVITY` quest's requirement are both remapped to the restored course's own activity |
| `collection_tab_test.php` | 8 | Collection tab: `filter_type` mapping (avatar/deadline/none), `power_hint_avatar` shown for unowned non-secret item and hidden for secret item, `is_equipped` flag; origin classification for an inventory row's source (map is recognised as PlayerHUD's own; anything outside the 4 known sources falls back to a generic "game" origin) |
| `content_crud_test.php` | 13 | Item, chapter and trade CRUD: create persists all fields, update changes fields, delete removes record, listing scoped to instance |
| `cross_instance_security_test.php` | 12 | Cross-instance isolation: item, quest, chapter and trade guards accept own-instance IDs and reject foreign ones without modifying the target record |
| `drop_guard_test.php` | 7 | Collection limits, trade-consumed items, cooldown enforcement |
| `game_test.php` | 36 | `get_game_stats()` totals XP/level plus quest XP inclusion (and exclusion when the quest is disabled), cross-checked against `analytics::economy_health()`'s own total; collection anti-farm and cooldown; `get_avatar_item` (enabled, disabled, foreign instance, not found); XP award on finite drop; leaderboard manager exclusion; level-up, beat-the-game and first-PlayerCoin milestone flags on collection; `xp_to_level`; player auto-creation, gamification and ranking-visibility toggles, inventory (revoked/consumed excluded), `has_item`; `get_user_rank` XP order, tie-break by arrival, manager and enrolment exclusion; `get_full_trades` requirement/reward hydration, empty case, and availability gating when either side's item is disabled; trade-suggestion heuristics (discounted avatars, covered-avatar skip, prerequisites) and persistence; `change_xp` emits the `xp_changed` event on award, on deduction (floored at zero) and stays silent on a true no-op |
| `gamemaster_test.php` | 6 | Grant/revoke/delete item and quest while preserving leaderboard timestamps; XP floor at zero |
| `instance_delete_test.php` | 1 | Deleting a block instance cleans every one of this plugin's own tables (`instance_cleanup`) |
| `item_delete_cascade_test.php` | 17 | Trade orphan detection when item deleted (sole req, one-of-two, sole reward, combined req+reward); bulk orphan checks; cross-instance isolation; delete removes item record and cascades orphaned trades without touching non-orphaned ones; deleting an item (single or bulk) reverts XP only for copies that actually earned it, leaving infinite-drop (zero-XP) copies untouched |
| `karma_test.php` | 11 | Karma read/write, positive/negative deltas, clamping at ±999 boundaries, successive accumulation |
| `privacy_provider_test.php` | 10 | GDPR full coverage: context/user discovery (`get_contexts_for_userid`, `get_users_in_context`); `export_user_data` across all six subtrees (profile, RPG, inventory, quests, trades, AI logs); per-user, multi-user and whole-context deletion with isolation guarantees; export/delete of every API-key and avatar preference; metadata declaration; non-block context guards are no-ops |
| `quest_test.php` | 34 | Completion checks (level, XP, items, trades, activity completion); claim rewards; disabled quest; idempotency; level-up and beat-the-game celebration flags on reward claim; `has_claimable_quests` across every requirement type incl. activity completion, with claimed/unclaimed short-circuit; `build_record_from_suggestion` mapping, item-id carrying and XP override floor; `get_heuristic_suggestions` level/collection/economy/activity milestones with duplicate skipping; a completion-tracked activity offered as a heuristic quest is detected as fulfilled once the activity is actually completed |
| `rpg_classes_test.php` | 7 | Class assignment, duplicate guard, karma initialisation, portrait tier boundaries |
| `story_manager_test.php` | 15 | Scene loading, progress persistence, choice navigation, karma delta, chapter completion, error cases |
| `suggest_trades_state_test.php` | 4 | Suggest Trades button: disabled without prereqs, disabled with coin only, disabled when all avatars covered, enabled on partial coverage |
| `trade_test.php` | 8 | Trade assembly, insufficient funds, atomic success, one-time limit, group restriction; a trade referencing a disabled reward item is rejected outright even with sufficient funds |
| `utils_test.php` | 4 | `get_avatar_html`: emoji produces `ph-avatar-emoji` div with aria-hidden span; HTTP URL produces `ph-avatar-img` img tag; a null image does not throw for `get_avatar_html` nor `get_items_display_data` |
| **Subtotal** | **198** | |

### Local Business-Logic Tests (`tests/local/`)

Shared logic reused by more than one entry point (the wizard's own web services, the manual "Distribute Drops" screen, the Economy Health panel), tested directly rather than only indirectly through whichever controller happens to call it.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `analytics_test.php` | 11 | Economy Health: total earnable XP vs ceiling ratio (empty/hard/perfect/easy), quest rewards and infinite/dropless items in the breakdown, zero-ceiling guard; level-distribution histogram bucketing, cap overflow (`N+`) ordering, percent of tallest bar, zero-XP-per-level guard, empty player set produces no rows; `balance_context()`'s current XP always matches `economy_health()`'s own total |
| `audit_log_test.php` | 5 | Shared audit-log query (`get_logs()`) behind the teacher Reports tab and the student History tab: an item's `xp_gained` reflects the recorded `xpawarded` value at grant time, not the item's current XP (and matches when never edited); a quest-granted item reports zero `xp_gained` since its own XP is never paid through that path; a revoked row reports the negative of its originally recorded value, not the item's current XP; a quest claim's `xp_gained` reflects the recorded value, not the quest's current `reward_xp` |
| `drop_distribution_test.php` | 12 | Eligible-modules discovery: includes forums, excludes modules pending deletion and the course's own news forum (reserved for PlayerCoin/Secret Item), empty for an activity-less course; best-name-match suggestion incl. no-match case; inserted-shortcode cmid lookup incl. not-found and empty-input cases; activity-quota splitting always sums to target, caps at activity count, edge cases |
| `external_items_test.php` | 18 | Cross-plugin item API used by other Player-family plugins (e.g. PlayerWords): `belongs_to_instance()` accepts an item's own instance (enabled or disabled) and rejects a foreign instance, a nonexistent id, or zero/negative ids without querying the database; `grant()` inserts one inventory row per unit with its own `xpawarded` and credits the total XP once, withholds XP when the caller flags the source as unbounded, and is a no-op for a foreign-instance or disabled item; `consume()` marks the oldest rows consumed on success, returns false when the balance is insufficient, and returns null (not false) for a foreign-instance item so the caller waives the cost instead of blocking the student forever; `get_name()`/`get_xp()` resolve for the item's own instance and return empty/zero for a foreign one; `get_available_quantity()` counts only active (non-revoked/non-consumed) rows and is zero for a foreign-instance item even if the user holds units of it |
| `wizard_test.php` | 17 | Run manifest: start/finish status; rollback deletes recorded objects across tables, strips the recorded shortcode, reverts XP and clears play history, rejects a mismatched instance; active-runs listing with counts and a limit; per-module "already generated" detection incl. stale runs without content, manifest-only items, AI-logged-only items and Ranking's config-only check; `ensure_config_flag` turns a flag on without touching sibling config and is a no-op when already on |
| `xp_budget_test.php` | 15 | Item/mission/chapter counts per journey size incl. fallback to short; `distribute_share` divides a gap evenly, spreads the remainder on the first elements, caps at the gap when elements outnumber it, edge cases; suggested max-levels mapping; balanced-mission round-robin across types, order preservation within a type, all-selected when the limit covers them, edge cases |
| **Subtotal** | **78** | |

### Web Services Tests (`tests/external/`)

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
| `wizard_generate_helpers_test.php` | 10 | `build_step_types()` matches selected modules in order, skips `auto_distribute` when Items' own distribute flag is off, empty when nothing selected; `compute_shared_xp_shares()` empty without Items/Missions, Pill/Latepenalty use their own defaults alone, share the budget with Items when combined; `resolve_or_create_progress_item()` idempotent and creates a complete item when missing; `resolve_previous_chapter_context()` reads the latest chapter; `distribute_drops()` caps each activity to its computed quota instead of letting name-matching alone stack every drop onto one activity |
| `wizard_list_runs_test.php` | 4 | Summary for an active run; RPG run summarised; rolled-back runs excluded; capability guard |
| `wizard_run_step_test.php` | 56 | One live-progress step at a time, per mechanic (PlayerCoin, Avatars, Missions, Trade, Knowledge Pill, Secret Item, Ranking, Deadline Extension, RPG, Item RPG, auto-distribute): item/quest/trade creation with manifest recording, idempotent retries, rollback per mechanic, distribute-flag gating, tone/journey-size flavouring, and the news-forum-only placement for PlayerCoin and Secret Item (incl. no-op without a news forum); unknown step type, capability guard, cross-instance `runid` rejection, failed step does not finish the run, final step reports the economy only when requested |
| `wizard_start_test.php` | 8 | One plan step per selected module; the "slow step" flag reflects whether Next Chapter was selected; XP shares split matches selected modules; Pill's bonus XP present when selected alone; the story-arc module expands into an outline + one step per chapter, step count grows with journey size, manifest keeps the logical module name; capability guard |
| **Subtotal** | **146** | |

### Controller Tests (`tests/controller/`)

These cover the business logic extracted from `manage.php` into the controllers (MVC refactor), each exercised with explicit inputs and instance isolation.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `aikeys_test.php` | 4 | AI key storage: keys trimmed and saved as user preferences, empty default for a missing field, legacy keys stripped from block config, clean config left untouched |
| `chapters_test.php` | 13 | Chapter persistence and ordering: save (insert, update, defaults, isolation), delete cascading scenes/choices, reorder/move with full-list renumbering, edge no-op |
| `classes_test.php` | 7 | RPG class persistence: insert (base HP, instance binding, emoji tiers), update preserves base HP, emoji trimming, isolation; delete removes record and tier portraits, isolation, siblings kept |
| `collect_test.php` | 3 | Item collection transaction: finite drop awards XP, infinite drop awards 0 XP (golden rule), zero-XP item stored without XP change |
| `drops_test.php` | 11 | Drop persistence: save (insert + code, unlimited, update preserves ownership, isolation, foreign item); delete single and foreign no-op; bulk deletes only owned with count, empty input; `get_owned_item` returns for the owning instance and rejects a foreign one |
| `export_test.php` | 7 | Grade export builder: row fields and derived level, XP ordering, level cap, teacher/manager exclusion, localized columns with no players, unenrolled exclusion, XP tie-break by last action |
| `items_test.php` | 15 | Item lifecycle: enable toggle and foreign no-op; grant adds inventory + XP, zero-XP, foreign rejection; revoke deducts XP, infinite-drop preservation, foreign no-op; revoke deducts the XP actually recorded at grant time, not the item's current XP; surviving-trade detection (trimmed trade, orphaned excluded, unrelated ignored); `find_xp_impact` aggregates only copies that actually earned XP across all holders, empty for an unheld item, and a no-op for an empty id list |
| `quests_test.php` | 12 | Quest lifecycle: toggle and foreign no-op; delete reverts XP per completion, zero-reward, foreign no-op; delete and bulk-delete revert the XP actually recorded per completion, not the quest's current reward; bulk deletes only owned with aggregated XP revert and count, empty input; `find_xp_impact` aggregates only completions that actually earned XP across all claimants, empty for an unclaimed quest, and a no-op for an empty id list |
| `scenes_test.php` | 6 | Story scene/choice persistence: save choices, class assignment with string/int ID normalisation (`set_class_id` regression), required class, next node, item cost, follow-up node creation |
| `suggestions_test.php` | 4 | Suggestion persistence: only ticked quest suggestions inserted (and none selected), only ticked trade suggestions created with reqs/rewards (and none selected) |
| `trades_test.php` | 7 | Trade persistence: save (insert with reqs + rewards, update replaces, isolation, foreign item filtered); delete cascading reqs/rewards/log, isolation, siblings kept |
| **Subtotal** | **89** | |

### Output / Renderer Tests (`tests/output/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `manage/item_delete_confirm_test.php` | 9 | Item-deletion confirmation context: single vs bulk action and id payload, singular/plural/simple confirm labels, surviving-only and orphaned+surviving sections; XP-impact warning shown for a single deletion with a disable-instead link, never shown for a bulk deletion even with a toggle URL supplied, and omitted entirely when there is no XP impact |
| `manage/quest_delete_confirm_test.php` | 3 | Quest-deletion confirmation context: single deletion produces the `delete_quest_force` action with the XP-impact warning and disable-instead link; bulk deletion produces `bulk_delete_quests_force` with the id list and never shows the disable-instead link even with a toggle URL supplied; no XP impact omits both the warning and the disable link |
| `manage/tab_chapters_test.php` | 4 | Chapter-card visibility warnings: missing start-scene flag, required-level-above-maximum warning text and bounds |
| **Subtotal** | **16** | |

| **Grand Total** | **527** | |

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
| `controller\drops` | 20% |
| `controller\export` | 90% |
| `controller\items` | 99% |
| `controller\quests` | 76% |
| `controller\scenes` | 13% |
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
| `external\generate_ai_content` | 77% |
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
| `external\wizard_generate` | 85% |
| `external\wizard_list_runs` | 100% |
| `external\wizard_run_step` | 86% |
| `external\wizard_start` | 99% |
| `game` | 84% |
| `instance_cleanup` | 100% |
| `local\analytics` | 90% |
| `local\audit_log` | 78% |
| `local\drop_distribution` | 97% |
| `local\external_items` | 97% |
| `local\wizard` | 76% |
| `local\xp_budget` | 98% |
| `output\manage\item_delete_confirm` | 100% |
| `output\manage\quest_delete_confirm` | 100% |
| `output\manage\tab_chapters` | 7% |
| `output\view\tab_collection` | 68% |
| `privacy\provider` | 96% |
| `quest` | 90% |
| `story_manager` | 37% |
| `trade_manager` | 90% |
| `utils` | 35% |
| **Overall** | **42%** |

49 of the plugin's 82 classes are listed above — the rest (mostly exception classes, event
subscribers and thin output wrappers never `require`'d during this suite's run) carry no
coverage data at all and are omitted rather than shown as a misleading 0%.

### Behat — Acceptance Tests

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
