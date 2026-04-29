# Changelog — block_playerhud

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
