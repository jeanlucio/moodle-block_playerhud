# Changelog — block_playerhud

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.5.0] — 2026-06-02

### Added
- **Item powers** (`action_type` / `action_value`): items now carry `avatar_profile` or
  `deadline_extension` powers. A student who uses an avatar item equips it as their
  profile picture; a deadline item extends the submission window for a specific activity.
- **PlayerCoin quick-create**: one-click button in the Items tab creates the PlayerCoin
  item. Shows a confirmation dialog if one already exists; reloads on successful creation.
- **PlayerCoin drop setup**: confirm modal lets teachers insert the PlayerCoin drop
  shortcode into the course News Forum intro, eliminating a manual navigation step.
- **Create Avatars button**: seeds 17 pre-defined avatar items in one click; disabled once
  all avatar slots are already covered or the PlayerCoin does not exist.
- **Suggest Trades button**: generates suggested trade offers for the active course.
- **Item type filter in collection tab**: students can filter their inventory by power
  type (avatar, deadline extension, or plain).
- **Compact icon grid in trades**: trades requiring more than 3 items show a compact icon
  grid in the teacher management view and in the student shop, with colored borders and
  on-hover popovers.
- **AI provider chain extended**: Moodle `core_ai` and the PlayerGames hub are now probed
  before Gemini, Groq, and OpenAI-compatible; no API key is needed in PlayerHUD when
  `core_ai` is configured at site level.
- **AI class oracle indicator**: an AI emoji badge appears on the oracle button; error
  messages shown when the call fails due to rate limit or quota exhaustion.
- **AI prompts as PHP constants**: system prompts and role strings moved to PHP constants,
  preventing `js_call_amd` argument-size overflow on Moodle 4.5.
- **Character tier portrait fallback**: if a class has no image URL, an emoji avatar is
  used before the generic placeholder.
- **Power hint badge in collection**: unowned, non-secret items with `avatar_profile`
  power show a hint badge in the student collection tab.

### Fixed
- **Quest form — required item not saved (TYPE_SPECIFIC_ITEM / TYPE_SPECIFIC_TRADE)**:
  `req_itemid` was always written as 0 on PostgreSQL because `get_fieldset_select()`
  returns integer columns as PHP strings; `in_array()` with strict comparison then
  silently rejected every valid ID. Fixed by wrapping the validation arrays with
  `array_map('intval', …)`. The same bug affected `reward_itemid` whenever a non-zero
  reward item was selected.
- **Quest cards cut off depending on installation**: fixed height (`260px`) and
  `overflow: hidden` silently clipped buttons and reward badges when font size,
  theme padding or title length caused content to exceed that value. Replaced with
  `min-height: 260px`; CSS Grid row alignment keeps cards uniform without clipping.
- **Quest description tooltip not working (all Moodle versions)**: the info button
  relied on `window.bootstrap` (not a global in Moodle) and `data-bs-title` /
  `data-bs-toggle` (Bootstrap 5 only), so the tooltip was never initialised.
  Replaced with `require(['theme_boost/bootstrap/tooltip'])` (same pattern as
  popovers) and a plain `title` attribute, which works on Bootstrap 4
  (Moodle 4.5) and Bootstrap 5 (Moodle 5.x).
- **Avatar rendering**: corrected oval distortion (`object-fit: cover`, `aspect-ratio`);
  fixed sizing in block sidebar and header; resolved a strict-comparison bug that
  prevented avatar display in the sidebar.
- **Trade edit losing items**: editing and saving an existing trade no longer discards all
  item rows.
- **Stash item modal on activity pages**: modal opens correctly when the block is
  displayed on an activity page without the standard sidebar.
- **AI provider key precedence**: personal API keys configured by a teacher now always
  override institution-level defaults regardless of provider order.
- **OpenAI-compatible endpoint normalization**: trailing slashes and path fragments are
  cleaned automatically before the request is sent.
- **PlayerCoin identified by `action_type`**: the PlayerCoin item is now located by the
  stable `action_type = 'playercoin'` flag instead of its mutable display name.
- **Badge contrast in economy breakdown**: item count badges use dark text; item type
  badges use white text on coloured backgrounds.

### Security
- `base64_decode` strict mode added before every `unserialize_object` call
  (`block_playerhud.php`, `manage.php`, `classes/game.php`) to prevent a crash on
  empty or corrupt `configdata`.
