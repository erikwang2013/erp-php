<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class ApprovalNode extends Model
{
    use Searchable;
    protected $table = 'approval_node';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 仅 $guarded 时 getFillable()=[] → fillableOnly/create 静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['workflow_id', 'name', 'approver_type', 'approver_id', 'role_id', 'seq', 'condition_field', 'condition_op', 'condition_value', 'can_reject'];
    public $timestamps = false;
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['workflow_id', 'name', 'approver_type', 'approver_id', 'role_id', 'seq', 'condition_field', 'condition_op', 'condition_value', 'can_reject'];
}
