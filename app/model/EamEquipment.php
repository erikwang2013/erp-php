<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class EamEquipment extends Model
{
    protected $table = 'erik_eam_equipment';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'model', 'serial_number', 'category', 'location', 'department_id', 'purchase_date', 'warranty_expiry', 'status'];
    public $timestamps = true;
}
