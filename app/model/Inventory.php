<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class Inventory extends Model
{
    protected $table = 'erik_inventory';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['product_id', 'sku_id', 'warehouse_id', 'location_id', 'batch_code', 'quantity', 'cost_price'];
    protected $casts = ['product_id' => 'integer', 'sku_id' => 'integer', 'warehouse_id' => 'integer', 'location_id' => 'integer', 'quantity' => 'float', 'cost_price' => 'float'];
    public $timestamps = false;
}
