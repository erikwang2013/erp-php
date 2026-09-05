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
}
