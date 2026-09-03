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
 * 表名约定：模型 $table 与所有 DB::table/原生 SQL 均硬编码 erp_ 前缀，
 * config/database.php 不再声明连接 prefix（曾致双前缀遗留，erp_erp_*）。
 * 后续新增连接（如多组织账套）直接拷贝连接数组即可，天然安全。
 */
class LaravelDb implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $config = Config::get('database', []);
        $connection = $config['connections'][$config['default']] ?? [];

        $capsule = new Capsule();
        $capsule->addConnection($connection);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
