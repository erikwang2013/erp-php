<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsFreightRate extends Model
{
    protected $table = 'erp_tms_freight_rate';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['carrier_service_id', 'origin_country', 'origin_zone', 'dest_country', 'dest_zone', 'weight_from_kg', 'weight_to_kg', 'base_rate', 'per_kg_rate', 'fuel_surcharge_pct', 'currency', 'valid_from', 'valid_to', 'status'];
    protected $casts = [
        'carrier_service_id' => 'integer',
        'weight_from_kg' => 'float',
        'weight_to_kg' => 'float',
        'base_rate' => 'float',
        'per_kg_rate' => 'float',
        'fuel_surcharge_pct' => 'float',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'status' => 'integer',
    ];
}
