<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class HrEmployee extends Model
{
    use SoftDeletes;

    protected $table = 'erp_hr_employee';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'department_id', 'position_id', 'gender', 'birthday', 'phone', 'email', 'id_card', 'hire_date', 'status', 'bank_account', 'emergency_contact', 'emergency_phone'];
    protected $casts = [
        'department_id' => 'integer',
        'position_id' => 'integer',
        'gender' => 'integer',
        'status' => 'integer',
        'phone' => Encryptable::class,
        'email' => Encryptable::class,
        'id_card' => Encryptable::class,
        'bank_account' => Encryptable::class,
        'emergency_phone' => Encryptable::class,
    ];

    public function department()
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }
    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position_id');
    }
}
