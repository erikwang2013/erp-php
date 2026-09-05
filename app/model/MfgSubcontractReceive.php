<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 委外收料单（P1-M2，表头）
 *
 * 状态机：0草稿 → 1已审核。审核时以委外单加工单价快照入库
 * （source_type=mfg_subcontract_receive），累计委外单 received_qty。
 * 收料数量 ≤ 委外单未收数量（quantity − received_qty）。
 */
class MfgSubcontractReceive extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'mfg_subcontract_receive';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'code', 'subcontract_id', 'warehouse_id',
        'receive_date', 'quantity', 'remark',
    ];

    protected $casts = [
        'subcontract_id' => 'integer',
        'warehouse_id' => 'integer',
        'receive_date' => 'string',
        'quantity' => 'float',
        'unit_price' => 'float',
        'status' => 'integer',
    ];

    /** 所属委外单 */
    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(MfgSubcontract::class, 'subcontract_id');
    }
}
