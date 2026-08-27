<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class TmsCarrier extends Model
{
    protected $table = 'erp_tms_carrier';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'name', 'type', 'website', 'tracking_url_template', 'api_provider', 'api_config', 'contact_phone', 'status'];
    protected $casts = [
        'api_config' => 'array',
        'status' => 'integer',
    ];
}
