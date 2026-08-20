# 🧪 Automated Tests

PlayerHUD ships with an extensive test suite covering both business logic (PHPUnit) and browser acceptance (Behat). Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai/generator_test.php` | 5 | `save_item()` (reached via reflection, no network): clamps an overlong AI-provided name; coerces non-string fields before persisting; sanitizes a malicious description; `is_safe_url()` rejects a host that does not resolve and a non-canonical IP literal (SSRF guard) |
| `ai/hub_usage_reporting_test.php` | 2 | Reports every failed AI attempt when a hub key is used, not just the last one; never reports usage for a plugin-owned (non-hub) key |
| `backup_restore_test.php` | 3 | Backup/restore step definitions cover all RPG tables; full course round-trip (incl. two real activities) preserves RPG class/chapter/story data, item powers (`action_type`/`action_value`), class emoji tiers, and a `TYPE_SPECIFIC_TRADE` quest's requirement remapped against the restored trade rather than the item mapping; a pinned `deadline_extension` cmid and a `TYPE_ACTIVITY` quest's requirement are both remapped to the restored course's own activity; a second `deadline_extension` item with its own cmid confirms `after_restore()`'s batch lookup never mixes up one item's cmid with another's |
| `collection_tab_test.php` | 15 | Collection tab: `filter_type` mapping (avatar/deadline/none), `power_hint_avatar` shown for unowned non-secret item and hidden for secret item, `is_equipped` flag; origin classification for an inventory row's source (map is recognised as PlayerHUD's own; anything outside the 4 known sources falls back to a generic "game" origin); `get_lp_activities()` is memoised across multiple items in the same render (N+1 fix); `has_power()` is false for an unrecognized `action_type` and for `deadline_extension` without the companion plugin installed; a large origin count renders compact; an item held only in the new quantity engine (no legacy inventory rows) still counts and carries the "new" badge; a balance split across both storage generations sums correctly into one card; a high quantity renders through `utils::format_compact_number()` |
| `content_crud_test.php` | 13 | Item, chapter and trade CRUD: create persists all fields, update changes fields, delete removes record, listing scoped to instance |
| `cross_instance_security_test.php` | 12 | Cross-instance isolation: item, quest, chapter and trade guards accept own-instance IDs and reject foreign ones without modifying the target record |
| `db_access_test.php` | 1 | `block/playerhud:manage`'s declared `riskbitmask` includes `RISK_PERSONAL`, since the management panel surfaces other users' XP, inventory and class assignments |
| `db_upgrade_test.php` | 8 | Upgrade steps that carry real data-migration logic (as opposed to plain schema DDL): tags a pre-existing item literally named 'PlayerCoin' with `action_type=playercoin`, leaves unrelated/already-tagged items untouched; backfills `xpawarded` on a pre-existing inventory row from a paid source (map/teacher/revoked) using the item's current XP, but not for a `drop`-sourced row nor a row collected from an infinite drop (`maxusage=0`); backfills a pre-existing quest-log claim's `xpawarded` from the quest's current `reward_xp`; backfills a missing drop code with a unique code that does not collide with one already present in the same instance; the upgrade step adds the `block_playerhud_stack`/`block_playerhud_stack_log` tables and the drop/quest quantity columns without touching any existing row |
| `drop_guard_test.php` | 11 | Collection limits, trade-consumed items, cooldown enforcement; a pickup limit is enforced from a balance held only in the new quantity-engine log; a limit combining legacy inventory rows and new-engine log rows blocks correctly; a combined-source balance below the limit is still allowed; a cooldown is enforced from the most recent new-engine pickup, not just a legacy inventory row |
| `form/edit_item_form_test.php` | 4 | Server-side validation of the item image field: an emoji value passes; a valid HTTPS URL passes; an attribute-breakout payload (`http` followed by quotes/tags) is rejected; a malformed `http`-like value that is not really a URL is rejected |
| `game_test.php` | 47 | `get_game_stats()` totals XP/level plus quest XP inclusion (and exclusion when the quest is disabled), cross-checked against `analytics::economy_health()`'s own total; collection anti-farm and cooldown; `get_avatar_item` (enabled, disabled, foreign instance, not found); XP award on finite drop; leaderboard manager exclusion; level-up, beat-the-game and first-PlayerCoin milestone flags on collection; `xp_to_level`; player auto-creation, gamification and ranking-visibility toggles, inventory (revoked/consumed excluded), `has_item`; `get_user_rank` XP order, tie-break by arrival, manager and enrolment exclusion; `get_full_trades` requirement/reward hydration, empty case, and availability gating when either side's item is disabled; trade-suggestion heuristics (discounted avatars, covered-avatar skip, prerequisites) and persistence, with the avatar emoji escaped (`strip_tags`) in `build_trade_suggestions`; `change_xp` emits the `xp_changed` event on award, on deduction (floored at zero) and stays silent on a true no-op; `get_leaderboard` correctly flags the current user's own group in the group ranking and restricts the teacher's group filter to actual members; `process_collection()` grants multiple units at once through a drop's `value` field; `maxusage` and `value` multiply independently (a 3-use, 2-per-use drop yields 6 total across 3 pickups, blocked on the 4th regardless of `value`); the collection response reports the granted quantity and progress text |
| `gamemaster_test.php` | 6 | Grant/revoke/delete item and quest while preserving leaderboard timestamps; XP floor at zero |
| `instance_delete_test.php` | 1 | Deleting a block instance cleans every one of this plugin's own tables (`instance_cleanup`), incl. the new `block_playerhud_stack`/`block_playerhud_stack_log` tables |
| `item_delete_cascade_test.php` | 19 | Trade orphan detection when item deleted (sole req, one-of-two, sole reward, combined req+reward); bulk orphan checks; cross-instance isolation; delete removes item record and cascades orphaned trades without touching non-orphaned ones; deleting an item (single or bulk) reverts XP only for copies that actually earned it, leaving infinite-drop (zero-XP) copies untouched; single and bulk item deletion also remove the item's `block_playerhud_stack`/`block_playerhud_stack_log` rows and revert the XP they recorded |
| `karma_test.php` | 11 | Karma read/write, positive/negative deltas, clamping at ±999 boundaries, successive accumulation |
| `lib_test.php` | 27 | `block_playerhud_myprofile_navigation`: every no-op branch (no course, site course, no block instance, no player record, gamification disabled) and an active player with a collected item gets the profile section added, incl. a regular student viewer (not admin); the section only mounts when the viewer holds both `block/playerhud:view` and `moodle/block:view` in the block context (either capability denied is a no-op); respects the leaderboard's `ranking_visibility` opt-out — a fellow student cannot see another student's hidden profile, but the owner always sees their own and a teacher with `block/playerhud:manage` always sees anyone's; `block_playerhud_get_drop_details_by_code`: match, unknown code, foreign-instance rejection, disabled-item exclusion; `block_playerhud_is_visible_for_class`: public (empty/'0'), matching/non-matching class id, '0' inside a list; `block_playerhud_pluginfile`: non-block context, unknown file area, no stored file found, and the same `block/playerhud:view`/`moodle/block:view` capability gate as the profile section (either denied rejects the download, a regular student with both passes through) before item/class art is served |
| `privacy_provider_test.php` | 21 | GDPR full coverage: context/user discovery (`get_contexts_for_userid`, `get_users_in_context`); `export_user_data` across all six subtrees (profile, RPG, inventory, quests, trades, AI logs), incl. a quest's `xp_gained` and RPG progress's `created`/`modified`, which were previously left out; per-user, multi-user and whole-context deletion with isolation guarantees, incl. cleaning up `wizard_objects`/`wizard_shortcodes` before `wizard_runs` (no rows left dangling with a nonexistent `runid`); export/delete of every API-key and avatar preference; metadata declaration checked against every real column of `playerhud_user`, `inventory`, `ai_logs`, `wizard_runs`, `rpg_progress`, `quest_log` and `trade_log` (not just a handful of hand-picked keys); non-block context guards are no-ops; metadata declaration is checked against every real column of the new `block_playerhud_stack` and `block_playerhud_stack_log` tables too |
| `quest_test.php` | 45 | Completion checks (level, XP, items, trades, activity completion); claim rewards; disabled quest; idempotency; level-up and beat-the-game celebration flags on reward claim; `has_claimable_quests` across every requirement type incl. activity completion, with claimed/unclaimed short-circuit; `preload_totals()` matches `check_status()`'s own unbatched per-quest results across every query-backed type, incl. two `TYPE_SPECIFIC_ITEM`/`TYPE_SPECIFIC_TRADE` quests targeting different ids, and its read count stays flat as the quest list grows instead of scaling; `has_claimable_quests()` keeps its own per-type counts isolated between items/trades and never pays for an aggregate a quest type earlier in the list already short-circuited past; `build_record_from_suggestion` mapping, item-id carrying and XP override floor; `get_heuristic_suggestions` level/collection/economy/activity milestones with duplicate skipping; a completion-tracked activity offered as a heuristic quest is detected as fulfilled once the activity is actually completed; `TYPE_SPECIFIC_ITEM`/`TYPE_UNIQUE_ITEMS`/`TYPE_TOTAL_ITEMS` are satisfied by a balance held only in the new quantity engine, not just legacy inventory rows; claiming a reward grants the quest's configured `reward_itemqty`, not always exactly one unit; the reward message keeps its connector spacing intact |
| `rpg_classes_test.php` | 8 | Class assignment, duplicate guard, karma initialisation, portrait tier boundaries |
| `story_manager_test.php` | 29 | Scene loading, progress persistence, choice navigation, karma delta, chapter completion, error cases; `make_choice()` acquires and releases its per-user lock without hanging on a normal call; `consumed`/`revoked` inventory is rejected as payment for a cost-gated choice, a genuinely held copy is accepted and consumed (and soft-consumed — flipped to `source=consumed`, not deleted, so its row survives); `load_scene()`, `make_choice()` and `load_recap()` re-validate a chapter's `unlock_date`/`required_level` server-side (previously only the UI blocked the click), with `load_recap()` exempt from that check for an already-completed chapter — re-reading something already finished never re-locks, even if a teacher later tightens the chapter's gates; spending an item in a story choice never refunds a pickup of the finite/cooldown-bearing map drop that item came from, since `drop_guard` still counts the (now-consumed, not deleted) row against the drop's `maxusage`; a choice cost is accepted when payment comes only from the new quantity engine's balance; a choice cost is accepted when payment is split across both storage generations |
| `suggest_trades_state_test.php` | 4 | Suggest Trades button: disabled without prereqs, disabled with coin only, disabled when all avatars covered, enabled on partial coverage |
| `trade_test.php` | 11 | Trade assembly, insufficient funds, atomic success, one-time limit, group restriction; a trade referencing a disabled reward item is rejected outright even with sufficient funds; a high-quantity trade grants/consumes through the quantity engine without inserting one inventory row per unit; a trade's cost is paid correctly when split across both storage generations |
| `utils_test.php` | 17 | `get_avatar_html`: emoji produces `ph-avatar-emoji` div with aria-hidden span; HTTP URL produces `ph-avatar-img` img tag; a null image does not throw for `get_avatar_html` nor `get_items_display_data`; `generate_drop_code()` returns a 6-character uppercase alphanumeric code, and generating/persisting several codes in a row for the same instance never repeats one; `format_compact_number()` leaves a value under 1000 unchanged, uses a `k` suffix for thousands, an `M` suffix for millions, and preserves a negative sign; `format_drop_progress()` renders a finite and an unlimited collection count; drop-quantity-per-collection formatting |
| **Subtotal** | **320** | |

### Local Business-Logic Tests (`tests/local/`)

Shared logic reused by more than one entry point (the wizard's own web services, the manual "Distribute Drops" screen, the Economy Health panel), tested directly rather than only indirectly through whichever controller happens to call it.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `analytics_test.php` | 12 | Economy Health: total earnable XP vs ceiling ratio (empty/hard/perfect/easy), quest rewards and infinite/dropless items in the breakdown, zero-ceiling guard; level-distribution histogram bucketing, cap overflow (`N+`) ordering, percent of tallest bar, zero-XP-per-level guard, empty player set produces no rows; `balance_context()`'s current XP always matches `economy_health()`'s own total; the achievable-XP ceiling accounts for a drop's `value`, not just its item count |
| `audit_log_test.php` | 15 | Shared audit-log query (`get_logs()`) behind the teacher Reports tab and the student History tab: an item's `xp_gained` reflects the recorded `xpawarded` value at grant time, not the item's current XP (and matches when never edited); a quest-granted item reports zero `xp_gained` since its own XP is never paid through that path; a revoked row reports the negative of its originally recorded value, not the item's current XP; a quest claim's `xp_gained` reflects the recorded value, not the quest's current `reward_xp`; a new-engine grant/consume/revoke each surface as their own event type, with a revoke reporting a negative value; a legacy grant reports a quantity of one and a legacy consume reports a quantity of negative one; a new-engine grant/consume/revoke report their real quantity (positive/negative) instead of always one; trade and quest events always report zero quantity; `format_qty_badge()` renders the expected HTML |
| `drop_distribution_test.php` | 12 | Eligible-modules discovery: includes forums, excludes modules pending deletion and the course's own news forum (reserved for PlayerCoin/Secret Item), empty for an activity-less course; best-name-match suggestion incl. no-match case; inserted-shortcode cmid lookup incl. not-found and empty-input cases; activity-quota splitting always sums to target, caps at activity count, edge cases |
| `external_items_test.php` | 24 | Cross-plugin item API used by other Player-family plugins (e.g. PlayerWords): `belongs_to_instance()` accepts an item's own instance (enabled or disabled) and rejects a foreign instance, a nonexistent id, or zero/negative ids without querying the database; `grant()` updates the new quantity-engine stack and awards XP for the caller's own enabled item, and records the triggering `dropid` on the log row; `consume()` spends from the new stack first, falls back to legacy inventory rows when the stack is empty, spends correctly when the balance is split across both storage generations, and returns false when insufficient across both; `get_available_quantity()` sums both storage generations; `get_available_quantities_bulk()` matches what looking each item up individually would return, and its read count stays flat as the item list grows instead of scaling; `get_name()`/`get_xp()` resolve for the item's own instance and return empty/zero for a foreign one |
| `wizard_test.php` | 20 | Run manifest: start/finish status; rollback deletes recorded objects across tables, strips the recorded shortcode, reverts XP and clears play history, rejects a mismatched instance; rollback deletes recorded trades and chapters (with their `trade_reqs` and `story_nodes`) through the same bulk paths `bulk_delete_trades()`/`bulk_delete_chapters()` give the trades/chapters controllers, exercised end to end via `wizard::rollback()` rather than the controller methods directly; active-runs listing with counts and a limit; per-module "already generated" detection incl. stale runs without content, manifest-only items, AI-logged-only items and Ranking's config-only check; `ensure_config_flag` turns a flag on without touching sibling config and is a no-op when already on; `require_course_matches_instance()` accepts the block instance's real course and rejects a courseid belonging to any other course |
| `xp_budget_test.php` | 15 | Item/mission/chapter counts per journey size incl. fallback to short; `distribute_share` divides a gap evenly, spreads the remainder on the first elements, caps at the gap when elements outnumber it, edge cases; suggested max-levels mapping; balanced-mission round-robin across types, order preservation within a type, all-selected when the limit covers them, edge cases |
| **Subtotal** | **98** | |

### Web Services Tests (`tests/external/`)

One test class per web service function, each validating the external API contract, parameter/return structure conformance (`external_api::clean_returnvalue`), and capability gates. AI functions are tested without network — with no API key configured, the `try/catch` path returns `success=false`, which is asserted directly.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `chat_message_test.php` | 4 | No API key → `moodle_exception`, asserting the specific `accessdenied` errorcode rather than any `moodle_exception` where relevant (an unconfigured API key would otherwise also throw one and mask a missing binding check); an unclean-HTML `message` field is rejected; capability guard (`manage`); a mismatched `courseid` is rejected before any AI call is attempted |
| `collect_item_test.php` | 4 | Item collected + inventory record created; invalid drop → `success=false`; limit reached → `success=false`; capability guard (`view`) |
| `create_avatar_pack_test.php` | 6 | 17 items created; ids and names returned in lockstep; all have `action_type=avatar`; emoji deduplication; second call creates 0 (idempotency); capability guard |
| `create_class_pack_test.php` | 7 | Creates 3 classes; base-HP tiers match expectations; skips an already-existing name; second call creates 0 (idempotency); different tones produce different names; unknown tone falls back to fantasy; capability guard |
| `create_playercoin_test.php` | 3 | New item created; second call returns existing item (idempotency); capability guard |
| `execute_chat_action_test.php` | 4 | `action_open_tab` returns redirect URL (deterministic, no AI); unknown action type → `success=false`; invalid params → `success=false`; capability guard |
| `generate_ai_content_test.php` | 3 | No API key → `success=false`; an unclean-HTML `message` field is rejected; capability guard (`manage`) |
| `generate_class_oracle_test.php` | 3 | No API key → `success=false`; an unclean-HTML `message` field is rejected; capability guard (`manage`) |
| `generate_story_test.php` | 3 | No API key → `success=false`; an unclean-HTML `message` field is rejected; capability guard (`manage`) |
| `insert_drop_shortcode_test.php` | 11 | Shortcode prepended to module content field; duplicate insert rejected; drop from another instance rejected; drop renamed to the activity it lands in; `mode=text` with a custom label; unknown mode falls back to card; capability guard; `execute_batch()` inserts shortcodes for several drops in one call (used by the wizard's distribution step), and one drop's failure never blocks processing of the rest; two drops landing on the same activity and field in one batch both survive (the preloaded field-value cache is updated in place after each write, not read once for the whole batch); the batch's read count stays flat as it grows instead of scaling with the number of items |
| `load_recap_test.php` | 3 | Recap HTML returned after scene visit; no history → exception; capability guard (`view`) |
| `load_scene_test.php` | 3 | Start node and choices returned; invalid chapter → exception; capability guard (`view`) |
| `make_choice_test.php` | 3 | Advances story to destination node; invalid choice → exception; capability guard (`view`) |
| `remove_drop_shortcode_test.php` | 5 | Existing shortcode stripped; `<br>`-separated shortcode stripped; shortcode carrying `mode=`/`text=` attributes stripped; absent shortcode is a no-op success; capability guard |
| `setup_playercoin_drop_test.php` | 6 | Success path; no forum → `success=false`; item from another instance rejected; course not owning the instance rejected; shortcode prepended to existing intro; capability guard |
| `use_item_test.php` | 12 | Capability guard (`view`, asserted via a dedicated `interact` capability requirement); not-owned item → exception; deadline power: no activity selected, no rule found, creates override and consumes item, updates existing override, rejects a `targetcmid` from another course even when an override already exists for that cmid+userid pair, and rejects a foreign cmid before ever probing the companion plugin's own rule table; two legitimate sequential grants (two items held) each consume their own unit and extend by exactly one day-block per call, without either one doubling the extension; avatar power: equip and unequip both succeed with `$OUTPUT` reset to the still-unresolved `bootstrap_renderer` placeholder, reproducing the exact precondition of a real AJAX dispatch, and equipping still works for an avatar granted only through the new quantity engine |
| `wizard_apply_suggested_levels_test.php` | 3 | Applies the suggestion when config is at defaults; still applies when config was already customised; preserves every other config field untouched |
| `wizard_generate_helpers_test.php` | 12 | `build_step_types()` matches selected modules in order, skips `auto_distribute` when Items' own distribute flag is off, empty when nothing selected; `compute_shared_xp_shares()` empty without Items/Missions, Pill/Latepenalty use their own defaults alone, share the budget with Items when combined; `resolve_or_create_progress_item()` idempotent and creates a complete item when missing; `resolve_previous_chapter_context()` reads the latest chapter; `distribute_drops()` caps each activity to its computed quota instead of letting name-matching alone stack every drop onto one activity; a drop id from another block instance is silently excluded rather than distributed, leaking its name or inflating the quota count; an empty drop-id list returns early instead of reaching `get_in_or_equal()` with nothing to match |
| `wizard_list_runs_test.php` | 4 | Summary for an active run; RPG run summarised; rolled-back runs excluded; capability guard |
| `wizard_rollback_test.php` | 3 | Deletes the run's generated objects, reported count matches what was recorded; rejects a mismatched instance; capability guard |
| `wizard_run_step_test.php` | 57 | One live-progress step at a time, per mechanic (PlayerCoin, Avatars, Missions, Trade, Knowledge Pill, Secret Item, Ranking, Deadline Extension, RPG, Item RPG, auto-distribute): item/quest/trade creation with manifest recording, idempotent retries, rollback per mechanic, distribute-flag gating, tone/journey-size flavouring, and the news-forum-only placement for PlayerCoin and Secret Item (incl. no-op without a news forum); unknown step type, capability guard, cross-instance `runid` rejection, failed step does not finish the run, final step reports the economy only when requested; a `courseid` that does not actually own the block instance is rejected |
| `wizard_start_test.php` | 9 | One plan step per selected module; the "slow step" flag reflects whether Next Chapter was selected; XP shares split matches selected modules; Pill's bonus XP present when selected alone; the story-arc module expands into an outline + one step per chapter, step count grows with journey size, manifest keeps the logical module name; capability guard; a `courseid` that does not actually own the block instance is rejected |
| **Subtotal** | **168** | |

### Controller Tests (`tests/controller/`)

These cover the business logic extracted from `manage.php` into the controllers (MVC refactor), each exercised with explicit inputs and instance isolation.

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `aikeys_test.php` | 4 | AI key storage: keys trimmed and saved as user preferences, empty default for a missing field, legacy keys stripped from block config, clean config left untouched |
| `chapters_test.php` | 17 | Chapter persistence and ordering: save (insert, update, defaults, isolation), delete cascading scenes/choices, reorder/move with full-list renumbering, edge no-op; `bulk_delete_chapters()` removes several chapters together with their scenes and choices in one pass, filters out a chapter from another instance rather than deleting it or throwing, and is a no-op for an empty id list; moving a chapter does not scale its write count with the total number of chapters |
| `classes_test.php` | 7 | RPG class persistence: insert (base HP, instance binding, emoji tiers), update preserves base HP, emoji trimming, isolation; delete removes record and tier portraits, isolation, siblings kept |
| `drops_test.php` | 17 | Drop persistence: save (insert + code, unlimited, update preserves ownership, isolation, foreign item); delete single and foreign no-op; bulk deletes only owned with count, empty input; `get_owned_item` returns for the owning instance and rejects a foreign one; `get_sort_data` toggles the icon/direction for the active sort column; `view_manage_page`/`handle_edit_form` render end to end through the real global `$OUTPUT`/`$PAGE` for a teacher with the manage capability; the management table template escapes drop media content; `view_manage_page` sorts correctly by name and falls back to a safe column for an unknown sort parameter instead of interpolating it into SQL |
| `export_test.php` | 8 | Grade export builder: row fields and derived level, XP ordering, level cap, teacher/manager exclusion, localized columns with no players, unenrolled exclusion, XP tie-break by last action; a student's email is omitted from the export when hidden by the site's identity policy |
| `items_test.php` | 22 | Item lifecycle: enable toggle and foreign no-op; grant adds inventory + XP, zero-XP, foreign rejection; revoke deducts XP, infinite-drop preservation, foreign no-op; revoke deducts the XP actually recorded at grant time, not the item's current XP; surviving-trade detection (trimmed trade, orphaned excluded, unrelated ignored); `find_xp_impact` aggregates only copies that actually earned XP across all holders, empty for an unheld item, and a no-op for an empty id list; granting with an explicit `$qty` grants multiple units through the quantity engine in one call; revoking a single `block_playerhud_stack_log` entry decrements the balance and deducts its recorded XP, caps the removal at the current balance, is a no-op for a `consume` row, is idempotent on a second revoke of the same entry, and is a no-op for a foreign-instance entry |
| `manage_entry_points_test.php` | 34 | The controllers' HTTP-facing halves, driven through the real request lifecycle (superglobals populated as a browser would, `redirect()` caught as `redirecterrordetected` under CLI): drops delete and bulk-delete actually remove the rows, a foreign-instance drop id is never deleted, a wrong sesskey deletes nothing, the listing renders the instance's own drops only and falls back to a safe sort column for a crafted `sort` parameter; scene deletion cascades to its choices, a node from another chapter is left alone, a chapter from another instance is rejected; collect awards the item and its XP, pays no XP on an infinite drop, rejects a foreign drop, a disabled item and a bad sesskey without writing anything; the class and trade editors reject a record from another instance, and every one of these screens is closed to a user without `block/playerhud:manage` (or `:view` for collect); a genuinely submitted (moodleform `_qf__` marker present) request for a trade from another instance is still rejected before its requirements/rewards are ever read, closing an oracle that would otherwise leak a foreign trade's row counts into the re-rendered form's sizing; a plain GET with an oversized `repeats_req`/`repeats_give` is clamped instead of allocating an unbounded number of form elements, and `scenes::clamp_repeats()` caps a client-supplied `repeats` value at the same fixed ceiling; the drops page, the collect entry point, and the chapters/class/trade/scenes editors each reject a `courseid` that does not actually own the block instance; the trade and drop editors escape a malicious item name embedded in the item select and the page header |
| `quests_test.php` | 13 | Quest lifecycle: toggle and foreign no-op; delete reverts XP per completion, zero-reward, foreign no-op; delete and bulk-delete revert the XP actually recorded per completion, not the quest's current reward; bulk deletes only owned with aggregated XP revert and count, empty input; `find_xp_impact` aggregates only completions that actually earned XP across all claimants, empty for an unclaimed quest, and a no-op for an empty id list; a bulk-delete's XP-impact preview excludes a quest belonging to another instance once the id list is scoped to the instance |
| `scenes_test.php` | 6 | Story scene/choice persistence: save choices, class assignment with string/int ID normalisation (`set_class_id` regression), required class, next node, item cost, follow-up node creation |
| `suggestions_test.php` | 4 | Suggestion persistence: only ticked quest suggestions inserted (and none selected), only ticked trade suggestions created with reqs/rewards (and none selected) |
| `trades_test.php` | 10 | Trade persistence: save (insert with reqs + rewards, update replaces, isolation, foreign item filtered); delete cascading reqs/rewards/log, isolation, siblings kept; `bulk_delete_trades()` removes several trades together with their requirements, rewards and log in one pass, filters out a trade from another instance rather than deleting it or throwing, and is a no-op for an empty id list |
| **Subtotal** | **142** | |

### Output / Renderer Tests (`tests/output/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `manage/item_delete_confirm_test.php` | 9 | Item-deletion confirmation context: single vs bulk action and id payload, singular/plural/simple confirm labels, surviving-only and orphaned+surviving sections; XP-impact warning shown for a single deletion with a disable-instead link, never shown for a bulk deletion even with a toggle URL supplied, and omitted entirely when there is no XP impact |
| `manage/quest_delete_confirm_test.php` | 3 | Quest-deletion confirmation context: single deletion produces the `delete_quest_force` action with the XP-impact warning and disable-instead link; bulk deletion produces `bulk_delete_quests_force` with the id list and never shows the disable-instead link even with a toggle URL supplied; no XP impact omits both the warning and the disable link |
| `manage/tab_chapters_test.php` | 4 | Chapter-card visibility warnings: missing start-scene flag, required-level-above-maximum warning text and bounds |
| `manage/tab_config_test.php` | 2 | Config tab (Economy Health summary): an instance with no items or quests still exports a well-formed empty summary instead of crashing on `economy_health()`; an item with XP contributes to the breakdown and the achievable total |
| `manage/tab_quests_test.php` | 3 | Quest form processing: a pasted modal skeleton in the description is sanitized before saving; basic formatting in the description is preserved; the reward-item select escapes a malicious item name |
| `manage/tab_reports_test.php` | 8 | Reports tab: an instance with no players/items/quests still exports a well-formed summary with the audit drill-down inactive; `display()` renders real HTML end to end through the global `$OUTPUT`; more than 30 AI log rows only export the first page (30) by default, newest first, and `ai_showall=1` returns every row; the audit drill-down's `inventory.source` fallback is escaped (`s()`) when no matching `report_src_*` lang string exists, instead of reaching the `details_html` triple-mustache sink raw; the student-selector export omits a student's email when hidden by the site's identity policy; the "most collected item" KPI and each student's total-items figure both sum across both storage generations |
| `profile_content_test.php` | 1 | `export_for_template()` strips tags from the item image content field |
| `view/header_test.php` | 2 | HUD header: a player with no equipped avatar falls back to the standard user picture, carrying name/XP/level without a group badge (no mod_playergroup); an equipped avatar item replaces the standard user picture |
| `view/tab_chapters_test.php` | 5 | Chapters tab: no chapters renders the empty state instead of crashing; an unlocked, uncompleted chapter is listed as available; a chapter recorded in the player's `completed_chapters` list renders as completed; a chapter with a future unlock date renders as locked; a chapter above the player's `required_level` also renders as locked, matching what the server now enforces |
| `view/tab_class_select_test.php` | 3 | Class selection tab: no classes configured renders the empty state instead of crashing; a class the player has not picked is listed unselected; a class the player already picked is marked as selected |
| `view/tab_history_test.php` | 2 | Log tab: a player with no logged events still exports a well-formed empty state, with all 5 sortable column headers present; the `inventory.source` fallback is escaped (`s()`) when no matching `report_src_*` lang string exists, instead of reaching the `details_html` triple-mustache sink raw |
| `view/tab_quests_test.php` | 4 | Quests tab: no quests renders the empty notification instead of crashing; a completed, unclaimed quest is listed with a claim action; a quest already claimed shows its claimed date instead of a claim action; `get_type_label()` maps every quest type constant to a label and falls back for an unrecognised type |
| `view/tab_ranking_test.php` | 4 | Ranking tab: disabled in block config short-circuits before touching any player data; a visible student sees the leaderboard content; a hidden student sees their own privacy toggle but not the leaderboard; a teacher always sees content with teacher-only filter controls active |
| `view/tab_rules_test.php` | 2 | Rules/help tab: a config with no `help_content` falls back to the system default template, carrying the enabled-feature flags for the default help cards; custom `help_content` with `use_default_help` disabled renders the teacher's own content instead |
| `view/tab_shop_test.php` | 4 | Shop tab: no trades renders the empty state instead of crashing; a student holding enough of the required item can afford the trade; a student with none of the required item cannot afford it; a one-time trade already completed is marked as such |
| **Subtotal** | **56** | |

