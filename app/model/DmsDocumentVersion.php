<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use support\Model;

class DmsDocumentVersion extends Model
{
    protected $table = 'erp_dms_document_version';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['document_id', 'version', 'content', 'changed_by', 'change_note'];
    public $timestamps = true;
}
