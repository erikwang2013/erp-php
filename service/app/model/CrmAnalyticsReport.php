<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\model;
use support\Model;

class CrmAnalyticsReport extends Model
{
    protected $table = 'erik_crm_analytics_report';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
}
