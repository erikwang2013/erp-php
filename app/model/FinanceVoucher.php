<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * @property int|null $ledger_id 账套维度（F1 加列；NULL = 旧数据历史全局账）
 */
class FinanceVoucher extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_voucher';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
}
