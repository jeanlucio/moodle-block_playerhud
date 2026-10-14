# ⚖️ Economy Health Panel

The **Config** tab in the management panel includes an **Economy Health** widget that compares the total XP a student can earn (all items × their drop limits + quest rewards) against the configured XP cap (XP per level × number of levels).

| Coverage | Status |
|---|---|
| Exactly 100% | ✅ Green — "Balanced configuration" |
| Below 100% | ⚠️ Yellow — students cannot reach the maximum level; add more items or quests, or lower the cap |
| Above 100% | 🔴 Red — students can exceed the cap; reduce item/quest XP or increase the cap |

The widget also shows a collapsible breakdown table listing every item and quest with its individual XP contribution, making it easy to identify which content is over- or under-contributing to the economy.

## Drops that are not placed yet

The figures above count every drop, but a drop only pays XP once its shortcode sits where students can see it. When the panel finds drops in no activity description, page content or the news forum, it adds a notice under the balance message with how many drops they are and how much XP they would pay, and links to **Distribute Drops**. That XP is part of the total above but cannot be earned until the drops are placed.

The check reads the same places as the Distribute Drops screen. A drop placed somewhere it does not look (a book chapter, a section summary, a forum post) is reported as "not found"; in that case the notice can be ignored.
