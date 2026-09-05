<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class FinanceVoucherSource extends Model
{
    use Searchable;
    protected $table = 'finance_voucher_source';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['voucher_id', 'source_type', 'source_id'];
    protected $casts = [
        'voucher_id' => 'integer',
        'source_type' => 'string',
        'source_id' => 'integer',
    ];
}
