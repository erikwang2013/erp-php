<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员积分账户 — P2-3 C1
 *
 * points 为当前可用积分（INT UNSIGNED），变动由 MemberService 事务内行锁执行。
 */
class MemberPointAccount extends Model
{
    use Searchable;
    protected $table = 'member_point_account';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
