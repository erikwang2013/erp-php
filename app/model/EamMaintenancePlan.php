<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class EamMaintenancePlan extends Model
{
    use Searchable;
    protected $table = 'eam_maintenance_plan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['equipment_id', 'name', 'frequency', 'last_date', 'next_date', 'assignee', 'status'];
    public $timestamps = true;
}
