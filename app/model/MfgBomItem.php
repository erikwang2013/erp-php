<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgBomItem extends Model
{
    protected $table = 'erik_mfg_bom_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['bom_id', 'component_product_id', 'quantity', 'unit', 'scrap_rate', 'seq'];
    protected $casts = ['bom_id' => 'integer', 'component_product_id' => 'integer', 'quantity' => 'float', 'scrap_rate' => 'float', 'seq' => 'integer'];

    public function bom()
    {
        return $this->belongsTo(MfgBom::class, 'bom_id');
    }
}
