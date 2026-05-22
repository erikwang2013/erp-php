<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;
use support\Model;

class InventoryFlow extends Model
{

    protected $table = 'erik_inventory_flow';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
}
