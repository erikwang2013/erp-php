<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class PurchaseReceiveItem extends Model
{
    use Searchable;
    protected $table = 'purchase_receive_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['receive_id', 'order_item_id', 'product_id', 'sku_id', 'location_id', 'batch_code', 'quantity', 'price', 'amount', 'unit'];
}
