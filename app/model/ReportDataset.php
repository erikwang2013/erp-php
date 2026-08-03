<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReportDataset extends Model
{
    protected $table = 'erik_report_dataset';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'name', 'query_sql', 'data', 'rows_count', 'generated_at', 'parameters'];
    protected $casts = ['template_id' => 'integer', 'data' => 'array', 'rows_count' => 'integer', 'parameters' => 'array'];

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id');
    }
}
