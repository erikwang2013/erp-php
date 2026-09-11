<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class CrmOpportunity extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'crm_opportunity';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // guarded-only 时 fill() 不落任何列 → 真实 NOT NULL(customer_id/stage_id/name)
    // 缺省直插 500。显式列白名单（表无 code 列；amount/stage 为页面幻键，被静默过滤）。
    protected $fillable = ['customer_id', 'stage_id', 'name', 'estimated_amount', 'probability', 'expected_close_date', 'owner_user_id', 'status', 'remark'];
}
