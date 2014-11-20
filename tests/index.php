<?php

require_once('../templatte.php');

$template = new Templatte('main');

ob_start();
require('views/home.php');
$content = ob_get_contents();
ob_clean();

$template->bind(array(
    'title'  => 'Templatté test',
    'content' => $content,
));

echo $template;
