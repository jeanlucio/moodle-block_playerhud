<?php
namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;

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
        global $DB, $CFG;
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

        // 1. Fetch Inventory & Items
        $rawinventory = $DB->get_records('block_playerhud_inventory', ['userid' => $this->player->userid]);
        $inventorybyitem = [];
        if ($rawinventory) {
            foreach ($rawinventory as $inv) {
                $inventorybyitem[$inv->itemid][] = $inv;
            }
        }

        $allitems = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $this->instanceid], 'xp ASC');
        
        // Check Class Visibility
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
                $count = count($usercopies);

                // Visibility Rules
                if ($count == 0) {
                    if (!$item->enabled) continue;
                    if (!block_playerhud_is_visible_for_class($item->required_class_id, $myclassid)) continue;
                }

            $media = \block_playerhud\utils::get_item_display_data($item, $context);
                
                // Infinite Check
                $isinfinite = $DB->record_exists('block_playerhud_drops', ['itemid' => $item->id, 'maxusage' => 0]);

                // CORREÇÃO 1: Preparar o payload para o JS (data-image).
                // Se for imagem, usa URL. Se for emoji, usa o conteúdo limpo (sem tags HTML).
                $js_payload = $media['is_image'] ? $media['url'] : strip_tags($media['content']);

                $itemObj = [
                    'id' => $item->id,
                    'count' => $count,
                    'is_infinite' => $isinfinite,
                    'is_image' => $media['is_image'],
                    'image_url' => $media['is_image'] ? $media['url'] : '',
                    'image_content' => $media['is_image'] ? '' : $media['content'],
                    
                    // Dados para o Modal (JS)
                    'data_image_payload' => $js_payload, 
                    'is_image_bool' => $media['is_image'] ? 1 : 0,
                    
                    // CORREÇÃO 2: Acessibilidade. Sempre navegável ('0'), mesmo se não coletado.
                    'tabindex' => '0', 
                    'cursor' => 'pointer', // Sempre clicável para ver detalhes
                    
                    'badge_new' => false,
                    'badge_archived' => false,
                    'has_origins' => false,
                    'origin_map' => 0,
                    'origin_shop' => 0,
                    'origin_quest' => 0,
                    'origin_legacy' => 0,
                    'date_str' => ''
                ];

                if ($count == 0) {
                    // MISSING ITEM
                    $itemObj['card_class'] = 'ph-missing';
                    
                    if ($item->secret) {
                        $itemObj['name'] = get_string('secret_name', 'block_playerhud');
                        $itemObj['xp_text'] = "???";
                        $itemObj['description'] = get_string('secret_desc', 'block_playerhud');
                        
                        // Override para segredo
                        $itemObj['is_image'] = false;
                        $itemObj['image_content'] = '❓';
                        $itemObj['data_image_payload'] = '❓'; // Modal mostra interrogação
                    } else {
                        $itemObj['name'] = format_string($item->name);
                        $itemObj['xp_text'] = "+{$item->xp} XP";
                        $itemObj['description'] = format_text($item->description, FORMAT_HTML);
                    }
                    } else {
                    // OWNED ITEM
                    $itemObj['card_class'] = 'ph-owned';
                    $itemObj['name'] = format_string($item->name);
                    $itemObj['xp_text'] = "+{$item->xp} XP";
                    $itemObj['description'] = format_text($item->description, FORMAT_HTML);

                    // Stats logic
                    $lastts = 0;
                    foreach ($usercopies as $copy) {
                        if ($copy->timecreated > $lastts) $lastts = $copy->timecreated;
                        $src = $copy->source ?? '';
                        if ($src == 'map') $itemObj['origin_map']++;
                        elseif ($src == 'shop') $itemObj['origin_shop']++;
                        elseif ($src == 'quest') $itemObj['origin_quest']++;
                        else $itemObj['origin_legacy']++;
                    }
                    
                    if ($itemObj['origin_map'] || $itemObj['origin_shop'] || $itemObj['origin_quest'] || $itemObj['origin_legacy']) {
                        $itemObj['has_origins'] = true;
                    }

                    // New Badge
                    $lastview = $this->player->last_inventory_view ?? 0;
                    if ($lastts > $lastview) {
                        $itemObj['badge_new'] = true;
                    }

                    // Archived Badge
                    if (!$item->enabled) {
                        $itemObj['badge_archived'] = true;
                        $itemObj['card_class'] .= ' ph-item-archived'; // Add opacity in CSS
                    }

                    if ($lastts > 0) {
                        $itemObj['date_str'] = userdate($lastts, get_string('strftimedatefullshort', 'langconfig'));
                    }
                }

                $items_data[] = $itemObj;
            }
        }

        return [
            'items' => $items_data,
            'has_items' => !empty($items_data)
        ];
    }
}
