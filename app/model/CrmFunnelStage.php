<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmFunnelStage extends Model
{
    use Searchable;
    protected $table = 'crm_funnel_stage';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // guarded-only 时 getFillable()=[]，AbstractCrudService::create() 不落任何列 →
    // 真实 NOT NULL(name) 缺省直插 500。显式列白名单（表无 code 列，页面幻键被静默过滤）。
    protected $fillable = ['name', 'sort', 'win_rate', 'status'];
}
