<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class PurchaseReceive extends Model
{
    use SoftDeletes;

    protected $table = 'erik_purchase_receive';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'order_id', 'supplier_id', 'warehouse_id', 'status', 'remark', 'received_at'];
    protected $casts = ['order_id' => 'integer', 'supplier_id' => 'integer', 'warehouse_id' => 'integer', 'status' => 'integer'];

    public function items() { return $this->hasMany(PurchaseReceiveItem::class, 'receive_id'); }
    public function order() { return $this->belongsTo(PurchaseOrder::class, 'order_id'); }
    public function supplier() { return $this->belongsTo(Supplier::class, 'supplier_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class, 'warehouse_id'); }
}
