<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class QualityNonconformity extends Model
{
    protected $table = 'erp_quality_nonconformity';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'source_type', 'source_id', 'product_id', 'defect_type', 'defect_qty', 'severity', 'disposition', 'root_cause', 'corrective_action', 'status', 'reported_by'];
    public $timestamps = true;
}
