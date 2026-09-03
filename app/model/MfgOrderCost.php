<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgOrderCost extends Model
{
    protected $table = 'erp_mfg_order_cost';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'order_id', 'finished_qty', 'standard_material_cost', 'actual_material_cost', 'labor_cost',
        'overhead_cost', 'other_cost', 'material_diff', 'total_cost', 'unit_cost', 'voucher_id', 'status',
    ];
    protected $casts = [
        'order_id' => 'integer',
        'finished_qty' => 'float',
        'standard_material_cost' => 'float',
        'actual_material_cost' => 'float',
        'labor_cost' => 'float',
        'overhead_cost' => 'float',
        'other_cost' => 'float',
        'material_diff' => 'float',
        'total_cost' => 'float',
        'unit_cost' => 'float',
        'voucher_id' => 'integer',
        'status' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(MfgProductionOrder::class, 'order_id');
    }
}