- One-time trade uniqueness check moved inside the advisory lock, eliminating a race
  condition where two simultaneous requests could both pass the check before either
  write landed.
- Chapter delete now validates that the chapter belongs to the current block instance
  before removing its scenes, closing a cross-instance scene deletion path.
- Chapter sort-order swaps wrapped in delegated transactions to prevent inconsistent
  state if one of the two UPDATE queries fails.
- Manager list pre-loaded with `get_users_by_capability()` before the leaderboard loop,
  replacing a per-user `has_capability()` call that caused N+1 DB queries.

### Tests
- **`use_item_test.php`** (5 new cases): item not owned throws; deadline item with no
  activity selected returns "pick activity" prompt; deadline item with no matching rule
  returns warning; deadline item creates a new calendar override and consumes the item;
  deadline item updates an existing override.
- **`game_test.php`** +2 cases: XP correctly awarded when collecting a finite-use drop;
  managers correctly excluded from the leaderboard.
- **`privacy_provider_test.php`** +2 cases: avatar preference included in user data
  export; metadata declaration covers all stored fields.
- **`quest_test.php`**: `TYPE_ACTIVITY` completion trigger replaced with a full
  integration test using real activity completion state.

---

## [v1.4.2] — 2026-05-27

### Fixed
- SCSS single-line blocks in `_assistant.scss` (`&:nth-child` selectors and
  `@keyframes ph-bounce`) expanded to multi-line to satisfy the Moodle Plugin
  Directory stylelint precheckers (`block-opening-brace-newline-after`,
  `block-closing-brace-newline-before`,
  `declaration-block-single-line-max-declarations`).

### CI
- Added `.stylelintrc.json` mirroring Moodle core stylelint rules
  (`postcss-scss` + `stylelint-csstree-validator@3`).
- Added dedicated Stylelint step to `ci.yml` so SCSS formatting errors are
  caught before reaching the Plugin Directory precheckers.

---

## [v1.4.1] — 2026-05-27

### Fixed
- Leader card showed an arbitrary student when multiple students shared the
  top XP score. The query was missing the tiebreaker (`timemodified ASC`,
  `lastname ASC`) used by the ranking table, so the database could return any
  tied student. Now the leader is always the same person shown as rank 1.
- Items consumed in a trade (`source = 'consumed'`) were still counted in the
  student collection, profile display, ranking item totals, and CSV/Excel
  exports. All six query sites now exclude `consumed` alongside `revoked`.
- `toggle_gamification` was updating `timemodified` on `block_playerhud_user`,
  corrupting the time-based ranking tiebreaker. Toggling visibility is not a
  scoring event; only XP gains (item collection, teacher grant, quest reward)
  should advance the tiebreaker timestamp.

---

## [v1.4.0] — 2026-05-15

### Added
- Game Master AI Assistant tab on the management page. Teachers can have
  a multi-turn conversation with Gemini, Groq, or OpenAI to get game design
  advice and trigger actions directly from the chat.
- AI actions with teacher confirmation: create item, create quest, and
  generate a full branching story chapter (using the existing chapter
  generator with nodes and choices).
- Action audit log entries now use the correct `action_type` ('item',
  'quest', 'chapter') and record the created object name instead of the
  raw chat message.
- Post-action link opens the relevant management tab in a new browser tab,
  preserving the chat history.

---

## [v1.3.19] — 2026-05-15

### Security
- Quest and trade forms now validate that submitted item and trade IDs belong
  to the current block instance before saving, preventing a teacher with
  manage rights from injecting cross-instance references via crafted POST data.

### Fixed
- `revoke_item` no longer deducts XP when the inventory entry originated from
  an infinite drop (maxusage = 0), which never granted XP at collection time.
- Chapter reorder queries no longer use a literal `LIMIT 1` clause; the
  redundant clause broke on MSSQL and Oracle installs.

### Added
- Automated release workflow: pushing a `v*` tag now publishes the version
  directly to the Moodle Plugin Directory.

---

## [v1.3.18] — 2026-05-14

### Security
- Cross-instance record tampering: item, quest, chapter and trade controllers
  now verify record ownership against the active block instance before every
  update, preventing a teacher with manage rights on instance A from
  overwriting content that belongs to instance B.
- Story choice references validated against the current chapter and instance:
  `next_nodeid`, `req_class_id`, `set_class_id` and `cost_itemid` are now
  filtered through allow-lists loaded from the database before being saved.
