<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class InventoryAlertRule extends Model
{
    use Searchable;
    protected $table = 'inventory_alert_rule';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // guarded-only 时 getFillable()=[]，fill() 不落任何列 → NOT NULL(product_id) 直插 500。
    // 显式列白名单（表无 name/code/status 列：启用语义列是 enabled）。
    protected $fillable = ['product_id', 'sku_id', 'warehouse_id', 'min_quantity', 'max_quantity', 'enabled'];
}
