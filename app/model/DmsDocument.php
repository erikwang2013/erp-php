<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class DmsDocument extends Model
{
    protected $table = 'erp_dms_document';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['code', 'title', 'category', 'version', 'author', 'status', 'content', 'tags'];
    public $timestamps = true;
}
