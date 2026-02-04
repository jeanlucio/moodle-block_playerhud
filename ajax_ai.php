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

// 2. Security.
require_login($courseid);
require_sesskey();
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    // 3. CÁLCULO DE BALANCEAMENTO (Game Health Check)
    // Antes de chamar a IA, vamos entender o estado atual do jogo.
    
    // A. Carrega Configurações
    $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
    $config = unserialize(base64_decode($bi->configdata));
    if (!$config) $config = new \stdClass();

    $xp_per_level = isset($config->config_xp_per_level) ? (int)$config->config_xp_per_level : 100;
    $max_levels   = isset($config->config_max_levels) ? (int)$config->config_max_levels : 20;
    $xp_ceiling   = $xp_per_level * $max_levels; // Meta total do jogo

    // B. Calcula XP Existente
    $current_total_xp = 0;
    $items = $DB->get_records('block_playerhud_items', ['blockinstanceid' => $instanceid, 'enabled' => 1]);
    if ($items) {
        foreach ($items as $it) {
            $drops = $DB->get_records('block_playerhud_drops', ['itemid' => $it->id]);
            if ($drops) {
                foreach ($drops as $d) {
                    $mult = ($d->maxusage > 0) ? $d->maxusage : 1; 
                    $current_total_xp += ($it->xp * $mult);
                }
            } else {
                $current_total_xp += $it->xp;
            }
        }
    }

    // C. Define Contexto para a IA
    $balance_context = [
        'current_xp' => $current_total_xp,
        'target_xp'  => $xp_ceiling,
        'gap'        => $xp_ceiling - $current_total_xp
    ];

    // 4. Delegate Logic to Class.
    $generator = new \block_playerhud\ai\generator($instanceid);

    $extraoptions = [
        'drop_location' => $droploc,
        'drop_max' => $dropmax,
        'drop_time' => $droptime,
        'balance_context' => $balance_context // Passamos o contexto aqui
    ];

    $result = $generator->generate($mode, $theme, $xp, $createdrop, $extraoptions);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
die();
