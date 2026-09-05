<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrLeave extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'hr_leave';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['employee_id', 'type', 'start_date', 'end_date', 'days', 'status', 'reason'];
    protected $casts = ['employee_id' => 'integer', 'type' => 'integer', 'days' => 'float', 'status' => 'integer'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
