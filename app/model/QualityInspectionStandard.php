<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class QualityInspectionStandard extends Model
{
    use Searchable;
    protected $table = 'quality_inspection_standard';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['name', 'code', 'product_id', 'type', 'specification', 'sampling_plan', 'status'];
    public $timestamps = true;
}
