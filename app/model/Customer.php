<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Erikwang2013\WebmanScout\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use support\Model;

class Customer extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $table = 'erp_customer';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'level_id', 'contact_person', 'phone', 'email', 'address', 'credit_limit', 'credit_days', 'credit_frozen', 'credit_over_ratio', 'credit_overdue_limit_amount', 'status', 'remark'];
    protected $casts = ['level_id' => 'integer', 'credit_limit' => 'float', 'credit_days' => 'integer', 'credit_frozen' => 'integer', 'credit_over_ratio' => 'float', 'credit_overdue_limit_amount' => 'float', 'status' => 'integer', 'phone' => Encryptable::class, 'email' => Encryptable::class];

    public function level()
    {
        return $this->belongsTo(CustomerLevel::class, 'level_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
