<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgWipFlow extends Model
{
    protected $table = 'erp_mfg_wip_flow';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['wip_id', 'order_id', 'source_type', 'source_id', 'amount', 'direction', 'flow_date'];
    protected $casts = [
        'wip_id' => 'integer',
        'order_id' => 'integer',
        'source_type' => 'integer',
        'source_id' => 'integer',
        'amount' => 'float',
        'direction' => 'integer',
        'flow_date' => 'string',
    ];
}
