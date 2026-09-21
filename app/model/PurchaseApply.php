<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class PurchaseApply extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'purchase_apply';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    // 不含 approved_at/approved_by：审批人/审批时间属审批动作的服务端产物，
    // 放行后客户端可用 hashid 串直填 BIGINT 列（1366 → 500），且可自造「已审批」记录；
    // 采购侧无任何合法写入方（全库仅财务费用/OMS 售后退货在用），故不予开放。
    protected $fillable = ['code', 'apply_user_id', 'department', 'status', 'remark'];
}
