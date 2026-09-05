<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class WmsPutawayTask extends Model
{
    use Searchable;
    protected $table = 'wms_putaway_task';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'warehouse_id', 'receiving_id', 'status', 'strategy', 'assigned_to', 'completed_at'];
    protected $casts = [
        'warehouse_id' => 'integer',
        'receiving_id' => 'integer',
        'status' => 'integer',
        'assigned_to' => 'integer',
    ];
}
