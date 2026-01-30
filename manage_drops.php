<?php
require_once('../../config.php');

// O Controller lida com a lógica de listar e deletar
$controller = new \block_playerhud\controller\drops();
echo $controller->view_manage_page();
