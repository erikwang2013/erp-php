<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReportSchedule extends Model
{
    protected $table = 'erik_report_schedule';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'name', 'frequency', 'recipients', 'next_run_at', 'enabled', 'last_run_at'];
    protected $casts = ['template_id' => 'integer', 'frequency' => 'integer', 'enabled' => 'integer'];

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id');
    }
}
