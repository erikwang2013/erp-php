<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsWave extends Model
{
    protected $table = 'erik_wms_wave';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'warehouse_id', 'type', 'status', 'priority', 'scheduled_at', 'completed_at'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'priority' => 'integer',
    ];
}
