<?php
date_default_timezone_set('UTC');
include __DIR__.'/../../../../init_autoloader.php';

$config = require 'messagesender.config.php';
$app = new Rindow\Container\ModuleManager($config);
$app->run();
