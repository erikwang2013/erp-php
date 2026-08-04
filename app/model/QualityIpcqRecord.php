<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class QualityIpcqRecord extends Model
{
    protected $table = 'erik_quality_ipqc_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'production_order_id', 'product_id', 'workstation_id', 'standard_id', 'inspected_qty', 'passed_qty', 'rejected_qty', 'result', 'inspector', 'remark', 'status'];
    public $timestamps = true;
}
