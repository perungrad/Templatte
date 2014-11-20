<?php

require_once('../templatte.php');

$template = new Templatte('main');

$template->bind(array(
    'title'  => 'Templatté test',
    'content' => 'You are home! :)'
));

echo $template;