- `revoke_item` action now loads the inventory row via a JOIN on
  `block_playerhud_items`, ensuring it belongs to the managed instance.
- SSRF guard for the custom OpenAI endpoint now resolves A/AAAA DNS records
  and rejects any resolved IP in a private or reserved range, closing a
  DNS-rebinding gap.
- Gemini API key moved from the request URL to the `x-goog-api-key` header,
  reducing the risk of the key being captured in proxy or server logs.

### Fixed
- Sort direction parameter for the items management table now normalised to
  exactly `ASC` or `DESC`, preventing a potential SQL syntax error from a
  crafted `dir` value.
- `format_text()` calls in student-facing views now pass the block context,
  ensuring multilang filters and pluginfile URL rewriting work correctly.

### Tests
- 12 new PHPUnit tests covering cross-instance isolation for item, quest,
  chapter and trade update paths (Finding #1 from the MDL Shield review).
- 13 new PHPUnit tests covering create, update, delete and instance scoping
  for items, chapters and trades, which previously had no persistence tests.

---

## [v1.3.17] — 2026-05-13

### Added
- **Stash overflow badge:** the item stash in the block sidebar now shows at
  most 5 unique items; a +N badge with a dashed border appears when the
  inventory exceeds that limit, consistent with the existing profile-page style.
- **Behat acceptance test suite:** covers modal open/close, DOM duplication,
  page redirect and string placeholder regressions across Moodle 4.5 and 5.x.

### Fixed
- **Item order consistency:** items collected at the same Unix second now
  appear in the same order across the sidebar, profile page, and filter widget.
  A stable `inv.id DESC` tiebreaker was added to all inventory SQL queries.
- **Bootstrap 4/5 compatibility:** calendar icon changed to `fa-calendar` (FA6
  Free compatible); modal date container fix prevents DOM corruption; badge row
  uses `display:flex` instead of `.show()`; `ph-help-trigger` spacing applied
  via CSS instead of BS5-only utility classes.
- **Bootstrap 4 visually-hidden fallback:** `.visually-hidden` CSS rule added
  for Moodle 4.5, where Bootstrap 4 does not define this utility class.
- **Modal tripling:** `filter_collect.js` now uses namespaced events with
  `.off().on()` to prevent handler stacking on repeated `init()` calls.

---

## [v1.3.16] — 2026-05-11

### Fixed
- **AI story and class generation broken:** `build_prompt_story` and
  `build_prompt_class_oracle` were returning a plain `string` while all API
  call helpers (`call_gemini`, `call_groq`, `call_openai_compatible`) expected
  an `array ['system' => ..., 'user' => ...]` since v1.3.8. The mismatch
  caused a `TypeError` at runtime whenever story or class generation was
  triggered; item generation was unaffected because it called the API helpers
  directly with the correct array. Both builder functions now return the same
  structured array, and `call_with_fallback` signature updated accordingly.

### Changed
- Plugin icon updated.

---

## [v1.3.15] — 2026-05-07

### Fixed
- **Quest suggestions — max level deadlock:** the suggestion engine no longer proposes a
  "Reach level N" mission for the maximum configured level. The mission would create an
  unreachable state if its XP reward was the only remaining source to cross the threshold.
  Level milestones are now suggested at 25 %, 50 % and 75 % of `max_levels` only.
- **Quest suggestions — trade milestones capped to available trades:** trade milestone steps
  (1 / 5 / 10) are now filtered against the total number of trades configured. If all trades
  are single-use, steps exceeding that count are suppressed. If at least one trade is
  unlimited the full progression is kept, since the student can accumulate many executions.
- **Drop pickup limit reset after trade:** items spent in a trade were previously deleted
  from `block_playerhud_inventory`, which reset the per-drop pickup counter to zero and
  allowed students to re-collect items indefinitely after trading them away. Consumed items
  are now retained with `source = 'consumed'`; the pickup guard counts all records
  (including consumed) so the limit is preserved. The history views show consumed items with
  a distinct yellow badge ("Consumido / Consumed") and no delete button.

### Refactored
- Drop pickup rules (limit and cooldown) extracted from the collect controller into the new
  `\block_playerhud\drop_guard` class, making the logic unit-testable independently of the
  HTTP request lifecycle.

### Tests
- New `drop_guard_test` covers seven scenarios: below-limit allowed, blocked at limit,
  blocked when limit is reached via consumed records (trade regression), mixed
  active + consumed, unlimited drops, cooldown blocking, and cooldown elapsed.
- `trade_test` updated: `test_trade_success_atomic` now asserts `source = 'consumed'`
  retention; two new tests cover consumed-item reuse prevention and drop-limit preservation.

---

## [v1.3.14] — 2026-05-07

### Added
- **All-drops overview:** new `action=alldrops` view in the Items management tab shows a
  flat paginated table of every drop across all items (item number, item name, drop name,
  code, collection limit, cooldown).
- **Distribute split button:** the Distribute Drops button is converted to a split-button
  dropdown so teachers can reach both Distribute and All Drops from the same control.
- **Group filter in ranking (teacher):** a group selector dropdown appears above the
  individual leaderboard when the course has groups; selecting a group filters the
  individual tab to show only members of that group (group ranking tab is unaffected).
- **Per-user visibility toggle (teacher):** teachers can toggle any enrolled student's
  ranking visibility directly from the leaderboard table without impersonating the student;
  the action validates capability, sesskey, and course enrolment.

---

## [v1.3.13] — 2026-05-06

### Added
- **Group ranking toggle:** new `config_enable_group_ranking` option in block settings
  (visible only when ranking is enabled); when disabled, the Groups tab is hidden from
  the leaderboard.
- **Conditional help cards:** help page cards for Items, Shop/Timers, Quests, RPG, and
  Ranking are now shown only when the corresponding feature is enabled in block settings.
- **`use_default_help` setting:** new block config option replacing the previous reset
  checkbox; when enabled (default), the help content editor is pre-populated with the
  current default HTML when the field is empty.

### Fixed
- **Leaderboard — unenrolled users excluded:** individual ranking and rank-in-sidebar no
  longer include users who have been unenrolled from the course.
- **Reports — unenrolled students excluded:** teacher reports tab now filters out
  unenrolled students.
- **SEPARATEGROUPS support in ranking:** when a course uses Separate Groups mode,
  students see only members of their own group in the individual leaderboard tab;
  teachers see all.
- **Site admins excluded from ranking and reports:** `get_admins()` is now merged into
  the capability-based exclusion list; site admins bypass `has_capability` checks
  (they appear via `CFG->siteadmins`), causing them to previously appear in rankings
  and teacher reports.
- **Reports default sort matches ranking tiebreaker:** the N. column in the reports tab
  now sorts by `currentxp DESC, timemodified ASC, lastname ASC`, consistent with the
  position students see in the ranking tab.
- **`.btn-close` style no longer bleeds into Moodle's block-config modal:** the CSS rule
  was scoped to `.block_playerhud` only, preventing accidental override of the close
  button in the native block editing drawer on `path-blocks-playerhud` pages.

### Strings changed
- `help_reset_checkbox` replaced by `help_use_default` and `help_use_default_note`
  (en / pt_br).

---

## [v1.3.12] — 2026-05-06

### Fixed
- **Item modal not opening in forum posts (filter context):** `filter_collect.js`
  was searching for `#phItemModalFilter` in the DOM but the modal HTML was
  never added there. Fixed by having `text_filter.php` append the modal HTML
  to `$text` so it lands in the DOM, and updating `filter_collect.js` to read
  strings via `M.util.get_string()` (registered server-side with
  `strings_for_js`) instead of AMD arguments, removing the dependency on a
  large `config.modalsHtml` blob that exceeded Moodle 4.5's 1024-character
  `js_call_amd` argument limit. Also applies the Bootstrap `getInstance`
  reuse pattern to prevent duplicate modal instances on repeated clicks, and
  guards against a null `lang` attribute in `toLocaleDateString`.
- **Copy button in drops table broken on HTTP:** replaced the custom
  `execCommand`-based fallback in `manage_drops.js` with Moodle's
  `core/copy_to_clipboard`, which handles both HTTPS and plain HTTP. Removed
  orphan lang strings `gen_yours` and `err_clipboard`.
- **Natural sort for quest and collection names:** replaced `strcmp()` with
  `strnatcasecmp()` in the quest and collection tabs so entries like "Nível 3"
  and "Nível 10" sort in human-expected numeric order instead of lexicographic.
- **Leaderboard — last score date shown for users with zero XP:** `timemodified`
  is set on record creation, not only on XP gain, so every enrolled user
  previously showed a timestamp before earning anything. The column now displays
  `–` when `currentxp == 0`.

---

## [v1.3.11] — 2026-05-06

### Fixed
- **Item modal not opening intermittently in Moodle 4.5 (Bootstrap 4):** when
  `openItemModal()` was called a second time on the same element, Bootstrap 4
  detected the existing instance stored in `$(el).data('bs.modal')` and
  silently ignored the `show()` call. Fixed by reusing the existing instance
  (`getInstance` in BS5, `$(el).data` in BS4) before creating a new one.
  Same fix applied to the character (`ph-char-modal`) modal.
- **ESLint `promise/no-nesting` in `manage_drops.js`:** the clipboard error
  handler used `Str.get_strings().then()` nested inside a `.catch()`. Replaced
  with strings pre-loaded via PHP `config.strings`, eliminating the nesting.

---

## [v1.3.10] — 2026-05-05

### Fixed
- **Copy button broken on HTTP:** replaced `navigator.clipboard` (unavailable
  outside HTTPS) with `core/copy_to_clipboard`, the Moodle-approved API that
  falls back to `execCommand` and works on plain HTTP (Moodle 4.5).
- **Duplicate modal on item card click:** `$(document).on()` accumulated
  handlers each time `init()` was called. Fixed with namespaced jQuery events
  (`.off()` before `.on()`), preventing double-open when the block appears
  more than once on the page.

---

## [v1.3.9] — 2026-05-05

### Added
- **Drop usage indicator in items table:** the drop button now shows the number
  of locations (📍), the total finite max-uses across all locations (🔄), and an
  infinity symbol (∞) when at least one location has unlimited drops. A legend
  explaining each icon was added to the expandable help box.

### Fixed
- **Crash when editing an item without RPG classes configured:** passing an empty
  array to a hidden form field caused `htmlspecialchars()` to fail. Fixed by
  using scalar `0` instead of `[]` when no class restriction is set.

---

## [v1.3.8] — 2026-05-05

### Added
- **Balance breakdown accordion:** the Game Economy card on the Config tab now
  includes a collapsible table listing every item and quest reward with their
  individual XP contribution (XP each × max uses per drop). Makes it easy to
  diagnose unexpected economy totals without database access.

### Improved
- **AI item generation — system-level rules:** the AI prompt for item generation
  now sends the role and rules (including the 4-word title limit) as a `system`
  instruction instead of embedding them in the user message. This makes models
  (Groq, OpenAI-compatible, Gemini) treat the rules as hard constraints, improving
  compliance with the title length restriction.

---

## [v1.3.7] — 2026-05-04

### Security
- **Capability enforcement in `get_content()`:** the block now checks
  `block/playerhud:view` before rendering any content, making the capability
  effective when restricted via the Permissions UI (previously the block rendered
  regardless of the capability setting).
- **`db/access.php` clean-up:** removed `guest` from `block/playerhud:view`
  archetypes (guests are already blocked by `isguestuser()` checks; the
  declaration was misleading). Restricted `myaddinstance` to `editingteacher` and
  `manager` — students should not be able to add the block to their personal
  Dashboard.

---

## [v1.3.6] — 2026-05-04

### Fixed
- **Quests tab:** `get_records_select` on `block_playerhud_quest_log` was keyed by
  `questid`, triggering Moodle's *"Duplicate value found in column"* debugging notice
  when a user had more than one log entry for the same quest. The query now selects `id`
  (PK) as the first column; a PHP loop builds the `$claimedrows` lookup keyed by
  `questid`, keeping the earliest claim per quest.
- **Config tab / Moove theme:** corrected visual regressions on Moodle 4.5 with the
  Moove theme (Bootstrap 4 environment):
  - Numbered rank badges (`1°/2°/3°`) now render with white text (`color: #fff` on
    `badge.bg-dark`).
  - API key input groups restore correct border-radius via new `ph-key-input-group`
    CSS class, replacing the broken BS4 `input-group-prepend/append` wrappers.
  - Description text in the API settings card uses `card-text` instead of
    `text-muted` to maintain proper contrast.
  - Advanced-config toggle anchor and hint paragraph no longer inherit `text-muted`
    colour, restoring legibility.
  - Tab anchor underlines forced by Moove's high-specificity rule
    `body.pagelayout-incourse #region-main a:not(.btn)` are now suppressed via an
    elevated selector (`0,1,3,1`) scoped to `#ph-*-tabs .nav-item a.nav-link`.

---

## [v1.3.5] — 2026-05-03

### Fixed
- Bootstrap modal opening in `view.js`, `manage_items.js`, `manage_drops.js`, and
  `filter_collect.js` now uses `document.body.appendChild()` +
  `require(['theme_boost/bootstrap/modal'])` instead of the unreliable `bootstrap`
  global and `jQuery.fn.modal()` patterns, ensuring modals open correctly on both
  Bootstrap 4 (Moodle 4.5) and Bootstrap 5 (Moodle 5.x). Obsolete
  `/* global bootstrap */` declarations removed from affected modules.

### Added
- Show/hide toggle buttons on all three API key fields (`apikey_gemini`, `apikey_groq`,
  `apikey_openai`) in the plugin configuration tab (`tab_config`). Pressing the button
  switches the input between `type="password"` and `type="text"`, making it easier for
  teachers to verify keys without leaving the page. The toggle label is localised
  (`toggle_visibility` string, EN / PT-BR).

### Strings added
- `toggle_visibility` (en / pt_br): accessible label for the show/hide API key button.

---

## [v1.3.4] — 2026-04-30

### Security
- **Medium:** `story_manager::make_choice` now validates that the submitted choice
  belongs to the player's current node before applying any side effect (karma, class
  change, item cost). Previously, any authenticated student could submit an arbitrary
  `choiceid` from the same block instance to skip story progression, saturate karma,
  switch RPG class, or mark chapters complete and claim their quest rewards without
  playing through the narrative. The fix computes the expected node from
  `rpg_progress.current_nodes` (falling back to the chapter's `is_start` node for
  first-time visitors) and throws `story_error_invalid_choice` on mismatch. Choices
  in already-completed chapters are also rejected upfront, preventing repeat reward
  farming via terminal choices.
