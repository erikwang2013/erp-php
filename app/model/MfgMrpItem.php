<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgMrpItem extends Model
{
    protected $table = 'erik_mfg_mrp_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['plan_id', 'product_id', 'gross_requirement', 'scheduled_receipt', 'on_hand', 'net_requirement', 'planned_order_qty', 'planned_start', 'planned_end'];
    protected $casts = [
        'plan_id' => 'integer',
        'product_id' => 'integer',
        'gross_requirement' => 'float',
        'scheduled_receipt' => 'float',
        'on_hand' => 'float',
        'net_requirement' => 'float',
        'planned_order_qty' => 'float',
    ];

    public function plan()
    {
        return $this->belongsTo(MfgMrpPlan::class, 'plan_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
