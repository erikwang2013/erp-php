<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgRouting extends Model
{
    protected $table = 'erp_mfg_routing';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['product_id', 'name', 'seq', 'workstation_id', 'standard_hours', 'piece_rate', 'description'];
    protected $casts = ['product_id' => 'integer', 'seq' => 'integer', 'workstation_id' => 'integer', 'standard_hours' => 'float', 'piece_rate' => 'float'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function workstation()
    {
        return $this->belongsTo(MfgWorkstation::class, 'workstation_id');
    }
}
