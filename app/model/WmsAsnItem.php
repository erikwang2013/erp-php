<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsAsnItem extends Model
{
    protected $table = 'erik_wms_asn_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['asn_id', 'product_id', 'sku_id', 'expected_quantity', 'received_quantity', 'unit'];
    protected $casts = [
        'asn_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'expected_quantity' => 'float',
        'received_quantity' => 'float'
    ];
}
