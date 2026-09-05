<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * Webhook 投递日志（一次事件一条记录，重试在同一记录上累积 attempts / next_retry_at）
 */
class WebhookDeliveryLog extends Model
{
    use Searchable;
    protected $table = 'webhook_delivery_log';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['subscription_id', 'event', 'payload', 'status', 'attempts', 'next_retry_at', 'http_code', 'response_summary'];

    protected $casts = [
        'subscription_id' => 'integer',
        'payload' => 'array',
        'attempts' => 'integer',
        'http_code' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
