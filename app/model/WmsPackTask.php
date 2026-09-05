<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class WmsPackTask extends Model
{
    use Searchable;
    protected $table = 'wms_pack_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'warehouse_id', 'status', 'package_type', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'assigned_to', 'completed_at'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'status' => 'integer',
        'weight_kg' => 'float',
        'length_cm' => 'float',
        'width_cm' => 'float',
        'height_cm' => 'float',
        'assigned_to' => 'integer',
    ];
}
