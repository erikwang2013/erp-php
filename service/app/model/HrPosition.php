<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class HrPosition extends Model
{
    protected $table = 'erik_hr_position';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['department_id', 'code', 'name', 'rank', 'status'];
    protected $casts = ['department_id' => 'integer', 'rank' => 'integer', 'status' => 'integer'];

    public function department() { return $this->belongsTo(HrDepartment::class, 'department_id'); }
}
