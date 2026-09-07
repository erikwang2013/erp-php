<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmQuotation extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'crm_quotation';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // guarded-only 时 fill() 不落任何列 → 真实 NOT NULL(customer_id/owner_user_id) 缺省直插 500；
    // 表无 name 列（页面幻键 name 被静默过滤）。显式列白名单（code 留空由控制器生成，uk_code 唯一）。
    protected $fillable = ['code', 'customer_id', 'opportunity_id', 'total_amount', 'status', 'remark', 'quoted_at', 'valid_until', 'owner_user_id'];
}
