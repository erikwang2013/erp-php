<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsShipment extends Model
{
    protected $table = 'erp_tms_shipment';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'carrier_service_id', 'tracking_no', 'status', 'shipping_label_url', 'estimated_delivery_at', 'actual_delivery_at', 'origin_address_snapshot', 'dest_address_snapshot', 'total_weight_kg', 'total_volume_cm3', 'package_count', 'freight_charge', 'insurance_charge', 'currency'];
    protected $casts = [
        'carrier_service_id' => 'integer',
        'status' => 'integer',
        'origin_address_snapshot' => 'array',
        'dest_address_snapshot' => 'array',
        'total_weight_kg' => 'float',
        'total_volume_cm3' => 'float',
        'package_count' => 'integer',
        'freight_charge' => 'float',
        'insurance_charge' => 'float',
    ];
}
