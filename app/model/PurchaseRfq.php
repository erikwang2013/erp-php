<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class PurchaseRfq extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'purchase_rfq';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public const STATUS_DRAFT = 0;      // 草稿
    public const STATUS_SUBMITTED = 1;  // 已发布（询价中）
    public const STATUS_WON = 2;        // 已中标
    public const STATUS_CLOSED = 3;     // 已关闭
    public const STATUS_CANCELLED = 4;  // 已取消

    public function items()
    {
        return $this->hasMany(PurchaseRfqItem::class, 'rfq_id');
    }

    public function quotes()
    {
        return $this->hasMany(PurchaseRfqQuote::class, 'rfq_id');
    }
}
