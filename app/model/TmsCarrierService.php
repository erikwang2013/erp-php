<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsCarrierService extends Model
{
    protected $table = 'erik_tms_carrier_service';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['carrier_id', 'code', 'name', 'type', 'estimated_days_min', 'estimated_days_max', 'status'];
    protected $casts = [
        'carrier_id' => 'integer',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'status' => 'integer',
    ];
}
