<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace support\bootstrap;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Webman\Bootstrap;
use Webman\Config;
use Workerman\Worker;

/**
 * Illuminate Database Capsule 引导（webman 标准模板缺失，本仓库补回）。
 * 模型表名已硬编码 erp_ 前缀，addConnection 前必须移除配置中的 prefix，
 * 否则 Eloquent 会拼出 erp_erp_* 双前缀表名。
 */
class LaravelDb implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $config = Config::get('database', []);
        $connection = $config['connections'][$config['default']] ?? [];
        unset($connection['prefix']);

        $capsule = new Capsule();
        $capsule->addConnection($connection);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
