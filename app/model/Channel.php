<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class Channel extends Model
{
    use Searchable;
    protected $table = 'channel';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $fillable = ['code', 'name', 'type', 'config', 'status'];
    protected $casts = [
        'config' => 'array',
        'status' => 'integer',
    ];
}
