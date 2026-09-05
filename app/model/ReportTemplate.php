<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class ReportTemplate extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'report_template';
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
