# 🏆 Group Ranking Behavior

When the group ranking is enabled, each group's average XP is calculated **only from members who are actively participating** — meaning members who have both:

* **Gamification enabled** (`enable_gamification = 1`)
* **Ranking visible** (`ranking_visibility = 1`)

Members who have opted out of gamification or hidden their ranking are completely excluded from the group's sum and count. The denominator used to calculate the average reflects only the number of active participants, not the total group size.

**Practical implication:** a group with many opted-out members may show a higher average than expected, because the average is computed over a smaller subset. Teachers should be aware that a group's displayed average does not represent all enrolled members — only those actively participating in the ranking.

### Integration with PlayerGroup

The group ranking reads directly from Moodle's native group tables (`{groups}` / `{groups_members}`). It works with **any** Moodle group — whether created manually by a teacher or automatically via the **PlayerGroup** activity module.

When **PlayerGroup** (`mod_playergroup`) is installed alongside PlayerHUD, an additional integration activates **inside the HUD header** (not the ranking tab): the student's group badge, group name, member count, and capacity (e.g. `3/5`) are displayed at the top of the block. This information is fetched via PlayerGroup's public API (`\mod_playergroup\api\group_info`) and is only available for groups created through PlayerGroup activities — manually created Moodle groups are not shown there.

The two features are independent:

| Scenario | Group Ranking tab | HUD header group info |
|---|---|---|
| No PlayerGroup installed | ✅ Works with any Moodle group | — Not shown |
| PlayerGroup installed, student has a PlayerGroup group | ✅ Group appears in ranking | ✅ Badge + name + slots displayed |
| PlayerGroup installed, student is in a manual group only | ✅ Group appears in ranking | — Not shown (manual groups not in PlayerGroup API) |