- **Medium:** Items management table no longer emits raw HTML attribute strings via
  Mustache triple-mustache (`{{{preview_attributes}}}`). The `data-name`, `data-xp`,
  `data-image`, and `data-isimage` attributes are now rendered individually through
  escaped double-mustache placeholders (`{{preview_data_*}}`), closing an attribute
  injection path that allowed a teacher to plant JavaScript event handlers (e.g.
  `onfocus`, `autofocus`) visible to any other teacher or admin who opened the items
  management tab.
- **Medium:** OpenAI-compatible provider URL (teacher user preference and admin config)
  is now validated by `generator::is_safe_url()` before any HTTP request is made.
  The check enforces `https://` scheme and blocks loopback addresses (`localhost`,
  `127.0.0.1`, `::1`) and RFC-1918 / link-local / reserved IP ranges, preventing a
  teacher-configured endpoint from being used to probe the Moodle server's internal
  network (SSRF). Invalid URLs are silently skipped, falling through to the next
  configured provider.
- **Medium:** `controller/drops.php` `save_drop()` INSERT path now verifies that the
  supplied `itemid` belongs to the current block instance before creating the drop
  record, matching the existing validation on the UPDATE path. This closes a
  cross-instance confusion window where a teacher could forge the hidden `itemid` form
  field to create a drop pointing to an item from a different instance.
