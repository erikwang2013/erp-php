<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsLocation extends Model
{
    protected $table = 'erp_wms_location';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['location_id', 'zone_id', 'aisle', 'rack', 'level', 'bin', 'barcode', 'length_cm', 'width_cm', 'height_cm', 'max_weight_kg', 'max_volume_cm3', 'pick_sequence', 'status'];
    protected $casts = [
        'location_id' => 'integer',
        'zone_id' => 'integer',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'max_weight_kg' => 'float',
        'max_volume_cm3' => 'float',
        'pick_sequence' => 'integer',
        'status' => 'integer',
    ];
}
