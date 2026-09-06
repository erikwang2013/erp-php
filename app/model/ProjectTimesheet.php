<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class ProjectTimesheet extends Model
{
    use Searchable;
    protected $table = 'project_timesheet';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // fillable 与 guarded 需共存：仅 guarded 时 getFillable() 为空 → fill 空写。
    protected $fillable = ['project_id', 'task_id', 'user_id', 'hours', 'work_date', 'description'];
}
