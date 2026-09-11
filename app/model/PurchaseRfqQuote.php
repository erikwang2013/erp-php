<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class PurchaseRfqQuote extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'purchase_rfq_quote';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'awarded', 'amount', 'created_at', 'updated_at', 'deleted_at'];

    public function items()
    {
        return $this->hasMany(PurchaseRfqQuoteItem::class, 'quote_id');
    }
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['rfq_id', 'supplier_id', 'amount', 'quote_date', 'valid_until', 'awarded', 'status', 'remark'];
}
