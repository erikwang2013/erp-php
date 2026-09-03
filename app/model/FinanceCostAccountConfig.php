<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class FinanceCostAccountConfig extends Model
{
    protected $table = 'erp_finance_cost_account_config';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['cost_type', 'account_id', 'status'];
    protected $casts = [
        'cost_type' => 'integer',
        'account_id' => 'integer',
        'status' => 'integer',
    ];
}
