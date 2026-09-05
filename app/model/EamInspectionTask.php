<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 设备点检任务
 * status 流转由 EamInspectionService 维护（0待执行/1已完成/2异常待维修/3已取消），
 * 故 status 不入 fillable，防止请求覆盖状态。
 */
class EamInspectionTask extends Model
{
    use Searchable;
    protected $table = 'eam_inspection_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['equipment_id', 'source_plan_id', 'task_date', 'assignee_id', 'remark'];
    public $timestamps = true;
}
