# 🧪 Automated Tests

PlayerHUD ships with an extensive test suite covering both business logic (PHPUnit) and browser acceptance (Behat). Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

### PHPUnit — Unit & Integration Tests

| Test file | Cases |
|-----------|------:|
| `ai/generator_test.php` | 2 |
| `backup_restore_test.php` | 3 |
| `collection_tab_test.php` | 8 |
| `content_crud_test.php` | 13 |
| `cross_instance_security_test.php` | 12 |
| `drop_guard_test.php` | 7 |
| `game_test.php` | 36 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 1 |
| `item_delete_cascade_test.php` | 17 |
| `karma_test.php` | 11 |
| `privacy_provider_test.php` | 10 |
| `quest_test.php` | 34 |
| `rpg_classes_test.php` | 7 |
| `story_manager_test.php` | 15 |
| `suggest_trades_state_test.php` | 4 |
| `trade_test.php` | 8 |
| `utils_test.php` | 4 |
| **Subtotal** | **198** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `analytics_test.php` | 11 |
| `audit_log_test.php` | 5 |
| `drop_distribution_test.php` | 12 |
| `external_items_test.php` | 18 |
| `wizard_test.php` | 17 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **78** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `chat_message_test.php` | 2 |
| `collect_item_test.php` | 4 |
| `create_avatar_pack_test.php` | 6 |
| `create_class_pack_test.php` | 7 |
| `create_playercoin_test.php` | 3 |
| `execute_chat_action_test.php` | 4 |
| `generate_ai_content_test.php` | 2 |
| `generate_class_oracle_test.php` | 2 |
| `generate_story_test.php` | 2 |
| `insert_drop_shortcode_test.php` | 7 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 3 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 5 |
| `setup_playercoin_drop_test.php` | 6 |
| `use_item_test.php` | 6 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 10 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_run_step_test.php` | 56 |
| `wizard_start_test.php` | 8 |
| **Subtotal** | **146** |

### Controller Tests (`tests/controller/`)

| Test file | Cases |
|-----------|------:|
| `aikeys_test.php` | 4 |
| `chapters_test.php` | 13 |
| `classes_test.php` | 7 |
| `collect_test.php` | 3 |
| `drops_test.php` | 11 |
| `export_test.php` | 7 |
| `items_test.php` | 15 |
| `quests_test.php` | 12 |
| `scenes_test.php` | 6 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 7 |
| **Subtotal** | **89** |

### Output / Renderer Tests (`tests/output/`)

| Test file | Cases |
|-----------|------:|
| `manage/item_delete_confirm_test.php` | 9 |
| `manage/quest_delete_confirm_test.php` | 3 |
| `manage/tab_chapters_test.php` | 4 |
| **Subtotal** | **16** |

| **Grand Total** | **527** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Overall line coverage** (`moodle-coverage`, PHPUnit + Xdebug): **42%**.

[Full test-by-test breakdown and coverage table →]({{ '/testing.html' | relative_url }})
