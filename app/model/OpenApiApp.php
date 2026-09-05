<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\model;

use Erikwang2013\Encryptable\Encryptable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 开放平台第三方应用（P0 OpenAPI）
 *
 * 认证凭据：app_key 公开标识；app_secret 必须可解密（HMAC 验签需要原文），
 * 故以 Encryptable 加密存储（AES-256-CBC，密钥 ENCRYPTABLE_KEY），读取时自动解密。
 * app_secret_hash 仅作密钥完整性校验（创建/重置时比对），不可用于验签。
 */
class OpenApiApp extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'openapi_app';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['app_name', 'app_key', 'app_secret', 'app_secret_hash', 'scopes', 'status', 'created_by'];

    // app_secret/app_secret_hash 不得进入任何 toArray 序列化输出
    protected $hidden = ['app_secret', 'app_secret_hash'];

    protected $casts = [
        'app_secret' => Encryptable::class,
        'scopes' => 'array',
        'status' => 'integer',
        'created_by' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(WebhookSubscription::class, 'app_id');
    }
}
