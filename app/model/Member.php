<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员主档 — P2-3 C1（零售会员价值引擎）
 *
 * 储值/积分余额在 MemberBalanceAccount/MemberPointAccount（一会员一行），
 * 本表不含金额列；phone 为开卡唯一标识（软删记录也占用，uk_phone 硬约束）。
 */
class Member extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'member';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['phone', 'name', 'level', 'customer_id', 'source', 'status', 'remark'];
}
