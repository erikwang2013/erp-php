<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class SalesDeliveryItem extends Model
{
    use Searchable;
    protected $table = 'sales_delivery_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['delivery_id', 'order_item_id', 'product_id', 'sku_id', 'location_id', 'batch_code', 'quantity', 'price', 'amount', 'unit'];
    protected $casts = ['delivery_id' => 'integer', 'order_item_id' => 'integer', 'product_id' => 'integer', 'sku_id' => 'integer', 'location_id' => 'integer', 'quantity' => 'float', 'price' => 'float', 'amount' => 'float'];
}
