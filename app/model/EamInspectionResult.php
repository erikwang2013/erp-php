<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 设备点检结果明细（一次扫码执行为任务写入一行一条）
 */
class EamInspectionResult extends Model
{
    use Searchable;
    protected $table = 'eam_inspection_result';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['task_id', 'item_name', 'result', 'remark'];
    public $timestamps = true;
}
