<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsZone extends Model
{
    protected $table = 'erik_wms_zone';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['warehouse_id', 'code', 'name', 'type', 'sort', 'status'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'type' => 'integer',
        'sort' => 'integer',
        'status' => 'integer'
    ];
}
