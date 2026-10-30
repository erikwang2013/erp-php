<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\common;

use Erikwang2013\Encryptable\Encryption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * 写入一律加密、读取兼容历史明文的 Eloquent cast。
 *
 * 与标准 Encryptable 的区别只在 get()：先显式 isEncrypted() 判断，密文才解密；
 * 历史明文原样返回，避免把明文当密文送进 openssl。数据迁移完成后可换回 Encryptable。
 */
class EncryptableOrLegacy implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): mixed
    {
        if (!is_string($value) || !Encryption::isEncrypted($value)) {
            return $value;
        }

        return Encryption::php()->decrypt($value);
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        return Encryption::php()->encrypt($value);
    }
}
