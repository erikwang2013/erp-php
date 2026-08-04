<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsAsn extends Model
{
    protected $table = 'erik_wms_asn';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'supplier_id', 'warehouse_id', 'purchase_order_id', 'expected_arrive_at', 'arrived_at', 'carrier', 'tracking_no', 'status', 'total_packages', 'remark'];
    protected $casts = [
        'supplier_id' => 'integer',
        'warehouse_id' => 'integer',
        'purchase_order_id' => 'integer',
        'status' => 'integer',
        'total_packages' => 'integer',
    ];
}
