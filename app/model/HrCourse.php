<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 课程（H3）
 *
 * 状态机：0 草稿 / 1 上架 / 2 下架（见 TrainingService docblock）。
 * 软删除：删除课程不抹除员工已完成学分（学分查询带 withTrashed 关联）。
 */
class HrCourse extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'hr_course';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['title', 'course_type', 'lecturer', 'credits', 'duration_hours', 'status'];

    protected $casts = [
        'credits' => 'integer',
        'duration_hours' => 'float',
        'status' => 'integer',
    ];

    public function enrollments(): HasMany
    {
        return $this->hasMany(HrCourseEnrollment::class, 'course_id');
    }
}
