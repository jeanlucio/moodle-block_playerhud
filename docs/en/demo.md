# 🌱 Demo Environment (Quick Start)

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
* A "Deadline Extension" item wired to a real, already-applied [Late Penalty](latepenalty.html) deduction — only seeded if `local_latepenalty` is installed
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
