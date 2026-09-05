<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Erikwang2013\WebmanScout\Searchable;
use support\Model;

/**
 * 租户（P2-4 B5）——公司 1:1 租户注册表
 *
 * company_id 唯一（uk_company）：一个公司至多一个租户，租户以 tenant_code
 * 对外标识（X-Tenant-Code 请求头查表来源）。plan/status/expire_at 状态机
 * 见 app\service\platform\TenantService（消息文本为稳定契约）。
 *
 * 软删说明：tenant_code/company_id 的 UNIQUE 键含已软删行，同一公司/编码
 * 复开需先 restore 既有行（或清理）；服务层唯一性预检仅覆盖未删行，
 * 并发竞态由 DB 唯一键兜底（同 erp_admin_user 软删约定）。
 *
 * @property int $id
 * @property int $company_id
 * @property string $tenant_code
 * @property int $plan
 * @property int $status
 * @property string $expire_at
 * @property string|null $opened_at
 * @property string $remark
 * @property int $created_by
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 */
class Tenant extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $table = 'tenant';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
