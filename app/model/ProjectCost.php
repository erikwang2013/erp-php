<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 项目成本归集台账
 * source_type: timesheet=工时归集(自动) / manual=手工录入
 * category: 1=人工 2=材料 3=其他
 * cost 为服务端 bcmath 计算结果（DECIMAL 字符串语义），本模型不做运算。
 */
class ProjectCost extends Model
{
    use Searchable;
    protected $table = 'project_cost';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['project_id', 'task_id', 'employee_id', 'work_date', 'source_type', 'timesheet_id', 'category', 'hours', 'rate', 'cost', 'remark'];
    public $timestamps = true;
}
