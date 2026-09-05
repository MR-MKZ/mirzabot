<?php

if (defined('CRONBOT_BOOTSTRAP')) {
    return;
}
define('CRONBOT_BOOTSTRAP', true);

date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../Marzban.php';
require_once __DIR__ . '/../jdf.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
