<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmTicket extends Model
{
    use Searchable;
    use SoftDeletes;
    protected $table = 'crm_ticket';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    // 显式 fillable（与 guarded 共存）：无 fillable → AbstractCrudService::fillableOnly 过滤为空 → 新增/编辑全空落库
    // 仅列可编辑字段；status/assignee_user_id/resolved_at/closed_at 由指派/解决/流转端点管理
    protected $fillable = ['code', 'customer_id', 'contact_id', 'title', 'priority', 'category', 'content'];
}
