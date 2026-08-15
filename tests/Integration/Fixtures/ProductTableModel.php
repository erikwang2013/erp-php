<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * 集成测试专用模型：映射真实业务表 erik_product（结构见
 * database/migrations/2026_05_22_000003_product_base_tables.sql）。
 *
 * 不使用 app\model\Product 的原因：
 * 1. Product 挂载 Searchable（webman-scout），保存时会触发索引观察器，
 *    测试环境没有 Scout 引擎，会引入外部依赖；
 * 2. Product 使用 SoftDeletes（软删除），本测试需要物理删除以保持库干净。
 * 本模型为纯 Eloquent 映射，字段与迁移保持一致，仅用于验证「真实业务表」的读写。
 */
class ProductTableModel extends Model
{
    protected $table = 'erik_product';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** erik_product 的 created_at/updated_at 由数据库默认值填充 */
    public $timestamps = false;

    protected $fillable = [
        'id', 'category_id', 'brand_id', 'code', 'name', 'barcode',
        'spec', 'unit', 'image', 'description', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'category_id' => 'integer',
        'brand_id' => 'integer',
    ];
}