- **Medium:** `external::insert_drop_shortcode` and `external::remove_drop_shortcode`
  now verify that the block instance (`instanceid`) belongs to the course (`courseid`)
  passed in the same call, by comparing `context_block::get_course_context()->instanceid`
  against the supplied `courseid`. Previously a teacher with `manage` on Block A and
  `manageactivities` on Course B could combine both to write or remove shortcodes in
  Course B's activities using drops from Block A.
- **Low:** AI-generated item and class names are no longer concatenated directly into
  HTML strings in `manage_items.js` and `ai_oracle.js`. The success modals now use
  jQuery's `.text()` and `.appendTo()` DOM methods for AI-sourced values, and `.val()`
  for the shortcode input, eliminating the self-XSS vector in teacher-only modals.

### Changed
- `block_playerhud_inventory` query in `tab_collection` now joins `block_playerhud_items`
  and filters by `blockinstanceid`, scoping the result set explicitly to the current
  block instance (was previously filtered only by userid, with the instance scope
  enforced post-query by iteration).
- `controller/drops.php` sort and direction parameters now go through the same
  allow-list pattern used by the other management tabs (`tab_items`, `tab_quests`,
  `tab_reports`): `$sort` is validated against `['id', 'mapcode', 'maxusage',
  'respawntime', 'timecreated']` and `$dir` is normalised to `ASC`/`DESC`.
