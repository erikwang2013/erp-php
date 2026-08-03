<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $table = 'erik_warehouse';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'code', 'address', 'manager', 'phone', 'status'];
    protected $casts = ['status' => 'integer', 'phone' => Encryptable::class];

    public function locations()
    {
        return $this->hasMany(Location::class, 'warehouse_id');
    }
}
