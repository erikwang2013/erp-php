<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class QualityOqcRecord extends Model
{
    protected $table = 'erik_quality_oqc_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'delivery_id', 'product_id', 'standard_id', 'inspected_qty', 'passed_qty', 'rejected_qty', 'result', 'inspector', 'remark', 'status'];
    public $timestamps = true;
}
