<?php
/**
 * AJAX Handler Entry Point.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

// 1. Parameters.
$instanceid = required_param('instanceid', PARAM_INT);
$courseid   = required_param('id', PARAM_INT);
$theme      = required_param('theme', PARAM_TEXT);
$mode       = optional_param('mode', 'item', PARAM_ALPHA);
$xp         = optional_param('xp', 0, PARAM_INT);
$createdrop = optional_param('create_drop', 0, PARAM_BOOL);

$droploc    = optional_param('drop_location', '', PARAM_TEXT);
$dropmax    = optional_param('drop_max', 0, PARAM_INT);
$droptime   = optional_param('drop_time', 0, PARAM_INT);
$amount     = optional_param('amount', 1, PARAM_INT);

// 2. Security.
require_login($courseid);
require_sesskey();
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    // 3. Balanceamento
    $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
    $config = unserialize(base64_decode($bi->configdata));
    if (!$config) $config = new \stdClass();

    // CORREÇÃO: Removemos o prefixo 'config_' pois o Moodle salva sem ele
    $xp_per_level = isset($config->xp_per_level) ? (int)$config->xp_per_level : 100;
    $max_levels   = isset($config->max_levels) ? (int)$config->max_levels : 20;
    
    $xp_ceiling   = $xp_per_level * $max_levels;

    $current_total_xp = 0;
    $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
    if ($items) {
        foreach ($items as $it) {
            $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $it->id]);
            if ($drops) {
                foreach ($drops as $d) {
                    // Ignora drops infinitos na conta do saldo
                    if ($d->maxusage > 0) {
                        $current_total_xp += ($it->xp * $d->maxusage);
                    }
                }
            } else {
                $current_total_xp += $it->xp;
            }
        }
    }

    $balance_context = [
        'current_xp' => $current_total_xp,
        'target_xp'  => $xp_ceiling,
        'gap'        => $xp_ceiling - $current_total_xp,
        'qty'        => $amount
    ];

    // 4. Delegate Logic
    $generator = new \block_playerhud\ai\generator($instanceid);

    $extraoptions = [
        'drop_location' => $droploc,
        'drop_max' => $dropmax,
        'drop_time' => $droptime,
        'balance_context' => $balance_context
    ];

    $result = $generator->generate($mode, $theme, $xp, $createdrop, $extraoptions, $amount);

    echo json_encode($result);

} catch (\Throwable $e) { 
    if (ob_get_length()) ob_clean();
    
    // Log detalhado para o admin
    debugging('PlayerHUD AI Error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    
    http_response_code(200); 
    echo json_encode([
        'success' => false,
        // Mensagem limpa para o usuário (sem link de arquivo)
        'message' => $e->getMessage()
    ]);
}
die();
