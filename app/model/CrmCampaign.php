<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmCampaign extends Model
{
    use Searchable;
    use SoftDeletes;
    protected $table = 'crm_campaign';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    // 显式 fillable（与 guarded 共存）：无 fillable → AbstractCrudService::fillableOnly 过滤为空 → 新增/编辑全空落库
    protected $fillable = ['code', 'name', 'type', 'budget_amount', 'actual_cost', 'start_date', 'end_date', 'target_audience', 'description', 'owner_user_id', 'remark'];
}
