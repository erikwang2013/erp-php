<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class OmsFulfillmentItem extends Model
{
    use Searchable;
    protected $table = 'oms_fulfillment_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['fulfillment_id', 'order_item_id', 'product_id', 'sku_id', 'allocated_quantity', 'picked_quantity', 'packed_quantity', 'shipped_quantity'];
    protected $casts = [
        'fulfillment_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'allocated_quantity' => 'float',
        'picked_quantity' => 'float',
        'packed_quantity' => 'float',
        'shipped_quantity' => 'float',
    ];
}
