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

// NOVOS PARÂMETROS (Configuração personalizada do Drop)
$droploc    = optional_param('drop_location', '', PARAM_TEXT);
$dropmax    = optional_param('drop_max', 0, PARAM_INT);
$droptime   = optional_param('drop_time', 0, PARAM_INT); // Vem em minutos do JS

// 2. Security.
require_login($courseid);
require_sesskey();
$context = context_block::instance($instanceid);
require_capability('block/playerhud:manage', $context);

header('Content-Type: application/json; charset=utf-8');

try {
    // 3. Delegate Logic to Class.
    // This respects Moodle standards: Logic is in classes/ai/generator.php
    $generator = new \block_playerhud\ai\generator($instanceid);

    // Empacota as configs extras num array para passar limpo
    $extraoptions = [
        'drop_location' => $droploc,
        'drop_max' => $dropmax,
        'drop_time' => $droptime
    ];

    // Chamada atualizada com o novo argumento $extraoptions
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
