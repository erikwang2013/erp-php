<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * 集成测试专用 CRUD 模型：映射测试临时表 erik_it_crud。
 *
 * 与业务模型保持一致的约定（snowflake 风格主键、非自增、bigint），
 * 但不依赖任何业务 trait（SoftDeletes/Searchable），保证测试自包含。
 */
class CrudTestModel extends Model
{
    protected $table = 'erik_it_crud';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id', 'name', 'quantity', 'price', 'status'];

    protected $casts = [
        'id' => 'integer',
        'quantity' => 'integer',
        'price' => 'float',
        'status' => 'integer',
    ];
}
