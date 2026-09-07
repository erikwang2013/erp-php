<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 进项发票池登记行 — P2-2 F5
 *
 * 验真/抵扣状态机由 TaxInvoicePoolService 推进（0待验真→1/2、0→1勾选→2抵扣），
 * 本模型只承载状态不承载流转。金额列不加 float cast，字符串直出 JSON
 * （DECIMAL 由 bcmath 计算，与 FinanceBill 同款注释约定）。
 */
class TaxInputInvoice extends Model
{
    use Searchable;
    protected $table = 'tax_input_invoice';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['invoice_code', 'invoice_no', 'issue_date', 'seller_name', 'seller_tax_no', 'buyer_name', 'buyer_tax_no', 'amount', 'untaxed_amount', 'tax_amount', 'verify_status', 'verify_at', 'deduct_status', 'deduct_period', 'source', 'remark'];
}
