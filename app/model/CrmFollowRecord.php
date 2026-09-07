<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmFollowRecord extends Model
{
    use Searchable;
    protected $table = 'crm_follow_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // guarded-only 时 fill() 不落任何列 → 真实 NOT NULL(customer_id/follow_user_id)
    // 缺省直插 500。显式列白名单（表无 name/code/status 列，页面幻键被静默过滤）。
    protected $fillable = ['customer_id', 'contact_id', 'opportunity_id', 'method', 'content', 'next_plan', 'next_follow_at', 'follow_user_id', 'followed_at'];
}
