<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class FinanceArAp extends Model
{
    protected $table = 'erik_finance_ar_ap';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['type', 'partner_id', 'source_type', 'source_id', 'amount', 'settled_amount', 'status', 'due_date'];
    protected $casts = ['type' => 'integer', 'partner_id' => 'integer', 'source_id' => 'integer', 'amount' => 'float', 'settled_amount' => 'float', 'status' => 'integer', 'due_date' => 'date'];
}
