<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class MfgWorkstation extends Model
{
    protected $table = 'erp_mfg_workstation';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'capacity', 'status'];
    protected $casts = ['capacity' => 'integer', 'status' => 'integer'];
}
