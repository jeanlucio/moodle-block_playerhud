# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai/chat_test.php` | 4 |
| `ai/generator_test.php` | 47 |
| `ai/hub_usage_reporting_test.php` | 2 |
| `backup_restore_item_images_test.php` | 1 |
| `backup_restore_test.php` | 4 |
| `block_dashboard_test.php` | 3 |
| `collection_tab_test.php` | 17 |
| `db_access_test.php` | 2 |
| `db_upgrade_test.php` | 11 |
| `drop_guard_test.php` | 11 |
| `edit_form_test.php` | 5 |
| `form/edit_chapter_form_test.php` | 6 |
| `form/edit_class_form_test.php` | 1 |
| `form/edit_item_form_test.php` | 8 |
| `form/edit_quest_form_test.php` | 4 |
| `form/edit_scene_form_test.php` | 10 |
| `form/edit_trade_form_test.php` | 3 |
| `form/name_length_test.php` | 9 |
| `game_test.php` | 57 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 2 |
| `item_delete_cascade_test.php` | 27 |
| `karma_test.php` | 11 |
| `lib_test.php` | 28 |
| `privacy_provider_test.php` | 25 |
| `quest_closed_activity_test.php` | 8 |
| `quest_test.php` | 54 |
| `rpg_classes_test.php` | 3 |
| `story_manager_test.php` | 35 |
| `suggest_trades_state_test.php` | 4 |
| `template_strings_test.php` | 3 |
| `trade_test.php` | 14 |
| `uninstall_test.php` | 2 |
| `utils_test.php` | 40 |
| **Subtotal** | **467** |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `analytics_test.php` | 15 |
| `audit_log_test.php` | 16 |
| `diagnostics/integrity_test.php` | 16 |
| `diagnostics/report_test.php` | 3 |
| `diagnostics/usage_test.php` | 2 |
| `drop_distribution_test.php` | 14 |
| `external_items_test.php` | 30 |
| `latepenalty_bridge_test.php` | 2 |
| `wizard_test.php` | 24 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **137** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `block_access_test.php` | 2 |
| `chat_message_test.php` | 4 |
| `collect_item_test.php` | 5 |
| `create_avatar_pack_test.php` | 7 |
| `create_class_pack_test.php` | 8 |
| `create_playercoin_test.php` | 4 |
| `execute_chat_action_test.php` | 9 |
| `generate_ai_content_test.php` | 4 |
| `generate_class_oracle_test.php` | 4 |
| `generate_story_test.php` | 4 |
| `insert_drop_shortcode_test.php` | 14 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 4 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 10 |
| `setup_playercoin_drop_test.php` | 9 |
| `use_item_test.php` | 15 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 14 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_rollback_test.php` | 4 |
| `wizard_run_step_test.php` | 63 |
| `wizard_start_test.php` | 9 |
| **Subtotal** | **206** |

### Testes de Controlador (`tests/controller/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `aikeys_test.php` | 16 |
| `chapters_test.php` | 19 |
| `classes_test.php` | 8 |
| `drops_test.php` | 20 |
| `export_test.php` | 11 |
| `items_test.php` | 27 |
| `manage_entry_points_test.php` | 38 |
| `quests_test.php` | 14 |
| `scenes_test.php` | 11 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 12 |
| **Subtotal** | **180** |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `admin/diagnostics_test.php` | 6 |
| `chapters_escaping_test.php` | 8 |
| `manage/item_delete_confirm_test.php` | 13 |
| `manage/item_disable_confirm_test.php` | 3 |
| `manage/quest_delete_confirm_test.php` | 3 |
| `manage/tab_chapters_test.php` | 4 |
| `manage/tab_config_test.php` | 6 |
| `manage/tab_items_escaping_test.php` | 8 |
| `manage/tab_items_test.php` | 3 |
| `manage/tab_quests_test.php` | 5 |
| `manage/tab_reports_test.php` | 22 |
| `player_screens_escaping_test.php` | 18 |
| `profile_content_test.php` | 1 |
| `quests_escaping_test.php` | 5 |
| `reports_escaping_test.php` | 6 |
| `sidebar_escaping_test.php` | 2 |
| `trades_escaping_test.php` | 4 |
| `view/header_test.php` | 3 |
| `view/tab_chapters_test.php` | 6 |
| `view/tab_history_test.php` | 4 |
| `view/tab_quests_test.php` | 6 |
| `view/tab_ranking_test.php` | 13 |
| `view/tab_rules_test.php` | 2 |
| `view/tab_shop_test.php` | 4 |
| **Subtotal** | **155** |

| **Total geral** | **1145** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **81%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