- `tab_reports.php` constructor normalises `$dir` to `ASC` or `DESC` (defaulting to
  `DESC`) instead of accepting any alphabetic string, aligning with the pattern used
  by the audit-log query inside the same class and by other management tabs.
- `manage_drops.js` drop preview now uses jQuery DOM methods (`.text()`, `.appendTo()`,
  `document.createTextNode()`) for all user-typed values (`linkTxt`, `previewTxt`,
  `previewEmo`, `currentItem.name`) instead of template-literal HTML concatenation.
  Server-sourced image URLs and emoji content (sanitised server-side) retain their
  existing `innerHTML`/template-literal form.
- Exception handler in `block_playerhud::get_content()` broadened from `\Exception`
  to `\Throwable` and log level raised from `DEBUG_DEVELOPER` to `DEBUG_NORMAL`,
  so render errors are visible in production logs instead of disappearing silently.

### Removed
- Dead stub `block_playerhud_upgrade($oldversion, $block)` removed from `lib.php`.
  The real Moodle upgrade hook (`xmldb_block_playerhud_upgrade`) lives in
  `db/upgrade.php` and was never affected.

### Tests
- `story_manager_test`: replaced `test_make_choice_does_not_duplicate_completed_chapter`
  with three targeted tests — `test_make_choice_records_chapter_completion_once`,
  `test_make_choice_throws_for_completed_chapter`, and
  `test_make_choice_throws_for_out_of_sequence_choice` — covering the new
  path-validation and completed-chapter rejection behaviour.