| **Grand Total** | **784** | |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `ai\generator` | 15% |
| `controller\aikeys` | 100% |
| `controller\chapters` | 63% |
| `controller\classes` | 67% |
| `controller\collect` | 100% |
| `controller\drops` | 91% |
| `controller\export` | 91% |
| `controller\items` | 97% |
| `controller\quests` | 97% |
| `controller\scenes` | 45% |
| `controller\suggestions` | 100% |
| `controller\trades` | 79% |
| `drop_guard` | 100% |
| `event\character_selected` | 43% |
| `event\item_collected` | 43% |
| `event\quest_collected` | 43% |
| `event\trade_completed` | 43% |
| `event\xp_changed` | 43% |
| `external\chat_message` | 79% |
| `external\collect_item` | 100% |
| `external\create_avatar_pack` | 84% |
| `external\create_class_pack` | 79% |
| `external\create_playercoin` | 91% |
| `external\execute_chat_action` | 27% |
| `external\generate_ai_content` | 78% |
| `external\generate_class_oracle` | 67% |
| `external\generate_story` | 71% |
| `external\insert_drop_shortcode` | 93% |
| `external\load_recap` | 100% |
| `external\load_scene` | 79% |
| `external\make_choice` | 79% |
| `external\remove_drop_shortcode` | 84% |
| `external\setup_playercoin_drop` | 90% |
| `external\use_item` | 81% |
| `external\wizard_apply_suggested_levels` | 83% |
| `external\wizard_generate` | 85% |
| `external\wizard_list_runs` | 100% |
| `external\wizard_rollback` | 100% |
| `external\wizard_run_step` | 86% |
| `external\wizard_start` | 99% |
| `form\edit_item_form` | 74% |
| `game` | 94% |
| `instance_cleanup` | 100% |
| `local\analytics` | 92% |
| `local\audit_log` | 81% |
| `local\drop_distribution` | 97% |
| `local\external_items` | 94% |
| `local\rpg_archetypes` | 92% |
| `local\wizard` | 97% |
| `local\xp_budget` | 98% |
| `output\manage\item_delete_confirm` | 100% |
| `output\manage\quest_delete_confirm` | 100% |
| `output\manage\tab_chapters` | 7% |
| `output\manage\tab_config` | 81% |
| `output\manage\tab_quests` | 24% |
| `output\manage\tab_reports` | 83% |
| `output\profile_content` | 86% |
| `output\view\header` | 95% |
| `output\view\tab_chapters` | 100% |
| `output\view\tab_class_select` | 79% |
| `output\view\tab_collection` | 84% |
| `output\view\tab_history` | 79% |
| `output\view\tab_quests` | 79% |
| `output\view\tab_ranking` | 64% |
| `output\view\tab_rules` | 78% |
| `output\view\tab_shop` | 92% |
| `privacy\provider` | 97% |
| `quest` | 95% |
| `story_manager` | 74% |
| `trade_manager` | 90% |
| `utils` | 59% |
| **Overall** | **66%** |

