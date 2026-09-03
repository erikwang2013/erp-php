<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class MfgMaterialIssue extends Model
{
    use SoftDeletes;

    protected $table = 'erp_mfg_material_issue';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'order_id', 'warehouse_id', 'issue_date', 'status', 'total_cost', 'remark'];
    protected $casts = [
        'order_id' => 'integer',
        'warehouse_id' => 'integer',
        'issue_date' => 'string',
        'status' => 'integer',
        'total_cost' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(MfgProductionOrder::class, 'order_id');
    }
    public function items()
    {
        return $this->hasMany(MfgMaterialIssueItem::class, 'issue_id');
    }
}