### Strings added
- `story_error_invalid_choice` (en / pt_br): shown when a player submits a choice
  that is not reachable from their current story position.

---

## [v1.3.3] — 2026-04-29

### Added
- Behat acceptance tests covering block access control, student gamification controls,
  and teacher management panel navigation (13 scenarios, 136 steps). Tests run on all
  CI matrix combinations via `moodle-plugin-ci behat --profile chrome`.

### Security
- **High:** `make_choice` and `preview_nav` now load story choices via a JOIN through
  `story_nodes → chapters → blockinstanceid`, preventing any student from submitting
  arbitrary choice IDs from other block instances to manipulate karma, class assignment,
  or quest completion without playing the story.
- **High (server-side):** `req_class_id` and `req_karma_min` requirements are now
  re-validated server-side in `make_choice`, mirroring the UI enforcement in
  `prepare_node_data`. Previously only the item cost was enforced on the server.
- **Medium:** `load_recap` now verifies that the requested `chapterid` belongs to the
  supplied `instanceid` before reading saved node paths, closing the cross-instance
  narrative content disclosure that was possible after a `make_choice` exploit.
- **Medium:** Drop delete, bulk-delete, form load and update in `controller/drops.php`
  now scope every operation to the verified block instance via a JOIN on
  `block_playerhud_items.blockinstanceid`, preventing a teacher with `manage` rights
  on one instance from deleting or re-parenting drops belonging to another instance.
  The `save_drop` update path additionally preserves `blockinstanceid` and `itemid`
  from the existing DB record rather than re-deriving them from form input.
- **Medium:** `controller/scenes.php` node load and update now filter by
  `['id' => $nodeid, 'chapterid' => $chapterid]`; the choices pre-load is gated by a
  `record_exists` check. This prevents a teacher from loading or overwriting story nodes
  that belong to a chapter in a different block instance.
- **Low:** Replaced all 14 occurrences of bare `unserialize(base64_decode($…->configdata))`
  with Moodle's hardened `unserialize_object()`, aligning with core's defence-in-depth
  posture against PHP object injection.
- **Low:** Removed `'noclean' => true` from `tab_rules::export_for_template`; the help
  content is now sanitised by Moodle's standard HTML purifier via `format_text` with the
  correct block context.
- **Low:** AI API keys (`apikey_gemini`, `apikey_groq`, `apikey_openai`) in admin
  settings changed from `admin_setting_configtext` to `admin_setting_configpasswordunmask`,
  masking the values by default in the administration interface.
