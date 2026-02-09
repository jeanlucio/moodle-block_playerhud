<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_playerhud_generate_ai_content' => [
        'classname'   => 'block_playerhud\external',
        'methodname'  => 'generate_ai_content',
        'description' => 'Generates game items using AI',
        'type'        => 'write',
        'ajax'        => true,  // Permite chamada via AJAX web
        'loginrequired' => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE], // Permite uso no App Mobile
    ],
];