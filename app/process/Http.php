<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\process;

use app\common\SnowflakeService;
use Webman\App;

class Http extends App
{
    /**
     * HTTP 进程启动：先执行父类初始化，再注册 Snowflake 节点
     */
    public function onWorkerStart($worker): void
    {
        parent::onWorkerStart($worker);
        SnowflakeService::registerProcessNode();
    }
}
