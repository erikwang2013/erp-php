<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 委外加工订单（P1-M2）
 *
 * 状态机：0草稿 → 1已发料 → 2已收货 → 3已核销。
 * 发料/收料分别以 erp_mfg_subcontract_issue / erp_mfg_subcontract_receive
 * 单证记录，审核时双向累计本表 issued_amount / received_qty；
 * 累计收货 ≥ 委外数量时自动核销（status=3，consumed_amount=issued_amount 快照）。
 */
class MfgSubcontract extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'mfg_subcontract';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'supplier_id', 'product_id', 'warehouse_id',
        'quantity', 'unit_price', 'remark',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'product_id' => 'integer',
        'warehouse_id' => 'integer',
        'quantity' => 'float',
        'unit_price' => 'float',
        'amount' => 'float',
        'issued_amount' => 'float',
        'received_qty' => 'float',
        'consumed_amount' => 'float',
        'status' => 'integer',
    ];

    /** 供应商 */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** 发料单列表（按审核时间倒序） */
    public function issues(): HasMany
    {
        return $this->hasMany(MfgSubcontractIssue::class, 'subcontract_id')->orderBy('id', 'desc');
    }

    /** 收料单列表（按审核时间倒序） */
    public function receives(): HasMany
    {
        return $this->hasMany(MfgSubcontractReceive::class, 'subcontract_id')->orderBy('id', 'desc');
    }
}
