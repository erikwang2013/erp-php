<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'erik_purchase_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'apply_id', 'supplier_id', 'warehouse_id', 'total_amount', 'status', 'remark', 'ordered_at'];
    protected $casts = ['apply_id' => 'integer', 'supplier_id' => 'integer', 'warehouse_id' => 'integer', 'total_amount' => 'float', 'status' => 'integer'];

    public function items() { return $this->hasMany(PurchaseOrderItem::class, 'order_id'); }
    public function supplier() { return $this->belongsTo(Supplier::class, 'supplier_id'); }
}
