<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class WmsWaveOrder extends Model
{
    use Searchable;
    protected $table = 'wms_wave_order';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['wave_id', 'oms_order_id', 'sort'];
    protected $casts = [
        'wave_id' => 'integer',
        'oms_order_id' => 'integer',
        'sort' => 'integer',
    ];
    // 表无 updated_at 列（仅 created_at DEFAULT CURRENT_TIMESTAMP），关闭 Eloquent 时间戳自动维护
    public $timestamps = false;
}
