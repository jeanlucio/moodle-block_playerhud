# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai/generator_test.php` | 2 |
| `ai/hub_usage_reporting_test.php` | 2 |
| `backup_restore_test.php` | 3 |
| `collection_tab_test.php` | 9 |
| `content_crud_test.php` | 13 |
| `cross_instance_security_test.php` | 12 |
| `db_access_test.php` | 1 |
| `db_upgrade_test.php` | 7 |
| `drop_guard_test.php` | 7 |
| `form/edit_item_form_test.php` | 4 |
| `game_test.php` | 41 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 1 |
| `item_delete_cascade_test.php` | 17 |
| `karma_test.php` | 11 |
| `lib_test.php` | 27 |
| `privacy_provider_test.php` | 19 |
| `quest_test.php` | 40 |
| `rpg_classes_test.php` | 8 |
| `story_manager_test.php` | 26 |
| `suggest_trades_state_test.php` | 4 |
| `trade_test.php` | 9 |
| `utils_test.php` | 6 |
| **Subtotal** | **275** |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `analytics_test.php` | 11 |
| `audit_log_test.php` | 5 |
| `drop_distribution_test.php` | 12 |
| `external_items_test.php` | 19 |
| `wizard_test.php` | 20 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **82** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `chat_message_test.php` | 2 |
| `collect_item_test.php` | 4 |
| `create_avatar_pack_test.php` | 6 |
| `create_class_pack_test.php` | 7 |
| `create_playercoin_test.php` | 3 |
| `execute_chat_action_test.php` | 4 |
| `generate_ai_content_test.php` | 2 |
| `generate_class_oracle_test.php` | 2 |
| `generate_story_test.php` | 2 |
| `insert_drop_shortcode_test.php` | 11 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 3 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 5 |
| `setup_playercoin_drop_test.php` | 6 |
| `use_item_test.php` | 10 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 12 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_rollback_test.php` | 3 |
| `wizard_run_step_test.php` | 57 |
| `wizard_start_test.php` | 9 |
| **Subtotal** | **161** |

### Testes de Controlador (`tests/controller/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `aikeys_test.php` | 4 |
| `chapters_test.php` | 16 |
| `classes_test.php` | 7 |
| `drops_test.php` | 15 |
| `export_test.php` | 7 |
| `items_test.php` | 16 |
| `manage_entry_points_test.php` | 23 |
| `quests_test.php` | 13 |
| `scenes_test.php` | 6 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 10 |
| **Subtotal** | **121** |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `manage/item_delete_confirm_test.php` | 9 |
| `manage/quest_delete_confirm_test.php` | 3 |
| `manage/tab_chapters_test.php` | 4 |
| `manage/tab_config_test.php` | 2 |
| `manage/tab_reports_test.php` | 5 |
| `view/header_test.php` | 2 |
| `view/tab_chapters_test.php` | 5 |
| `view/tab_class_select_test.php` | 3 |
| `view/tab_history_test.php` | 2 |
| `view/tab_quests_test.php` | 4 |
| `view/tab_ranking_test.php` | 4 |
| `view/tab_rules_test.php` | 2 |
| `view/tab_shop_test.php` | 4 |
| **Subtotal** | **49** |

| **Total geral** | **688** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **63%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
