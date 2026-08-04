<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsRma extends Model
{
    protected $table = 'erik_oms_rma';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'order_id', 'customer_id', 'type', 'reason', 'status', 'refund_amount', 'return_shipping_fee', 'return_shipment_id', 'approved_by', 'approved_at', 'returned_at', 'received_at'];
    protected $casts = [
        'order_id' => 'integer',
        'customer_id' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
        'refund_amount' => 'float',
        'return_shipping_fee' => 'float',
        'return_shipment_id' => 'integer',
        'approved_by' => 'integer',
    ];
}
