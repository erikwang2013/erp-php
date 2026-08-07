#!/usr/bin/env php
<?php
chdir(__DIR__);
require_once __DIR__ . '/vendor/autoload.php';
// env_required() 等全局函数需在配置加载(loadAllConfig)之前就绪
require_once __DIR__ . '/app/functions.php';
support\App::run();
