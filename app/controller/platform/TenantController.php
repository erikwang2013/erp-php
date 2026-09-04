<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\platform;

use app\admin\controller\BaseController;
use app\model\Tenant;
use app\service\platform\TenantService;
use support\Request;
use support\Response;

/**
 * 租户管理（P2-4 B5）
 *
 * 【状态】本批次仅交付控制器 + Apidoc，不注册路由：启用入口（/admin/platform/tenant/*）
 * 由平台批次统一挂载并接入权限（AdminAuth 之后）。业务规则与错误消息契约
 * 见 TenantService（消息文本为稳定契约，勿在此层改写）。
 *
 * ID 出入参约定：租户 id 一律 hashid 字符串；company_id 兼容 hashid 或数字
 * （decodeIdSafe，同 CompanyController 双解码惯例）。
 */
class TenantController extends BaseController
{
    private TenantService $service;

    public function __construct()
    {
        $this->service = new TenantService();
    }

    /**
     * 租户列表
     * @Apidoc\Title("租户列表")
     * @Apidoc\Desc("按状态/公司过滤的租户列表，倒序")
     * @Apidoc\Url("/admin/v1/platform/tenant/list")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="status", type="int", desc="状态过滤: 0=待开通 1=启用 2=停用 3=到期，缺省全部")
     * @Apidoc\Param(name="company_id", type="string", desc="公司ID(hashid或数字)，缺省全部")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="data.list=租户行数组；data.total=行数")
     */
    public function list(Request $request): Response
    {
        $query = Tenant::query();
        $status = $request->input('status', '');
        if ($status !== '' && in_array((int) $status, [0, 1, 2, 3], true)) {
            $query->where('status', (int) $status);
        }
        $companyInput = $request->input('company_id', '');
        if ($companyInput !== '') {
            $companyId = $this->decodeCompanyId($companyInput);
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }
        }

        $items = $query->orderByDesc('id')->get()
            ->map(fn (Tenant $t): array => $this->row($t))
            ->all();

