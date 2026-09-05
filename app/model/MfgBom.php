<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class MfgBom extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'mfg_bom';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['product_id', 'code', 'name', 'version', 'status', 'effective_date'];
    protected $casts = ['product_id' => 'integer', 'status' => 'integer'];

    public function items()
    {
        return $this->hasMany(MfgBomItem::class, 'bom_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
