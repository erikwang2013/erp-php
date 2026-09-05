<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmContact extends Model
{
    use Searchable;
    protected $table = 'crm_contact';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['customer_id', 'name', 'position', 'phone', 'email', 'is_primary', 'status'];
    protected $casts = ['customer_id' => 'integer', 'is_primary' => 'integer', 'status' => 'integer', 'phone' => Encryptable::class, 'email' => Encryptable::class];
}
