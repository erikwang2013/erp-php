<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class WmsPutawayTask extends Model
{
    use Searchable;
    protected $table = 'wms_putaway_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    // status 只由 start/complete 动作流转，不进 fillable（防止通用 PUT 把已完成任务改回进行中）
    protected $fillable = ['code', 'warehouse_id', 'receiving_id', 'strategy', 'assigned_to', 'completed_at'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'receiving_id' => 'integer',
        'status' => 'integer',
        'assigned_to' => 'integer',
    ];
}
