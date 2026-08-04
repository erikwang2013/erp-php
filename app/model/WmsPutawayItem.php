<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsPutawayItem extends Model
{
    protected $table = 'erik_wms_putaway_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['putaway_id', 'product_id', 'sku_id', 'batch_code', 'from_location_id', 'to_location_id', 'quantity', 'unit'];
    protected $casts = [
        'putaway_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'quantity' => 'float',
    ];
}
