<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * P0 OpenAPI 开放平台配置
 *
 * 认证（app/middleware/OpenApiAuth.php，请求头方式）：
 *   X-API-Key     = 应用 app_key（公开标识）
 *   X-Timestamp   = 请求发起 Unix 秒级时间戳
 *   X-Signature   = HMAC-SHA256(secret, 签名串) 十六进制小写
 *   签名串 = {X-Timestamp}{HTTP方法大写}{请求路径(含前导/)}{请求体原文}，无分隔符直接拼接；
 *   校验用 app_secret 为数据库中的解密明文（模型 Encryptable 自动解密），hash_equals 比较。
 *   时间戳容差 ±timestamp_tolerance 秒（防重放）。
 * 限流：按 app_key 的 Redis 原子滑动窗口计数（与 app/middleware/RateLimit.php 同款 Lua），
 *   超限返回 429 并携带 X-RateLimit-* / Retry-After 头；Redis 故障时 fail-open 跳过限流并记录告警日志。
 * 授权范围：app.scopes 为允许的路径前缀数组（NULL/空数组=不限制），请求路径前缀命中任一 scope 才放行。
 * Webhook（app/service/notification/WebhookService.php）：
 *   同步 curl 投递 + erp_webhook_delivery_log 账本落库 + 指数退避重试
 *   （每次 dispatch 先顺带补偿到期失败的订阅，达 max_attempts 后放弃）。
 */

return [
    // 请求时间戳容差（秒）
    'timestamp_tolerance' => 300,

    'rate_limit' => [
        'limit' => 60, // 每窗口每 app_key 允许请求数
        'window' => 60, // 滑动窗口（秒），Redis key 过期 window + 10 秒
    ],

    'webhook' => [
        'max_attempts' => 5, // 单次事件最大投递尝试次数（含首次）
        'retry_base_seconds' => 60, // 指数退避基数：delay = base * 2^(attempts-1)，封顶 max_backoff_seconds
        'max_backoff_seconds' => 3600,
        'http_timeout' => 5, // 投递 HTTP 超时（秒）
        'sweep_limit' => 20, // 每次 dispatch 顺带补偿的到期失败记录上限
        'user_agent' => 'erp-webhook/1.0',
    ],
];
