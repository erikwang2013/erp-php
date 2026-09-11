<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class ProjectTask extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'project_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // fillable 与 guarded 需共存：仅 guarded 时 getFillable() 为空 → fill 空写。
    protected $fillable = ['project_id', 'parent_id', 'name', 'assignee_user_id', 'status', 'priority', 'start_date', 'due_date', 'completed_at', 'estimated_hours', 'actual_hours', 'progress', 'seq', 'description'];
}
