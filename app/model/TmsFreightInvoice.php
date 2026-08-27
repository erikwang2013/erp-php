<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsFreightInvoice extends Model
{
    protected $table = 'erp_tms_freight_invoice';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'carrier_id', 'shipment_id', 'amount', 'currency', 'status', 'invoice_date', 'due_date'];
    protected $casts = [
        'carrier_id' => 'integer',
        'shipment_id' => 'integer',
        'amount' => 'float',
        'status' => 'integer',
        'invoice_date' => 'datetime',
        'due_date' => 'datetime',
    ];
}
