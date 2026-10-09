# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai/chat_test.php` | 2 |
| `ai/generator_test.php` | 19 |
| `ai/hub_usage_reporting_test.php` | 2 |
| `backup_restore_item_images_test.php` | 1 |
| `backup_restore_test.php` | 3 |
| `block_dashboard_test.php` | 3 |
| `collection_tab_test.php` | 17 |
| `db_access_test.php` | 2 |
| `db_upgrade_test.php` | 9 |
| `drop_guard_test.php` | 11 |
| `edit_form_test.php` | 5 |
| `form/edit_item_form_test.php` | 8 |
| `form/edit_scene_form_test.php` | 3 |
| `game_test.php` | 47 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 1 |
| `item_delete_cascade_test.php` | 25 |
| `karma_test.php` | 11 |
| `lib_test.php` | 27 |
| `privacy_provider_test.php` | 26 |
| `quest_test.php` | 53 |
| `rpg_classes_test.php` | 3 |
| `story_manager_test.php` | 31 |
| `suggest_trades_state_test.php` | 4 |
| `template_strings_test.php` | 3 |
| `trade_test.php` | 12 |
| `uninstall_test.php` | 2 |
| `utils_test.php` | 22 |
| **Subtotal** | **358** |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `analytics_test.php` | 13 |
| `audit_log_test.php` | 16 |
| `drop_distribution_test.php` | 13 |
| `external_items_test.php` | 27 |
| `latepenalty_bridge_test.php` | 2 |
| `wizard_test.php` | 22 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **108** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `chat_message_test.php` | 4 |
| `collect_item_test.php` | 5 |
| `create_avatar_pack_test.php` | 7 |
| `create_class_pack_test.php` | 8 |
| `create_playercoin_test.php` | 4 |
| `execute_chat_action_test.php` | 6 |
| `generate_ai_content_test.php` | 4 |
| `generate_class_oracle_test.php` | 4 |
| `generate_story_test.php` | 4 |
| `insert_drop_shortcode_test.php` | 12 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 4 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 8 |
| `setup_playercoin_drop_test.php` | 7 |
| `use_item_test.php` | 13 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 13 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_rollback_test.php` | 4 |
| `wizard_run_step_test.php` | 57 |
| `wizard_start_test.php` | 9 |
| **Subtotal** | **186** |

### Testes de Controlador (`tests/controller/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `aikeys_test.php` | 4 |
| `chapters_test.php` | 17 |
| `classes_test.php` | 7 |
| `drops_test.php` | 19 |
| `export_test.php` | 10 |
| `items_test.php` | 26 |
| `manage_entry_points_test.php` | 34 |
| `quests_test.php` | 14 |
| `scenes_test.php` | 6 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 10 |
| **Subtotal** | **151** |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `chapters_escaping_test.php` | 8 |
| `manage/item_delete_confirm_test.php` | 12 |
| `manage/item_disable_confirm_test.php` | 3 |
| `manage/quest_delete_confirm_test.php` | 3 |
| `manage/tab_chapters_test.php` | 4 |
| `manage/tab_config_test.php` | 4 |
| `manage/tab_items_escaping_test.php` | 7 |
| `manage/tab_items_test.php` | 3 |
| `manage/tab_quests_test.php` | 4 |
| `manage/tab_reports_test.php` | 13 |
| `player_screens_escaping_test.php` | 18 |
| `profile_content_test.php` | 1 |
| `quests_escaping_test.php` | 5 |
| `reports_escaping_test.php` | 6 |
| `trades_escaping_test.php` | 4 |
| `view/header_test.php` | 2 |
| `view/tab_chapters_test.php` | 6 |
| `view/tab_history_test.php` | 4 |
| `view/tab_quests_test.php` | 6 |
| `view/tab_ranking_test.php` | 4 |
| `view/tab_rules_test.php` | 2 |
| `view/tab_shop_test.php` | 4 |
| **Subtotal** | **123** |

| **Total geral** | **926** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **67%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
