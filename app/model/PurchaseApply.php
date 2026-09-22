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
    // 放行后客户端可用 hashid 串直填 BIGINT 列（1366 → 500），且可自造「已审批」记录。
    // 采购侧并非没有写入方 —— ApplyController::update() 的批准/驳回分支就写这两列，
    // 它走 forceFill 显式绕开本白名单，且只认中间件注入的 adminId（请求体里自报的 approved_by 被无视）。
    // 也就是说「不开放」靠的是那条服务端路径，不是靠没人写；别顺手把这两列加进来。
    protected $fillable = ['code', 'apply_user_id', 'department', 'status', 'remark'];
}
