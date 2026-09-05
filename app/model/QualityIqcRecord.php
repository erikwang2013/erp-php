<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class QualityIqcRecord extends Model
{
    use Searchable;
    protected $table = 'quality_iqc_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'code', 'receiving_id', 'product_id', 'standard_id', 'inspected_qty', 'passed_qty', 'rejected_qty', 'result', 'inspector', 'remark', 'status'];
    public $timestamps = true;
}
