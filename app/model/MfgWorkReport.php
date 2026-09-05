<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class MfgWorkReport extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'mfg_work_report';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'order_id', 'product_id', 'routing_id', 'workstation_id', 'employee_id',
        'report_date', 'quantity', 'qualified_qty', 'piece_rate', 'amount', 'status', 'audit_at', 'remark',
    ];
    protected $casts = [
        'order_id' => 'integer',
        'product_id' => 'integer',
        'routing_id' => 'integer',
        'workstation_id' => 'integer',
        'employee_id' => 'integer',
        'report_date' => 'string',
        'quantity' => 'float',
        'qualified_qty' => 'float',
        'piece_rate' => 'float',
        'amount' => 'float',
        'status' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(MfgProductionOrder::class, 'order_id');
    }
    public function routing()
    {
        return $this->belongsTo(MfgRouting::class, 'routing_id');
    }
    public function workstation()
    {
        return $this->belongsTo(MfgWorkstation::class, 'workstation_id');
    }
    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
