<?php
namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class tab_collection implements renderable, templatable {

    protected $config;
    protected $player;
    protected $instanceid;

    public function __construct($config, $player, $instanceid) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
    }

    public function export_for_template(renderer_base $output) {
        global $DB, $CFG, $PAGE;
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

        // 1. Captura parâmetro de ordenação (Padrão: xp_asc)
        $current_sort = optional_param('sort', 'xp_asc', PARAM_ALPHANUMEXT);

        // 2. Buscar Inventário do Banco
        // JOIN para saber se a origem é um drop infinito
        $sql = "SELECT inv.*, d.maxusage as drop_maxusage
                  FROM {block_playerhud_inventory} inv
             LEFT JOIN {block_playerhud_drops} d ON inv.dropid = d.id
                 WHERE inv.userid = :userid";
        
        $rawinventory = $DB->get_records_sql($sql, ['userid' => $this->player->userid]);
        
        $inventorybyitem = [];
        if ($rawinventory) {
            foreach ($rawinventory as $inv) {
                $inventorybyitem[$inv->itemid][] = $inv;
            }
        }

        // Buscar todos os itens (Ordem inicial do SQL não importa, pois vamos reordenar)
        $allitems = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid], 'xp ASC');
        
        // Verificar classe do jogador para visibilidade
        $myclassid = 0; 
        if ($DB->get_manager()->table_exists('block_playerhud_rpg_progress')) {
             $prog = $DB->get_record('block_playerhud_rpg_progress', [
                'userid' => $this->player->userid, 
                'blockinstanceid' => $this->instanceid
            ]);
            if ($prog) $myclassid = $prog->classid;
        }

        $items_data = [];
        $context = \context_block::instance($this->instanceid);

        if ($allitems) {
            foreach ($allitems as $item) {
                $usercopies = isset($inventorybyitem[$item->id]) ? $inventorybyitem[$item->id] : [];
                $total_count = count($usercopies);

                // Regras de Visibilidade (Se não tem o item)
                if ($total_count == 0) {
                    if (!$item->enabled) continue;
                    if (!block_playerhud_is_visible_for_class($item->required_class_id, $myclassid)) continue;
                }

                $media = \block_playerhud\utils::get_item_display_data($item, $context);
                $isinfinite_config = $DB->record_exists('block_playerhud_drops', ['itemid' => $item->id, 'maxusage' => 0]);
                $js_payload = $media['is_image'] ? $media['url'] : strip_tags($media['content']);

                // Contagem separada (Finito vs Infinito)
                $count_infinite = 0;
                $count_finite = 0;
                $lastts = 0;

                foreach ($usercopies as $copy) {
                    if ($copy->timecreated > $lastts) {
                        $lastts = $copy->timecreated;
                    }
                    if (!is_null($copy->drop_maxusage) && $copy->drop_maxusage == 0) {
                        $count_infinite++;
                    } else {
                        $count_finite++;
                    }
                }

                // Nome formatado para ordenação (remove tags HTML e converte segredos)
                $visible_name = format_string($item->name);
                $sort_name = strip_tags($visible_name); // Chave limpa para ordenação

                // Lógica de Item Não Coletado
                if ($total_count == 0) {
                    $itemObj = [
                        'card_class' => 'ph-missing',
                        'date_str' => '&nbsp;' // Espaço vazio para manter altura
                    ];
                    
                    if ($item->secret) {
                        $itemObj['name'] = get_string('secret_name', 'block_playerhud');
                        $sort_name = 'zzzz_secret'; // Força segredos para o final da lista A-Z
                        $itemObj['xp_text'] = "???";
                        $itemObj['description'] = get_string('secret_desc', 'block_playerhud');
                        $itemObj['is_image'] = false;
                        $itemObj['image_content'] = '❓';
                        $itemObj['data_image_payload'] = '❓';
                    } else {
                        $itemObj['name'] = $visible_name;
                        $itemObj['xp_text'] = "{$item->xp} XP";
                        $itemObj['description'] = format_text($item->description, FORMAT_HTML);
                        $itemObj['is_image'] = $media['is_image'];
                        $itemObj['image_url'] = $media['is_image'] ? $media['url'] : '';
                        $itemObj['image_content'] = $media['is_image'] ? '' : $media['content'];
                        $itemObj['data_image_payload'] = $js_payload;
                    }
                } else {
                    // Lógica de Item Coletado
                    $itemObj = [
                        'card_class' => 'ph-owned',
                        'name' => $visible_name,
                        'xp_text' => "{$item->xp} XP",
                        'description' => format_text($item->description, FORMAT_HTML),
                        'is_image' => $media['is_image'],
                        'image_url' => $media['is_image'] ? $media['url'] : '',
                        'image_content' => $media['is_image'] ? '' : $media['content'],
                        'data_image_payload' => $js_payload
                    ];

                    // Origens
                    $itemObj['origin_map'] = 0;
                    $itemObj['origin_shop'] = 0;
                    $itemObj['origin_quest'] = 0;
                    $itemObj['origin_legacy'] = 0;

                    foreach ($usercopies as $copy) {
                        $src = $copy->source ?? '';
                        if ($src == 'map') $itemObj['origin_map']++;
                        elseif ($src == 'shop') $itemObj['origin_shop']++;
                        elseif ($src == 'quest') $itemObj['origin_quest']++;
                        else $itemObj['origin_legacy']++;
                    }
                    if ($itemObj['origin_map'] || $itemObj['origin_shop'] || $itemObj['origin_quest'] || $itemObj['origin_legacy']) {
                        $itemObj['has_origins'] = true;
                    }

                    // Badge Novo
                    $lastview = $this->player->last_inventory_view ?? 0;
                    if ($lastts > $lastview) {
                        $itemObj['badge_new'] = true;
                    }

                    // Badge Arquivado
                    if (!$item->enabled) {
                        $itemObj['badge_archived'] = true;
                        $itemObj['card_class'] .= ' ph-item-archived';
                    }

                    $itemObj['date_str'] = userdate($lastts, get_string('strftimedatefullshort', 'langconfig'));
                }

                // Dados comuns para ordenação
                $itemObj['id'] = $item->id;
                $itemObj['sort_name'] = $sort_name;
                if ($total_count == 0 && $item->secret) {
                    $itemObj['raw_xp'] = -1; 
                } else {
                    $itemObj['raw_xp'] = (int)$item->xp;
                }
                $itemObj['count'] = $total_count;
                $itemObj['timestamp'] = $lastts;
                
                $itemObj['count_infinite'] = $count_infinite;
                $itemObj['count_finite'] = $count_finite;
                $itemObj['has_infinite_copies'] = ($count_infinite > 0);
                $itemObj['has_finite_copies'] = ($count_finite > 0);
                $itemObj['is_infinite_type'] = $isinfinite_config;
                
                $itemObj['is_image_bool'] = $media['is_image'] ? 1 : 0;
                $itemObj['tabindex'] = '0';

                $items_data[] = $itemObj;
            }
        }

        // 3. Ordenação Robusta (Collator)
        // Instancia o Collator fora do loop para performance e evitar erros
        $collator = new \Collator(current_language());
        if (!$collator) {
            // Fallback seguro se intl falhar (raro no Moodle)
            $collator = new \Collator('en_US'); 
        }

        usort($items_data, function($a, $b) use ($current_sort, $collator) {
            switch ($current_sort) {
                case 'name_asc':
                    return $collator->compare($a['sort_name'], $b['sort_name']);
                
                case 'name_desc':
                    return $collator->compare($b['sort_name'], $a['sort_name']);
                
                case 'xp_desc':
                    // Desempate por nome se XP for igual
                    if ($b['raw_xp'] == $a['raw_xp']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $b['raw_xp'] <=> $a['raw_xp'];
                
                case 'xp_asc':
                    if ($a['raw_xp'] == $b['raw_xp']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $a['raw_xp'] <=> $b['raw_xp'];
                
                case 'count_desc':
                    if ($a['count'] == $b['count']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $b['count'] <=> $a['count'];

                case 'count_asc':
                    if ($a['count'] == $b['count']) {
                        return $collator->compare($a['sort_name'], $b['sort_name']);
                    }
                    return $a['count'] <=> $b['count'];
                
                case 'recent':
                    return $b['timestamp'] <=> $a['timestamp'];
                
                case 'acquired':
                    // Quem tem (count > 0) vem antes
                    $hasA = ($a['count'] > 0) ? 1 : 0;
                    $hasB = ($b['count'] > 0) ? 1 : 0;
                    if ($hasA == $hasB) return $collator->compare($a['sort_name'], $b['sort_name']);
                    return $hasB <=> $hasA;
                
                case 'missing':
                    // Quem não tem (count == 0) vem antes
                    $hasA = ($a['count'] > 0) ? 1 : 0;
                    $hasB = ($b['count'] > 0) ? 1 : 0;
                    if ($hasA == $hasB) return $collator->compare($a['sort_name'], $b['sort_name']);
                    return $hasA <=> $hasB;
                
                default:
                    // Padrão: XP Ascendente
                    return $a['raw_xp'] <=> $b['raw_xp'];
            }
        });

        // 4. Prepara dados do Dropdown de Filtro
        // CORREÇÃO: Adiciona 'tab' para garantir que a página recarregue na aba certa
        $url = new moodle_url($PAGE->url); 
        $url->param('tab', 'collection'); 
        
        $options = [
            'xp_asc'     => get_string('sort_xp_asc', 'block_playerhud'),
            'xp_desc'    => get_string('sort_xp_desc', 'block_playerhud'),
            'name_asc'   => get_string('sort_name_asc', 'block_playerhud'),
            'name_desc'  => get_string('sort_name_desc', 'block_playerhud'),
            'count_desc' => get_string('sort_count_desc', 'block_playerhud'),
            'count_asc'  => get_string('sort_count_asc', 'block_playerhud'),
            'recent'     => get_string('sort_recent', 'block_playerhud'),
            'acquired'   => get_string('sort_acquired', 'block_playerhud'),
            'missing'    => get_string('sort_missing', 'block_playerhud')
        ];

        $sort_options = [];
        foreach ($options as $val => $label) {
            $u = new moodle_url($url, ['sort' => $val]);
            $sort_options[] = [
                'value' => $u->out(false),
                'label' => $label,
                'selected' => ($val === $current_sort)
            ];
        }

        return [
            'items' => $items_data,
            'has_items' => !empty($items_data),
            'sort_options' => $sort_options,
            'show_filter' => !empty($items_data)
        ];
    }
}
