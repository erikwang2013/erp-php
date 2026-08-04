<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsOrder extends Model
{
    protected $table = 'erik_oms_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['order_id', 'channel', 'channel_order_no', 'channel_store', 'fulfillment_status', 'payment_status', 'shipping_method', 'shipping_fee', 'buyer_message', 'seller_note', 'priority', 'hold_until'];
    protected $casts = [
        'order_id' => 'integer',
        'fulfillment_status' => 'integer',
        'payment_status' => 'integer',
        'shipping_fee' => 'float',
        'priority' => 'integer'
    ];
}
