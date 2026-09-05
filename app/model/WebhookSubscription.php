<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * Webhook 订阅（归属某个开放平台应用）
 *
 * event 为事件名数组（JSON），支持 "*" 通配订阅全部事件；
 * secret 用于生成 X-Webhook-Signature = HMAC-SHA256(secret, payload) 供接收方验签，
 * 同样以 Encryptable 加密存储。
 */
class WebhookSubscription extends Model
{
    use Searchable;
    protected $table = 'webhook_subscription';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['app_id', 'event', 'target_url', 'secret', 'enabled', 'last_status', 'last_delivered_at', 'failed_count', 'created_by'];

    protected $hidden = ['secret'];

    protected $casts = [
        'app_id' => 'integer',
        'event' => 'array',
        'secret' => Encryptable::class,
        'enabled' => 'integer',
        'failed_count' => 'integer',
        'last_delivered_at' => 'datetime',
    ];

    public function app()
    {
        return $this->belongsTo(OpenApiApp::class, 'app_id');
    }

    public function logs()
    {
        return $this->hasMany(WebhookDeliveryLog::class, 'subscription_id');
    }
}
