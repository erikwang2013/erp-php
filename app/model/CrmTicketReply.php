<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

class CrmTicketReply extends Model
{
    use Searchable;
    protected $table = 'crm_ticket_reply';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    public $timestamps = false;
    // 显式 $fillable 白名单（真实业务列）：getFillable()=[] 时 fillableOnly/create 会静默丢弃全部字段，NOT NULL 无默认列直插 500。
    protected $fillable = ['ticket_id', 'user_id', 'content', 'is_internal'];
}
