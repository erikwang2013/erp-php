<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class Project extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'project';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // fillable 与 guarded 需共存：BaseController::fillModelFromRequest 走 getFillable()，
    // 仅 guarded 时返回空数组 → fill 静默空写（create/edit 字段全丢）。
    protected $fillable = ['code', 'name', 'customer_id', 'manager_user_id', 'status', 'priority', 'start_date', 'end_date', 'budget_amount', 'actual_cost', 'progress', 'description'];
}
