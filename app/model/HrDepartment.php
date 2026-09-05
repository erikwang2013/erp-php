<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class HrDepartment extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'hr_department';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['parent_id', 'code', 'name', 'manager_user_id', 'status'];
    protected $casts = ['parent_id' => 'integer', 'manager_user_id' => 'integer', 'status' => 'integer'];

    public function parent()
    {
        return $this->belongsTo(HrDepartment::class, 'parent_id');
    }
    public function children()
    {
        return $this->hasMany(HrDepartment::class, 'parent_id');
    }
}
