<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

/**
 * 数电票开票/红冲日志 — P2-2 F5
 *
 * 只追加不改写：表无 updated_at 列（$timestamps=false，created_at 走 DB 默认值）；
 * request/response JSON 按数组读写（$casts 数组，序列化交回 Eloquent）。
 */
class TaxIssueLog extends Model
{
    public $timestamps = false;

    protected $table = 'erp_tax_issue_log';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at'];
    protected $casts = [
        'request' => 'array',
        'response' => 'array',
    ];
}
