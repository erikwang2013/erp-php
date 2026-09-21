<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 数据库敏感字段加解密插件配置
 *
 * Webman—plugin 统一布局: 顶层 key/cipher/previous_keys
 * 模型中使用 cast: '字段名' => \Erikwang2013\Encryptable\Encryptable::class
 *
 * @see https://github.com/erikwang2013/encryptable
 */
$cipher = getenv('ENCRYPTABLE_CIPHER') ?: 'AES-256-CBC';

return [
    // 数据库加密密钥，生产环境请使用 32 字节随机字符串并通过环境变量注入
    // 注意: 与 API 传输加密密钥 ENCRYPTION_KEY 独立，两者不可共用
    // 长度按算法校验（AES-256 → 32 字节）：插件 Encrypter 只在首次解密时才查长度，
    // 缺这一道校验的应用能正常启动、直到用户点开一个会解密字段的页面才 500
    'key' => env_crypto_key('ENCRYPTABLE_KEY', $cipher),

    // 加密算法。默认与 .env.example / 安装向导 / 文档统一为 AES-256-CBC（32 字节密钥）；
    // 也支持 aes-128-ecb（16 字节）、sm4-ecb —— 改算法须同时换密钥长度，并注意
    // 旧密文按旧算法解密（算法不对时插件非严格模式会把密文原样返回，读出来是乱码）
    'cipher' => $cipher,

    // 历史密钥列表（用于密钥轮换时的数据迁移），逗号分隔
    'previous_keys' => Erikwang2013\Encryptable\Support\PreviousKeysParser::parse(getenv('ENCRYPTION_PREVIOUS_KEYS') ?: ''),
];
