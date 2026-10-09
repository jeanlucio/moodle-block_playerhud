# 🩺 Usage & Diagnostics Page

Site administrators get a page at **Site administration > Reports > PlayerHUD usage and diagnostics** (capability `moodle/site:config`). Every figure is counted on the site itself — **nothing is sent outside it**.

### Adoption

How widely the block is used, counted per course (the block allows one instance per course):

* Courses with the block, split into courses with items, with players but no items, and with an empty block — the three add up to the total.
* Courses with each feature on: RPG mode, quests, ranking, items.
* Blocks left on user Dashboards by earlier releases, shown only when there are some. They no longer work, and each user can delete theirs in the Dashboard edit mode.

### Engagement

* Players, players who turned gamification off, and the XP currently held by players.
* Over the **last 90 days**: players who earned XP, items received, quests completed, trades made, AI requests (per provider) and assistant runs.

Figures are cached for 10 minutes; a **Refresh** button recounts them.

### Data integrity and cleanup

Sites that removed the block before v1.7.0 may still hold the data of those blocks, and earlier versions could leave rows behind when an item, quest, chapter, scene, trade or assistant run was deleted. None of it appears on any page or counts towards anything in the game.

* **Removed block instances:** one row per removed block, with its players, XP, items, activity dates and course. The course is recovered from the log when possible; instances whose **course still exists** start unticked, since the block may have been removed by mistake. Only the ticked ones are deleted. At most 100 are listed at a time — the rest appear after that cleanup.
* **Leftovers of old deletions:** described in words (e.g. "Story scenes whose chapter no longer exists"). They are always included in the cleanup, and deleting them changes nothing in the courses in use.

Deleting needs an explicit confirmation and runs in one transaction. **Before deleting, a copy of every row is saved as a JSON file** in the site data directory (`moodledata/block_playerhud/orphan_backups/`). The page lists these copies with **Download** and **Delete** buttons. The copies contain personal data (user IDs and XP) of the deleted rows, so delete them once you no longer need them; they are not removed automatically.
