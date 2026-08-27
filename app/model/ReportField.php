<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReportField extends Model
{
    protected $table = 'erp_report_field';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'name', 'field', 'label', 'data_type', 'aggregator', 'sort_order', 'width', 'visible'];
    protected $casts = ['template_id' => 'integer', 'sort_order' => 'integer', 'width' => 'integer', 'visible' => 'integer'];

    public function template()
    {
        return $this->belongsTo(ReportTemplate::class, 'template_id');
    }
}
