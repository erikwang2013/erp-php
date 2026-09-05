<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 课程选课（H3）
 *
 * 状态机：0 已报名 / 1 已完成 / 2 已取消（见 TrainingService docblock）。
 * UNIQUE(course_id, employee_id)：同员工同课程仅一条选课。
 */
class HrCourseEnrollment extends Model
{
    use Searchable;
    protected $table = 'hr_course_enrollment';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['course_id', 'employee_id', 'status', 'completed_at', 'created_by'];

    protected $casts = [
        'course_id' => 'integer',
        'employee_id' => 'integer',
        'status' => 'integer',
        'created_by' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(HrCourse::class, 'course_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
