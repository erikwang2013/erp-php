<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class MfgMaterialIssueItem extends Model
{
    use Searchable;
    protected $table = 'mfg_material_issue_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['issue_id', 'product_id', 'sku_id', 'quantity', 'unit_cost', 'amount'];
    protected $casts = [
        'issue_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'quantity' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
    ];

    public function issue()
    {
        return $this->belongsTo(MfgMaterialIssue::class, 'issue_id');
    }
}
