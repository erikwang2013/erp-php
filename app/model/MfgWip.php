<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgWip extends Model
{
    protected $table = 'erp_mfg_wip';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['order_id', 'material_cost', 'labor_cost', 'overhead_cost', 'other_cost', 'total_cost', 'status'];
    protected $casts = [
        'order_id' => 'integer',
        'material_cost' => 'float',
        'labor_cost' => 'float',
        'overhead_cost' => 'float',
        'other_cost' => 'float',
        'total_cost' => 'float',
        'status' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(MfgProductionOrder::class, 'order_id');
    }
}
