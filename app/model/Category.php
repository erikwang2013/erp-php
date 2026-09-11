<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Category extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'category';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'name','parent_id', 'sort','status','created_at','updated_at', 'deleted_at'];
    // $guarded 误含业务列（name/parent_id/sort/status）→ fill 全丢弃 → INSERT 只剩
    // id/时间戳 → NOT NULL name 直插 500。显式列白名单与 $guarded 共存（fillable 优先）。
    protected $fillable = ['name', 'code', 'parent_id', 'sort', 'status'];
}
