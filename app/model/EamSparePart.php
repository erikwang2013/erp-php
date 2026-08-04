<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class EamSparePart extends Model
{
    protected $table = 'erik_eam_spare_part';
    protected $fillable = ['code', 'name', 'equipment_id', 'spec', 'unit', 'stock_qty', 'min_stock', 'location', 'status'];
    public $timestamps = true;
}
