<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Collection tab view for PlayerHUD Block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\view;

use renderable;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class tab_collection implements renderable {

    protected $config;
    protected $player;
    protected $instanceid;

    /**
     * Constructor.
     *
     * @param object $config Block instance config.
     * @param object $player Player record.
     * @param int $instanceid Block instance ID.
     */
    public function __construct($config, $player, $instanceid) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
    }

    /**
     * Render the tab.
     *
     * @return string
     */
    public function display() {
        global $DB, $CFG;
        
        // Garante que a lib está carregada para usar as funções auxiliares
        require_once($CFG->dirroot . '/blocks/playerhud/lib.php');

        // 1. Buscar Inventário do Usuário
        $rawinventory = $DB->get_records('block_playerhud_inventory', [
            'userid' => $this->player->userid
        ]);
        
        $inventorybyitem = [];
        if ($rawinventory) {
            foreach ($rawinventory as $inv) {
                // Filtra apenas se o item pertencer a este bloco (verificação via join seria melhor, mas array filter serve)
                // Vamos assumir que a renderização dos itens abaixo já filtra pelo instanceid
                $inventorybyitem[$inv->itemid][] = $inv;
            }
        }

        // 2. Buscar Todos os Itens do Bloco
        $allitems = $DB->get_records('block_playerhud_items', [
            'blockinstanceid' => $this->instanceid, 
            'enabled' => 1
        ], 'xp ASC');

        // 3. Buscar Classe do Jogador (para regras de visibilidade)
        // Nota: Se a tabela de progresso RPG ainda não foi migrada para bloco, ajuste o nome da tabela aqui.
        // Assumindo estrutura nova: block_playerhud_rpg_progress? Ou usando tabela antiga se compartilhado?
        // Vou assumir que o RPG também é por bloco agora, mas se não houver tabela, define classe 0.
        $myclassid = 0; 
        if ($DB->get_manager()->table_exists('block_playerhud_rpg_progress')) {
             $prog = $DB->get_record('block_playerhud_rpg_progress', [
                'userid' => $this->player->userid, 
                'blockinstanceid' => $this->instanceid
            ]);
            if ($prog) {
                $myclassid = $prog->classid;
            }
        }

        $context = \context_block::instance($this->instanceid);
        $cardshtml = '';
        $hasitems = false;

        if ($allitems) {
            foreach ($allitems as $item) {
                $usercopies = isset($inventorybyitem[$item->id]) ? $inventorybyitem[$item->id] : [];

                // REGRA DE VISIBILIDADE:
                // Se o aluno NÃO tem o item E o item é restrito a outra classe, esconde totalmente.
                if (empty($usercopies)) {
                    if (!block_playerhud_is_visible_for_class($item->required_class_id, $myclassid)) {
                        continue;
                    }
                }

                $mediadata = \block_playerhud\utils::get_item_display_data($item, $context);
                $deschtml = !empty($item->description) ? format_text($item->description, FORMAT_HTML) : "";

                // Verifica se é infinito (baseado nos drops associados)
                $isinfinite = false;
                $dropscheck = $DB->get_records('block_playerhud_drops', ['itemid' => $item->id]);
                foreach ($dropscheck as $d) {
                    if ($d->maxusage == 0) {
                        $isinfinite = true;
                    }
                }

                if (empty($usercopies)) {
                    // --- ITEM FALTANTE (Missing) ---
                    // Se não tiver drops configurados e não tiver o item, talvez nem deva aparecer (opcional)
                    if (!$dropscheck && !$item->secret) {
                        // continue; // Descomente se quiser esconder itens impossíveis de pegar
                    }

                    // Se for secreto e não tem, mostra "???"
                    $name = $item->secret ? get_string('secret_name', 'block_playerhud') : format_string($item->name);
                    $xplabel = $item->secret ? get_string('secret_name', 'block_playerhud') : "+{$item->xp} XP";
                    $displaydesc = $item->secret ? get_string('secret_help', 'block_playerhud') : $deschtml;

                    // Se for secreto, esconde a imagem real
                    if ($item->secret) {
                        $mediadata['is_image'] = false;
                        $mediadata['content'] = '<span aria-hidden="true">❓</span>';
                    }

                    $cardshtml .= $this->render_card(
                        $item, $name, $xplabel, "ph-missing", "cursor: help;", "ph-item-trigger",
                        0, "", "", $displaydesc, $mediadata, 0, 0, 0, 0, 0, $isinfinite
                    );
                    $hasitems = true;

                } else {
                    // --- ITEM OBTIDO (Owned) ---
                    $stacktotal = count($usercopies);
                    $lastdatets = 0;
                    $countmap = 0; $countshop = 0; $countquest = 0; $countlegacy = 0;

                    foreach ($usercopies as $copy) {
                        if ($copy->timecreated > $lastdatets) $lastdatets = $copy->timecreated;
                        
                        $src = $copy->source ?? '';
                        if ($src == 'map') $countmap++;
                        else if ($src == 'shop') $countshop++;
                        else if ($src == 'quest') $countquest++;
                        else $countlegacy++; // Drop legado ou inserção manual
                    }

                    // Badge "NOVO!"
                    $lastview = $this->player->last_inventory_view ?? 0;
                    $isnew = ($lastdatets > $lastview);
                    $newbadge = $isnew ? '<span class="ph-new-badge">' . get_string('new_item_badge', 'block_playerhud') . '</span>' : '';
                    
                    $datestr = ($lastdatets > 0) ? userdate($lastdatets, get_string('strftimedatefullshort', 'langconfig')) : "";

                    $cardshtml .= $this->render_card(
                        $item, format_string($item->name), "+{$item->xp} XP", "ph-owned", "cursor: pointer;", "ph-item-trigger",
                        $stacktotal, $newbadge, $datestr, $deschtml, $mediadata,
                        $countmap, $countshop, $countquest, $countlegacy, $lastdatets, $isinfinite
                    );
                    $hasitems = true;
                }
            }
        }

        if (!$hasitems) {
            return '<div class="alert alert-light border text-center py-5">
                        <div style="font-size: 3rem; opacity: 0.3;" aria-hidden="true">📭</div>
                        <p class="mb-0 text-muted">' . get_string('empty', 'block_playerhud') . '</p>
                    </div>';
        }

        return '<div class="playerhud-inventory-grid animate__animated animate__fadeIn">' . $cardshtml . '</div>';
    }

    /**
     * Helper para renderizar o ícone (Imagem ou Emoji).
     */
    private function render_icon($media) {
        if ($media['is_image']) {
            return '<img src="' . $media['url'] . '" class="ph-modal-img" alt="" style="max-height: 60px; width: auto;">';
        }
        // Emoji (Font size grande)
        return '<div class="ph-modal-emoji" style="font-size: 3rem; line-height:1;">' . $media['content'] . '</div>';
    }

    /**
     * Helper gigante para montar o HTML do card.
     * Mantém o mesmo HTML/CSS do sistema legado para compatibilidade visual.
     */
    private function render_card(
        $item, $name, $xptext, $class, $style, $trigger, $count, $badge, $datestr, 
        $desc, $media, $countmap, $countshop, $countquest, $countlegacy, $datets, $isinfinite
    ) {
        $badgecount = '';
        if ($count > 0) {
            $badgecount = '<div class="ph-card-count-badge" style="position: absolute; top: -8px; right: -8px; 
                background-color: #343a40; color: #fff; border-radius: 50%; width: 24px; height: 24px; 
                font-size: 0.75rem; font-weight: bold; display: flex; align-items: center; justify-content: center; 
                border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2); z-index: 5;">x' . $count . '</div>';
        }

        $infiniteicon = '';
        if ($isinfinite) {
            $title = get_string('infinite_item_title', 'block_playerhud');
            $infiniteicon = '<div class="ph-infinite-badge" style="position: absolute; top: 5px; left: 5px; 
                color: #17a2b8; font-size: 0.9rem; z-index: 4; opacity: 0.8;" title="' . $title . '">
                <i class="fa fa-infinity"></i></div>';
        }

        $originshtml = '';
        if ($count > 0) {
            $originshtml .= '<div class="ph-card-origins mt-1" style="font-size: 0.75rem; color: #6c757d; 
                background: #f8f9fa; padding: 2px 8px; border-radius: 10px; display: inline-block; border: 1px solid #e9ecef;">';
            
            if ($countmap > 0) $originshtml .= '<span class="me-2" title="' . get_string('source_map', 'block_playerhud') . '"><i class="fa fa-map-o text-info"></i> ' . $countmap . '</span>';
            if ($countshop > 0) $originshtml .= '<span class="me-2" title="' . get_string('source_shop', 'block_playerhud') . '"><i class="fa fa-shopping-cart text-success"></i> ' . $countshop . '</span>';
            if ($countquest > 0) $originshtml .= '<span class="me-2" title="' . get_string('source_quest', 'block_playerhud') . '"><i class="fa fa-scroll" style="color: #856404;"></i> ' . $countquest . '</span>';
            if ($countlegacy > 0) $originshtml .= '<span title="' . get_string('source_special', 'block_playerhud') . '"><i class="fa fa-star text-warning"></i> ' . $countlegacy . '</span>';
            
            $originshtml .= '</div>';
        }

        $footerdate = '';
        if ($datestr) {
            $footerdate = '<div class="ph-card-date" style="font-size: 0.65rem; color: #adb5bd; margin-top: 5px; 
                border-top: 1px solid #f1f1f1; width: 100%; text-align: center; padding-top: 3px;">' . $datestr . '</div>';
        }

        // Adicionamos role="button" e tabindex="0" para acessibilidade
        // Se o item não for clicável (ex: missing), tabindex pode ser -1, mas vamos manter simples por enquanto.
        $tabindex = ($count > 0) ? '0' : '-1'; 

        return '
        <div class="playerhud-item-card card ' . $class . ' ' . $trigger . '" 
             style="' . $style . ' width: 100%; position: relative; overflow: visible;"
             role="button" 
             tabindex="' . $tabindex . '"
             data-id="' . $item->id . '"
             data-name="' . s($name) . '"
             data-xp="' . $xptext . '"
             data-count="' . $count . '"
             data-date="' . $datestr . '"
             data-image="' . ($media['is_image'] ? $media['url'] : strip_tags($media['content'])) . '"
             data-isimage="' . ($media['is_image'] ? 1 : 0) . '">

             ' . $badge . '
             ' . $badgecount . '
             ' . $infiniteicon . '

             <div class="d-none ph-item-description-content">' . $desc . '</div>

            <div class="card-body p-2 d-flex flex-column align-items-center justify-content-between">
                <div class="my-3 text-center" style="height: 80px; display:flex; align-items:center; justify-content:center;">
                     ' . $this->render_icon($media) . '
                </div>

                <div class="text-center w-100 mb-3">
                    <div class="fw-bold text-truncate mb-1" style="font-size: 1rem;">' . $name . '</div>
                    <span class="badge bg-primary ph-xp-badge mb-2">' . $xptext . '</span>
                    <br>' . $originshtml . '
                </div>

                ' . $footerdate . '
            </div>
        </div>';
    }
}
