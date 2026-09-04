<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

/**
 * 承兑汇票票据台账 — P2 F6
 *
 * 票据为资产追踪单据：不新增 ARAP 分录、不联动收付款/核销/结算，
 * 金额列不加 float cast，字符串直出 JSON（DECIMAL 由 bcmath 计算）。
 */
class FinanceBill extends Model
{
    use SoftDeletes;

    protected $table = 'erp_finance_bill';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
}
