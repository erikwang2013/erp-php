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
    \app\common\SnowflakeService::class => \app\common\SnowflakeService::class,
    \app\common\HashidsService::class => \app\common\HashidsService::class,
    \app\common\EncryptionService::class => \app\common\EncryptionService::class,
    \app\common\I18n::class => \app\common\I18n::class,

    // ── 业务服务层 ──
    \app\service\finance\FinanceService::class => \app\service\finance\FinanceService::class,
    \app\service\finance\InvoiceService::class => \app\service\finance\InvoiceService::class,
    \app\service\inventory\InventoryService::class => \app\service\inventory\InventoryService::class,
    \app\service\notification\NotificationService::class => \app\service\notification\NotificationService::class,

    // ── P2-F2 服务层轻量提取新增（crm/hr/manufacturing/product 薄服务层）──
    // 注意：本文件为 dead config —— 全项目没有任何 addDefinitions()/config('dependence')
    // 加载点（见调研结论 P1），以下注册仅为文档性声明。运行时容器解析依赖
    // Webman\Container::get() 的 class_exists 回退：类存在时直接 new $name()，
    // 因此上述 Service 均保持无参构造，Container::get(XxxService::class) 可直接使用。
    \app\service\crm\CrmService::class => \app\service\crm\CrmService::class,
    \app\service\hr\HrService::class => \app\service\hr\HrService::class,
    \app\service\manufacturing\ManufacturingService::class => \app\service\manufacturing\ManufacturingService::class,
    \app\service\product\ProductService::class => \app\service\product\ProductService::class,
];
