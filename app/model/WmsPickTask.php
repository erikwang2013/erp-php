<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsPickTask extends Model
{
    protected $table = 'erik_wms_pick_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'warehouse_id', 'wave_id', 'type', 'status', 'assigned_to', 'priority', 'started_at', 'completed_at'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'wave_id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'assigned_to' => 'integer',
        'priority' => 'integer',
    ];
}