71 of the plugin's 86 classes are listed above — the rest (mostly exception classes, event
subscribers and thin output wrappers never `require`'d during this suite's run) carry no
coverage data at all and are omitted rather than shown as a misleading 0%.

The lowest figures in the table reflect structural limits rather than untested logic:

- `ai\generator` (15%) and the AI branches of `chat_message`/`execute_chat_action` call real
  external providers over curl, with no HTTP mock layer.
- The AJAX half of `collect::execute()` ends in `die()`, real `moodleform` submissions depend
  on a browser, and JavaScript-driven behaviour has no server-side existence — all of which the
  Behat suite below covers instead.
- `external\use_item` (81%) has an atomic race-guard branch (consuming the item before writing
  the deadline extension) that only fails under genuine two-request concurrency — a documented
  structural limit of single-process PHPUnit, verified live instead via genuinely concurrent
  HTTP requests rather than covered here.

### Global-Function Files

`db/upgrade.php` and `lib.php` define only global functions, not classes, so
`moodle-coverage`'s per-class breakdown above has nothing to attribute them to. Both are still
instrumented and folded into the **Overall** figure; measured on their own instead:

| File | Cases | Lines coverage |
|------|------:|:--------------:|
| `lib.php` | 27 | 95% |
| `db/upgrade.php` | 8 | 71% |

