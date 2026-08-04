<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class BiDashboard extends Model
{
    protected $table = 'erik_bi_dashboard';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'layout', 'user_id', 'status'];
    public $timestamps = true;
}
