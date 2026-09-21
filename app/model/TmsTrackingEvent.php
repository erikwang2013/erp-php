<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class TmsTrackingEvent extends Model
{
    use Searchable;
    protected $table = 'tms_tracking_event';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['shipment_id', 'status_code', 'description', 'location', 'event_time', 'raw_data'];
    protected $casts = [
        'shipment_id' => 'integer',
        'raw_data' => 'array',
    ];
    // 表无 updated_at 列（仅 created_at DEFAULT CURRENT_TIMESTAMP），关闭 Eloquent 时间戳自动维护
    public $timestamps = false;
}
