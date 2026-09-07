<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class SalesReturn extends Model
{
    use Searchable;
    protected $table = 'sales_return';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // $fillable 显式白名单（与 $guarded 并存）：fill 仅落真实表列，杜绝幻列/任意键写入
    protected $fillable = ['code', 'delivery_id', 'customer_id', 'warehouse_id', 'total_amount', 'status', 'remark', 'returned_at'];
}
