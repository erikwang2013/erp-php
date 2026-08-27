<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsInventoryReservation extends Model
{
    protected $table = 'erp_oms_inventory_reservation';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['product_id', 'sku_id', 'warehouse_id', 'location_id', 'batch_code', 'source_type', 'source_id', 'source_item_id', 'reserved_quantity', 'status'];
    protected $casts = [
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'warehouse_id' => 'integer',
        'location_id' => 'integer',
        'source_id' => 'integer',
        'source_item_id' => 'integer',
        'reserved_quantity' => 'float',
        'status' => 'integer',
    ];
}
