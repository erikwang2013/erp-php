<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsRmaItem extends Model
{
    protected $table = 'erik_oms_rma_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['rma_id', 'order_item_id', 'product_id', 'sku_id', 'quantity', 'price', 'amount', 'unit'];
    protected $casts = [
        'rma_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'quantity' => 'float',
        'price' => 'float',
        'amount' => 'float'
    ];
}
