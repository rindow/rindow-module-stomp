<?php
date_default_timezone_set('UTC');
include 'init_autoloader.php';
$loader->add('RindowTest\\Stomp\\', __DIR__);

define('RINDOW_STOMP_USER','admin');
define('RINDOW_STOMP_PASSWORD','password');

if(!class_exists('PHPUnit\Framework\TestCase')) {
    include __DIR__.'/travis/patch55.php';
}
