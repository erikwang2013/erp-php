<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class WmsReceiving extends Model
{
    use Searchable;
    protected $table = 'wms_receiving';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'asn_id', 'warehouse_id', 'dock_location_id', 'status', 'receiver_id', 'received_at', 'remark'];
    protected $casts = [
        'asn_id' => 'integer',
        'warehouse_id' => 'integer',
        'dock_location_id' => 'integer',
        'status' => 'integer',
        'receiver_id' => 'integer',
    ];
}
