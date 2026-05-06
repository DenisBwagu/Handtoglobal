<?php
require_once __DIR__ . '/config.php';

$target = 'task_history.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

redirect($target);