        return $this->success(['list' => $items, 'total' => count($items)]);
    }

    /**
     * 开通租户（创建即启用）
     * @Apidoc\Title("开通租户")
     * @Apidoc\Desc("公司 1:1 开通租户：plan/套餐 + 到期日，创建即 status=1 启用")
     * @Apidoc\Url("/admin/v1/platform/tenant/provision")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="company_id", type="string", desc="公司ID(hashid或数字)，必填，一个公司至多一个租户")
     * @Apidoc\Param(name="tenant_code", type="string", desc="租户编码(2-50位字母/数字/_/-)，必填且全局唯一")
     * @Apidoc\Param(name="plan", type="int", desc="套餐: 1=标准 2=专业 3=旗舰，必填")
     * @Apidoc\Param(name="expire_at", type="string", desc="到期日 Y-m-d，必填且不早于今天")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     */
    public function provision(Request $request): Response
    {
        $companyId = $this->decodeCompanyId((string) $request->input('company_id', ''));
        if ($companyId === null) {
            return $this->fail('公司不能为空', 422);
        }

        [$tenant, $error] = $this->service->provision([
            'company_id' => $companyId,
            'tenant_code' => (string) $request->input('tenant_code', ''),
            'plan' => $request->input('plan'),
            'expire_at' => (string) $request->input('expire_at', ''),
            'remark' => (string) $request->input('remark', ''),
            'created_by' => (int) $request->input('created_by', 0),
        ]);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->row($tenant), '租户开通成功');
    }

    /**
     * 停用租户
     * @Apidoc\Title("停用租户")
     * @Apidoc\Desc("仅 1启用 → 2停用")
     * @Apidoc\Url("/admin/v1/platform/tenant/suspend")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="id", type="string", desc="租户ID(hashid)，必填")
     */
    public function suspend(Request $request): Response
    {
        [$tenant, $error] = $this->service->suspend($this->tenantId($request));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->row($tenant), '租户已停用');
    }

    /**
     * 恢复启用租户
     * @Apidoc\Title("恢复启用租户")
     * @Apidoc\Desc("仅 2停用 → 1启用；到期(3)恢复须走续费(renew)")
     * @Apidoc\Url("/admin/v1/platform/tenant/resume")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="id", type="string", desc="租户ID(hashid)，必填")
     */
    public function resume(Request $request): Response
    {
        [$tenant, $error] = $this->service->resume($this->tenantId($request));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->row($tenant), '租户已恢复启用');
    }

    /**
     * 标记租户到期
     * @Apidoc\Title("标记租户到期")
     * @Apidoc\Desc("1启用/2停用 → 3到期（重复标记拒绝）")
     * @Apidoc\Url("/admin/v1/platform/tenant/expire-mark")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="id", type="string", desc="租户ID(hashid)，必填")
     */
    public function expireMark(Request $request): Response
    {
        [$tenant, $error] = $this->service->expireMark($this->tenantId($request));
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->row($tenant), '租户已标记到期');
    }

    /**
     * 租户续费（叠加天数）
     * @Apidoc\Title("租户续费")
     * @Apidoc\Desc("到期日向后叠加 N 天；已到期(3)续费自动恢复启用，停用(2)续费仅延长期限")
     * @Apidoc\Url("/admin/v1/platform/tenant/renew")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="id", type="string", desc="租户ID(hashid)，必填")
     * @Apidoc\Param(name="days", type="int", desc="续费天数 1-3650，必填")
     */
    public function renew(Request $request): Response
    {
        [$tenant, $error] = $this->service->renew(
            $this->tenantId($request),
            (int) $request->input('days', 0)
        );
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        return $this->success($this->row($tenant), '租户续费成功');
    }

    /**
     * 到期预警列表
     * @Apidoc\Title("到期预警")
     * @Apidoc\Desc("启用中且 N 天内到期的租户（含今天与边界日），到期日升序")
     * @Apidoc\Url("/admin/v1/platform/tenant/expiry-warnings")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("平台管理")
     * @Apidoc\Param(name="days", type="int", desc="预警窗口天数 1-365，缺省 30")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("data", type="object", desc="data.list=预警租户行数组")
     */
    public function expiryWarnings(Request $request): Response
    {
        [$rows, $error] = $this->service->expiryWarnings((int) $request->input('days', 30));
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $rows = array_map(
            fn (array $r): array => $this->encodeIds($r, ['id', 'company_id']),
            $rows
        );

        return $this->success(['list' => $rows, 'total' => count($rows)]);
    }

    /** 租户行输出形状（id/company_id 编码为 hashid） */
    private function row(Tenant $tenant): array
    {
        return $this->encodeIds([
            'id' => (int) $tenant->id,
            'tenant_code' => $tenant->tenant_code,
            'company_id' => (int) $tenant->company_id,
            'plan' => (int) $tenant->plan,
            'status' => (int) $tenant->status,
            'expire_at' => (string) $tenant->expire_at,
            'opened_at' => $tenant->opened_at === null ? null : (string) $tenant->opened_at,
            'remark' => (string) $tenant->remark,
        ], ['id', 'company_id']);
    }

    /** 解码请求中的租户 id（hashid），无效返回 0（服务层报「租户不存在」） */
    private function tenantId(Request $request): int
    {
        $id = $this->decodeIdSafe((string) $request->input('id', ''));

        return $id ?? 0;
    }

    /** company_id 双解码：数字原样 / hashid 解码；无效返回 null */
    private function decodeCompanyId(string $input): ?int
    {
        if ($input === '') {
            return null;
        }
        if (is_numeric($input)) {
            $id = (int) $input;

            return $id > 0 ? $id : null;
        }

        $id = $this->decodeIdSafe($input);

        return $id !== null && $id > 0 ? $id : null;
    }
}
