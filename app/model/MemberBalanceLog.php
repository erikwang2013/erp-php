<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员储值流水 — P2-3 C1（只追加；余额 = Σamount 带符号求和）
 */
class MemberBalanceLog extends Model
{
    use Searchable;
    public $timestamps = false;
    protected $table = 'member_balance_log';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at'];
}
