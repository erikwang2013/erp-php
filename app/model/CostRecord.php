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
}
