# Changelog — block_playerhud

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
