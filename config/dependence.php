<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

return [
    // ── 通用工具服务 ──
    \app\common\SnowflakeService::class  => \app\common\SnowflakeService::class,
    \app\common\HashidsService::class    => \app\common\HashidsService::class,
    \app\common\EncryptionService::class => \app\common\EncryptionService::class,
    \app\common\I18n::class              => \app\common\I18n::class,

    // ── 业务服务层 ──
    \app\service\finance\FinanceService::class           => \app\service\finance\FinanceService::class,
    \app\service\inventory\InventoryService::class       => \app\service\inventory\InventoryService::class,
    \app\service\notification\NotificationService::class => \app\service\notification\NotificationService::class,
];
