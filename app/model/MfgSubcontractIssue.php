<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 委外发料单（P1-M2，表头）
 *
 * 状态机：0草稿 → 1已审核。审核时逐行出库（移动加权成本快照），
 * 累计金额写入 total_cost，并回写委外单 issued_amount 与状态 0→1。
 */
class MfgSubcontractIssue extends Model
{
    use SoftDeletes;

    protected $table = 'erp_mfg_subcontract_issue';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'subcontract_id', 'warehouse_id',
        'issue_date', 'total_cost', 'remark',
    ];

    protected $casts = [
        'subcontract_id' => 'integer',
        'warehouse_id' => 'integer',
        'issue_date' => 'string',
        'total_cost' => 'float',
        'status' => 'integer',
    ];

    /** 所属委外单 */
    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(MfgSubcontract::class, 'subcontract_id');
    }

    /** 发料明细 */
    public function items(): HasMany
    {
        return $this->hasMany(MfgSubcontractIssueItem::class, 'issue_id');
    }
}
