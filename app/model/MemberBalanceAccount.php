<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 会员储值账户 — P2-3 C1
 *
 * balance 为当前可用余额（DECIMAL 字符串直出，算术走 bcmath），
 * 变动一律由 MemberService 在事务内 lockForUpdate 本行后执行。
 */
class MemberBalanceAccount extends Model
{
    use Searchable;
    protected $table = 'member_balance_account';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
