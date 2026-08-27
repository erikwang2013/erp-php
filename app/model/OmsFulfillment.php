<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsFulfillment extends Model
{
    protected $table = 'erp_oms_fulfillment';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['oms_order_id', 'warehouse_id', 'status', 'pick_task_id', 'pack_task_id', 'shipment_id'];
    protected $casts = [
        'oms_order_id' => 'integer',
        'warehouse_id' => 'integer',
        'status' => 'integer',
        'pick_task_id' => 'integer',
        'pack_task_id' => 'integer',
        'shipment_id' => 'integer',
    ];
}
