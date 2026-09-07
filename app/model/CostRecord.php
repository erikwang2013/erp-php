<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CostRecord extends Model
{
    use Searchable;
    protected $table = 'cost_record';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    // 成本记录为追加日志：表仅有 created_at（DB 默认填充），无 updated_at，禁 Eloquent 时间戳
    public $timestamps = false;
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['product_id', 'sku_id', 'flow_id', 'type', 'quantity', 'unit_cost', 'before_avg_cost', 'after_avg_cost'];
}
