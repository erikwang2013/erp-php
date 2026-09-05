<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Product extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $table = 'product';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['category_id', 'brand_id', 'code', 'name', 'barcode', 'spec', 'unit', 'image', 'description', 'status'];
    protected $casts = ['status' => 'integer', 'category_id' => 'integer', 'brand_id' => 'integer'];
    protected $hidden = ['deleted_at'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
    public function skus()
    {
        return $this->hasMany(ProductSku::class, 'product_id');
    }
    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id');
    }
    public function units()
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'barcode' => $this->barcode,
        ];
    }
}
