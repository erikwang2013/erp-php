<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmAnalyticsReport extends Model
{
    use Searchable;
    protected $table = 'crm_analytics_report';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 仅 $guarded 时 getFillable()=[] → fillableOnly/create 静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['name', 'type', 'period_type', 'period_year', 'period_value', 'report_data', 'generated_at'];
    public $timestamps = false;
}
