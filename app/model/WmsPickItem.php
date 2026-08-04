<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsPickItem extends Model
{
    protected $table = 'erik_wms_pick_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['pick_task_id', 'product_id', 'sku_id', 'batch_code', 'location_id', 'ordered_quantity', 'picked_quantity', 'unit', 'status', 'picked_at'];
    protected $casts = [
        'pick_task_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'location_id' => 'integer',
        'ordered_quantity' => 'float',
        'picked_quantity' => 'float',
        'status' => 'integer'
    ];
}
