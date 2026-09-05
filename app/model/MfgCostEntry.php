<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class MfgCostEntry extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'mfg_cost_entry';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'order_id', 'entry_type', 'amount', 'entry_date', 'status', 'summary'];
    protected $casts = [
        'order_id' => 'integer',
        'entry_type' => 'integer',
        'amount' => 'float',
        'entry_date' => 'string',
        'status' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(MfgProductionOrder::class, 'order_id');
    }
}
