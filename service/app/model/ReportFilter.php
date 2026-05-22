<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use support\Model;

class ReportFilter extends Model
{
    protected $table = 'erik_report_filter';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['template_id', 'name', 'field', 'filter_type', 'default_value', 'required'];
    protected $casts = ['template_id' => 'integer', 'required' => 'integer'];

    public function template() { return $this->belongsTo(ReportTemplate::class, 'template_id'); }
}
