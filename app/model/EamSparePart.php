<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class EamSparePart extends Model
{
    use Searchable;
    protected $table = 'eam_spare_part';
    protected $primaryKey = 'id';
    // 主键为 Snowflake 大整数、非自增（全库无 auto_increment）：
    // 缺 $incrementing=false 时 Eloquent 走 insertGetId 并把返回的 LAST_INSERT_ID()=0
    // 回写覆盖实例 id，创建响应 id 恒为 0，且后续 save() 会 update where id=0。
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'name', 'equipment_id', 'spec', 'unit', 'stock_qty', 'min_stock', 'location', 'status'];
    public $timestamps = true;
}
