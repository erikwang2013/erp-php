<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class SalesQuotation extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'sales_quotation';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    // guarded-only 时 BaseController::fillModelFromRequest() 的 getFillable() 为空 → fill 不落库，
    // 须显式声明 fillable（与 guarded 共存），按 erp_sales_quotation 全列（install.sql）
    protected $fillable = ['code', 'customer_id', 'total_amount', 'status', 'remark', 'quoted_at'];
}
