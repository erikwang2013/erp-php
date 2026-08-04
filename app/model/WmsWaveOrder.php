<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class WmsWaveOrder extends Model
{
    protected $table = 'erik_wms_wave_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['wave_id', 'oms_order_id', 'sort'];
    protected $casts = [
        'wave_id' => 'integer',
        'oms_order_id' => 'integer',
        'sort' => 'integer'
    ];
}
