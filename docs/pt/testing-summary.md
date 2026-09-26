# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai/generator_test.php` | 5 |
| `ai/hub_usage_reporting_test.php` | 2 |
| `backup_restore_test.php` | 3 |
| `collection_tab_test.php` | 17 |
| `content_crud_test.php` | 13 |
| `cross_instance_security_test.php` | 12 |
| `db_access_test.php` | 1 |
| `db_upgrade_test.php` | 9 |
| `drop_guard_test.php` | 11 |
| `form/edit_item_form_test.php` | 4 |
| `game_test.php` | 47 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 1 |
| `item_delete_cascade_test.php` | 22 |
| `karma_test.php` | 11 |
| `lib_test.php` | 27 |
| `privacy_provider_test.php` | 24 |
| `quest_test.php` | 45 |
| `rpg_classes_test.php` | 3 |
| `story_manager_test.php` | 31 |
| `suggest_trades_state_test.php` | 4 |
| `trade_test.php` | 11 |
| `uninstall_test.php` | 2 |
| `utils_test.php` | 19 |
| **Subtotal** | **330** |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `analytics_test.php` | 12 |
| `audit_log_test.php` | 16 |
| `drop_distribution_test.php` | 13 |
| `external_items_test.php` | 24 |
| `wizard_test.php` | 21 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **101** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `chat_message_test.php` | 4 |
| `collect_item_test.php` | 5 |
| `create_avatar_pack_test.php` | 7 |
| `create_class_pack_test.php` | 8 |
| `create_playercoin_test.php` | 4 |
| `execute_chat_action_test.php` | 5 |
| `generate_ai_content_test.php` | 4 |
| `generate_class_oracle_test.php` | 4 |
| `generate_story_test.php` | 4 |
| `insert_drop_shortcode_test.php` | 11 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 3 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 7 |
| `setup_playercoin_drop_test.php` | 6 |
| `use_item_test.php` | 13 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 13 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_rollback_test.php` | 3 |
| `wizard_run_step_test.php` | 57 |
| `wizard_start_test.php` | 9 |
| **Subtotal** | **180** |

### Testes de Controlador (`tests/controller/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `aikeys_test.php` | 4 |
| `chapters_test.php` | 17 |
| `classes_test.php` | 7 |
| `drops_test.php` | 19 |
| `export_test.php` | 8 |
| `items_test.php` | 26 |
| `manage_entry_points_test.php` | 34 |
| `quests_test.php` | 13 |
| `scenes_test.php` | 6 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 10 |
| **Subtotal** | **148** |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `manage/item_delete_confirm_test.php` | 9 |
| `manage/quest_delete_confirm_test.php` | 3 |
| `manage/tab_chapters_test.php` | 4 |
| `manage/tab_config_test.php` | 2 |
| `manage/tab_quests_test.php` | 3 |
| `manage/tab_reports_test.php` | 12 |
| `profile_content_test.php` | 1 |
| `view/header_test.php` | 2 |
| `view/tab_chapters_test.php` | 5 |
| `view/tab_history_test.php` | 4 |
| `view/tab_quests_test.php` | 4 |
| `view/tab_ranking_test.php` | 4 |
| `view/tab_rules_test.php` | 2 |
| `view/tab_shop_test.php` | 4 |
| **Subtotal** | **59** |

| **Total geral** | **818** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **67%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
