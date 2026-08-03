<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class ReportTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'erik_report_template';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'module', 'query_config', 'chart_type', 'status'];
    protected $casts = ['query_config' => 'array', 'status' => 'integer'];

    public function fields()
    {
        return $this->hasMany(ReportField::class, 'template_id');
    }
    public function filters()
    {
        return $this->hasMany(ReportFilter::class, 'template_id');
    }
}
