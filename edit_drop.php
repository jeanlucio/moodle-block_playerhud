<?php
require_once('../../config.php');

$controller = new \block_playerhud\controller\drops();
echo $controller->handle_edit_form();
