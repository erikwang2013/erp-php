<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class EamRepairOrder extends Model
{
    protected $table = 'erik_eam_repair_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'equipment_id', 'fault_description', 'repair_type', 'assignee', 'start_date', 'end_date', 'cost', 'status'];
    public $timestamps = true;
}
