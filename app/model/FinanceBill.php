<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
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
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_bill';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['bill_no', 'type', 'direction', 'drawer', 'payee', 'acceptor', 'endorsee', 'issue_date', 'due_date', 'amount', 'discount_fee', 'bank_account_id', 'status', 'source_type', 'source_id', 'endorsed_at', 'discounted_at', 'collected_at', 'cashed_at', 'returned_at', 'remark'];
}