- **Low:** CLI seed scripts (`cli/seed.php`, `cli/seed_pt_br.php`) now require an explicit
  `--password=<value>` flag (no default) and abort if `$CFG->wwwroot` does not match a
  known development pattern (`localhost`, `127.0.0.1`, `.local`, `.test`), preventing
  accidental creation of known-credential accounts on non-development sites.

### Fixed
- Chapter and scene delete modals now move themselves to `<body>` via
  `document.body.appendChild` before opening, preventing the Moodle block drawer from
  collapsing when the confirmation dialog appears.
- Chapter delete confirmation message was showing literal `&quot;{$a}&quot;` due to
  double HTML-encoding (`s()` + Mustache `{{...}}`). Replaced with a plain
  `get_string(…, $chap->title)` call so the chapter name interpolates correctly.
- Chapters without a starting scene (`is_start = 1`) are now hidden from the student
  story tab, preventing a raw `story_error_node_not_found` exception when the student
  clicked such a chapter. The management tab shows a warning badge on affected chapters.

### Added
- Lang string `chapter_no_start_warning` (EN/PT-BR): shown as a warning badge on
  chapters that have no starting scene in the teacher management view.
- Lang strings `story_error_class_required` and `story_error_karma_required` (EN/PT-BR):
  server-side enforcement messages for character and reputation gating on story choices.

### Changed
- Help text strings `help_teacher_section_classes` and `help_teacher_section_story`
  updated in both locales to replace the internal term "karma" with the player-facing
  term "reputation" / "reputação", consistent with the rest of the UI.

---

## [v1.3.2] — 2026-04-28

### Added
- Player group widget in HUD block and sidebar (soft dependency on `mod_playergroup`).

### Fixed
- XP label appended to total XP value in report KPI card.
- Tier labels updated to chapter-based progression (`tier_1` … `tier_5`).
- AI item generation no longer copies placeholder emoji into new items.
- Filter widget enforces a 6-item stash limit during session.

---

## [v1.3.1] — 2026-04-23

### Fixed
- Bootstrap 4 compatibility: accordion polyfill, replaced `gap-2` with `me-2` in templates.
- Character card modal now opens regardless of whether a description is set.
- Modal close button CSS lint issues resolved.
- HTML validation error (`div` inside `button`) in sidebar.

---

## [v1.3.0] — 2026-04-22

### Added
- Per-feature toggles for Items, Quests, RPG (characters/story), and Ranking modules.
- PHPDoc CI check added to the test pipeline.

### Changed
- Merged `feature/rpg` branch into main, shipping the full RPG system (characters,
  story engine, reputation) as part of the stable release.
- Teacher role label standardised to "Game Master" / "Mestre do Jogo".
- "Class" renamed to "Character" throughout the UI and lang strings.

---

## [v1.2.2] — 2026-04-22

### Added
- Interactive branching story engine with chapters, scenes, choices, karma/reputation
  effects, character assignment, and item costs.
- AI story generation (Gemini, Groq, OpenAI-compatible) for chapters and scenes.
- Story summary (recap) modal with chapter filter (All / Read / Unread).
- Character lore modal; character selection moved into the story flow.
- Star rating and reputation colour styles for character cards.
- English seed script (`cli/seed.php`); PT-BR seed renamed to `cli/seed_pt_br.php`.

### Fixed
- `assign_class` not updating `classid` on re-assignment.

---

## [v1.1.x] — 2026-03 / 2026-04

### Added
- Export to CSV and XLSX in the reports tab.
- AI API keys moved to per-user preferences to prevent backup leakage.
- Privacy API: `delete_data_for_user`, `export_user_data`, and user-preference provider.
- CI matrix extended: Moodle 5.0, 5.1, 5.2 (Early bird).

### Fixed
- Various coding-style fixes (PHPCS/Moodle standard compliance).
- PostgreSQL 16 service in CI for Moodle 5.2 compatibility.

---

## [v1.0.5] — 2026-02-27

### Added
- Initial stable public release on the Moodle Plugin Directory.
- XP/level system, drop collection, quest engine, leaderboard with privacy controls.
- Trading/shop system with one-time and group-restricted offers, lock-protected
  transactions.
- Full backup/restore with namespaced ID mapping and deferred resolution.
- Mobile web service support.
- Privacy API provider with `delete_data_for_user_list_in_context`.
- AMD modules for all interactive features; no inline `<script>` tags.
