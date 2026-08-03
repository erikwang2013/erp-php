<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class SalesDelivery extends Model
{
    use SoftDeletes;

    protected $table = 'erik_sales_delivery';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'order_id', 'customer_id', 'warehouse_id', 'status', 'remark', 'delivered_at'];
    protected $casts = ['order_id' => 'integer', 'customer_id' => 'integer', 'warehouse_id' => 'integer', 'status' => 'integer'];

    public function items()
    {
        return $this->hasMany(SalesDeliveryItem::class, 'delivery_id');
    }
    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
