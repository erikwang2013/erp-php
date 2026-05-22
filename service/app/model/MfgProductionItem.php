<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgProductionItem extends Model
{
    protected $table = 'erik_mfg_production_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['order_id', 'product_id', 'planned_quantity', 'completed_quantity', 'status'];
    protected $casts = ['order_id' => 'integer', 'product_id' => 'integer', 'planned_quantity' => 'float', 'completed_quantity' => 'float', 'status' => 'integer'];

    public function order() { return $this->belongsTo(MfgProductionOrder::class, 'order_id'); }
}
