<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use app\common\EncryptableOrLegacy;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceBankAccount extends Model
{
    use Searchable;
    protected $table = 'finance_bank_account';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['name', 'account_number', 'bank_name', 'balance', 'status'];
    // account_number 加密存储；EncryptableOrLegacy 兼容 demo/历史明文，读取时自动解密
    protected $casts = ['account_number' => EncryptableOrLegacy::class];

    /**
     * 输出脱敏账号：仅保留后 4 位，长度不足 4 位时全部掩码。
     */
    public static function maskAccountNumber(?string $accountNumber): string
    {
        $value = (string) $accountNumber;
        $length = mb_strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . mb_substr($value, -4);
    }

    /**
     * 前端回存的账号是否为本接口下发的掩码（不能把掩码当新账号覆盖入库）。
     */
    public static function isMaskedAccountNumber(?string $value): bool
    {
        return is_string($value) && preg_match('/^\*+[^*]{0,4}$/', $value) === 1;
    }
}
