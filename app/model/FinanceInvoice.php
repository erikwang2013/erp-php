<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 发票(应收/应付) — P0
 *
 * 边界：税务票据追踪单据，不新增 ARAP 分录、不联动收付款/核销/结算，
 * 金额列不加 float cast，字符串直出 JSON（DECIMAL 由 bcmath 计算）。
 */
class FinanceInvoice extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'finance_invoice';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function items(): HasMany
    {
        return $this->hasMany(FinanceInvoiceItem::class, 'invoice_id');
    }
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['invoice_no', 'electronic_no', 'issue_status', 'type', 'customer_id', 'supplier_id', 'biz_type', 'source_id', 'invoice_date', 'untaxed_amount', 'tax_amount', 'amount', 'currency', 'status', 'void_reason', 'audited_by', 'audited_at', 'remark'];
}
