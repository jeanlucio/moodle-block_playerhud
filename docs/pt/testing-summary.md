# 🧪 Testes Automatizados

O PlayerHUD inclui uma suíte de testes extensa que cobre tanto a lógica de negócio (PHPUnit) quanto a aceitação em navegador (Behat). Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai/generator_test.php` | 2 |
| `backup_restore_test.php` | 3 |
| `collection_tab_test.php` | 6 |
| `content_crud_test.php` | 13 |
| `cross_instance_security_test.php` | 12 |
| `drop_guard_test.php` | 7 |
| `game_test.php` | 32 |
| `gamemaster_test.php` | 6 |
| `instance_delete_test.php` | 1 |
| `item_delete_cascade_test.php` | 15 |
| `karma_test.php` | 11 |
| `privacy_provider_test.php` | 10 |
| `quest_test.php` | 33 |
| `rpg_classes_test.php` | 7 |
| `story_manager_test.php` | 15 |
| `suggest_trades_state_test.php` | 4 |
| `trade_test.php` | 7 |
| `utils_test.php` | 4 |
| **Subtotal** | **188** |

### Testes de Lógica de Negócio Compartilhada (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `analytics_test.php` | 9 |
| `drop_distribution_test.php` | 12 |
| `wizard_test.php` | 17 |
| `xp_budget_test.php` | 15 |
| **Subtotal** | **53** |

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
| `insert_drop_shortcode_test.php` | 7 |
| `load_recap_test.php` | 3 |
| `load_scene_test.php` | 3 |
| `make_choice_test.php` | 3 |
| `remove_drop_shortcode_test.php` | 5 |
| `setup_playercoin_drop_test.php` | 6 |
| `use_item_test.php` | 6 |
| `wizard_apply_suggested_levels_test.php` | 3 |
| `wizard_generate_helpers_test.php` | 9 |
| `wizard_list_runs_test.php` | 4 |
| `wizard_run_step_test.php` | 56 |
| `wizard_start_test.php` | 8 |
| **Subtotal** | **145** |

### Testes de Controlador (`tests/controller/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `aikeys_test.php` | 4 |
| `chapters_test.php` | 13 |
| `classes_test.php` | 7 |
| `collect_test.php` | 3 |
| `drops_test.php` | 11 |
| `export_test.php` | 7 |
| `items_test.php` | 11 |
| `quests_test.php` | 7 |
| `scenes_test.php` | 6 |
| `suggestions_test.php` | 4 |
| `trades_test.php` | 7 |
| **Subtotal** | **80** |

### Testes de Saída / Renderer (`tests/output/`)

| Arquivo de teste | Casos |
|------------------|------:|
| `manage/item_delete_confirm_test.php` | 6 |
| `manage/tab_chapters_test.php` | 4 |
| **Subtotal** | **10** |

| **Total geral** | **476** |

```bash
vendor/bin/phpunit --testsuite block_playerhud
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **37%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
