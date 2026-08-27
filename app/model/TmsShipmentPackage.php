<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsShipmentPackage extends Model
{
    protected $table = 'erp_tms_shipment_package';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['shipment_id', 'pack_task_id', 'package_no', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'declared_value'];
    protected $casts = [
        'shipment_id' => 'integer',
        'pack_task_id' => 'integer',
        'weight_kg' => 'float',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'declared_value' => 'float',
    ];
}
