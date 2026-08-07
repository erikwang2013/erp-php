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

class Supplier extends Model
{
    use SoftDeletes;
    use Searchable;

    protected $table = 'erik_supplier';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'name', 'contact_person', 'phone', 'email', 'address', 'bank_name', 'bank_account', 'tax_number', 'tax_rate', 'status', 'remark'];
    protected $casts = ['tax_rate' => 'float', 'status' => 'integer', 'phone' => Encryptable::class, 'email' => Encryptable::class, 'bank_account' => Encryptable::class];

    public function toSearchableArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
