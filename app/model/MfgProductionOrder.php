<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class MfgProductionOrder extends Model
{
    use SoftDeletes;

    protected $table = 'erp_mfg_production_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'bom_id', 'warehouse_id', 'planned_quantity', 'completed_quantity', 'status', 'planned_start', 'planned_end', 'actual_start', 'actual_end', 'remark'];
    protected $casts = [
        'bom_id' => 'integer',
        'warehouse_id' => 'integer',
        'planned_quantity' => 'float',
        'completed_quantity' => 'float',
        'status' => 'integer',
    ];

    public function bom()
    {
        return $this->belongsTo(MfgBom::class, 'bom_id');
    }
    public function items()
    {
        return $this->hasMany(MfgProductionItem::class, 'order_id');
    }
}
