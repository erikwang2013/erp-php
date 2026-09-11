<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class SalesOrder extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'sales_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // 与 erp_purchase_order 的写法对齐：仅 $guarded 时 BaseController::fillModelFromRequest
    // 按 getFillable() 取值得到空集 → store 只落 id/timestamps 必 1364 崩
    protected $fillable = ['code', 'customer_id', 'warehouse_id', 'total_amount', 'discount_amount', 'status', 'remark', 'ordered_at'];
}
