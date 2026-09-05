<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员积分流水 — P2-3 C1（只追加；账户积分 = Σpoints 带符号求和）
 */
class MemberPointLog extends Model
{
    use Searchable;
    public $timestamps = false;
    protected $table = 'member_point_log';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at'];
}
