<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * 数据库敏感字段加解密配置
 * 用于数据持久层的字段加解密，与接口传输层加密（encryption）是独立的密钥体系
 * @link https://github.com/erikwang2013/encryptable
 */
$cipher = getenv('ENCRYPTABLE_CIPHER') ?: 'AES-256-CBC';

return [
    // 数据库加密密钥，生产环境请使用 32 字节随机字符串并通过环境变量注入
    // 强校验：缺失/为空/弱占位值（change-me/CHANGE_ME/xxx）一律拒绝启动；
    // 长度也按算法校验（env_crypto_key）——插件只在首次解密时才查长度，晚一步就是用户侧 500
    'key' => env_crypto_key('ENCRYPTABLE_KEY', $cipher),

    // 加密算法，推荐 AES-256-CBC
    'cipher' => $cipher,
];
