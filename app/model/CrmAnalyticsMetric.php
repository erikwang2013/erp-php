<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmAnalyticsMetric extends Model
{
    use Searchable;
    protected $table = 'crm_analytics_metric';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['name', 'key', 'type', 'query_config', 'enabled'];
}
