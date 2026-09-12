<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use support\Model;

class ProductSku extends Model
{
    use Searchable;
    protected $table = 'product_sku';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['product_id', 'spec_id', 'sku_code', 'barcode', 'cost_price', 'status'];

    /**
     * 所属商品规格（spec_id → erp_product_spec.id）
     * 规格属性（attrs JSON）只存在规格表，SKU 不再自带副本，故取值一律经此关联。
     */
    public function spec(): BelongsTo
    {
        return $this->belongsTo(ProductSpec::class, 'spec_id', 'id');
    }
}
