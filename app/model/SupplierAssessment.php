<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class SupplierAssessment extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'supplier_assessment';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // dimensions 是 json 列：缺 array 强转时 Eloquent 把 PHP 数组直接交给 PDO，
    // 触发「Array to string conversion」并把字符串 "Array" 写进 json 列 →
    // 3140 Invalid JSON text → 建评分接口无论传什么入参都 500
    protected $casts = ['dimensions' => 'array'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['supplier_id', 'total_score', 'grade', 'dimensions', 'assessor_id', 'assessed_at', 'remark'];
}