- `lib.php` (`tests/lib_test.php`): all four functions are tested directly —
  `block_playerhud_myprofile_navigation()`'s every branch (no course, site course, no block
  instance, no player record, gamification disabled, active player with items, denied
  `block/playerhud:view`/`moodle/block:view`, `ranking_visibility` opt-out),
  `block_playerhud_get_drop_details_by_code()` (match, unknown code, foreign instance, disabled
  item) and `block_playerhud_is_visible_for_class()` (public, matching/non-matching class,
  `'0'` inside a list). `block_playerhud_pluginfile()` is covered up to the point where a
  matching file is actually found — the one branch left out calls `send_stored_file()`, which
  ends the script, the same structural limit already noted for `collect::execute()`'s AJAX half
  below.
- `db/upgrade.php` (`db_upgrade_test.php`): the 8 tests carrying real data-migration logic call
  `xmldb_block_playerhud_upgrade()` directly, which is enough for Xdebug to measure it like any
  other function call, even though PHPUnit's test environment always installs a fresh schema
  from `install.xml` and never runs the automatic upgrade path itself.

### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `block_playerhud_access.feature` | 3 | Role-based block visibility (teacher adds block, student sees HUD, non-enrolled user cannot) |
| `block_playerhud_student.feature` | 6 | HUD active on first visit, disable/re-enable gamification, dismiss confirmation; opening the Log tab does not error; equipping an avatar item over the real AJAX round-trip does not error |
| `block_playerhud_teacher.feature` | 7 | Game Master Panel button, management panel access, tab navigation, return to course; opening a student's audit log in Reports does not error |
| `block_playerhud_modals.feature` | 5 | Item detail modal open/close, duplicate-open guard, AJAX collect without redirect, no raw placeholders |
| `block_playerhud_celebrations.feature` | 2 | Huddy introduction shown once on the dashboard; first-quest nudge shown once when a reward is claimable |
| `block_playerhud_wizard.feature` | 6 | Wizard opens showing the generation form; Help and External recommendations side views; generating PlayerCoin end-to-end shows the success report; the PlayerCoin card locks after being generated; undoing a run from the History view unlocks it again |
| `block_playerhud_manage_crud.feature` | 7 | The management screens the PHP-level tests reach only in isolation: the Trades, Characters and Story tabs render on a real request; the item library links through to the drops screen; a drop is created through the real `moodleform`, appears in the listing and shows a success notification (locking the `redirect()` notification-type regression); a character is created through the real form (file-manager fields included); the bulk-selection master checkbox checks and clears every row (JavaScript) |
| **Total** | **36** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@block_playerhud --profile=chrome
```
