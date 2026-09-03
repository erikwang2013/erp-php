<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 委外发料单明细（P1-M2）
 *
 * 追加型单据行：审核时写入 unit_cost/amount 成本快照后不再变更。
 * 行表仅 created_at（DB 默认填充），禁 Eloquent 时间戳；无软删除。
 */
class MfgSubcontractIssueItem extends Model
{
    protected $table = 'erp_mfg_subcontract_issue_item';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    // 行表仅有 created_at（DB 默认填充），无 updated_at
    public $timestamps = false;

    protected $fillable = [
        'issue_id', 'product_id', 'sku_id', 'quantity', 'unit_cost', 'amount',
    ];

    protected $casts = [
        'issue_id' => 'integer',
        'product_id' => 'integer',
        'sku_id' => 'integer',
        'quantity' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
    ];

    /** 所属发料单 */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(MfgSubcontractIssue::class, 'issue_id');
    }
}
