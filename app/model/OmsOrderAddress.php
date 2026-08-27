<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class OmsOrderAddress extends Model
{
    protected $table = 'erp_oms_order_address';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['order_id', 'type', 'contact_name', 'phone', 'email', 'country', 'state', 'city', 'district', 'address_line1', 'address_line2', 'postal_code'];
    protected $casts = [
        'order_id' => 'integer',
        'type' => 'integer',
    ];
}
