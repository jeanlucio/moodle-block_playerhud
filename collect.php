<?php
/**
 * Entry point for collection (MVC Style).
 */
require_once('../../config.php');

// O Controller cuida de segurança, parâmetros e lógica.
$controller = new \block_playerhud\controller\collect();
$controller->execute();
