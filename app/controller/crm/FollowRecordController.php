<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\CrmFollowRecord;
use app\model\Customer;
use app\service\crm\CrmService;
use support\Container;
use support\Request;
use support\Response;
#[\erikwang2013\apidoc\annotation\Title("跟进记录")]
#[\erikwang2013\apidoc\annotation\Group("CRM")]

class FollowRecordController extends BaseController
{
    /**
     * 跟进记录列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("跟进记录列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询跟进记录记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/follow")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $customerId = $request->input('customer_id');

        // 表无 name/code/status 列（erp_crm_follow_record：customer_id/method/content 等），
        // 原按幻列的关键词搜索/状态筛选整体移除，仅保留客户维度筛选
        $result = $this->crm()->list(CrmFollowRecord::class, [
            'customer_id' => $customerId,
        ], $page, $limit, [
            'eqFilters' => ['customer_id'],
        ]);
        // FK 编码为 hashid（与客户下拉选项同源，供编辑弹窗回填）+ 引用名展示
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'customer_id', 'contact_id', 'opportunity_id', 'follow_user_id']), $result['list']);

        $customerIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['customer_id'] ?? 0), $list)));
        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id');
        $userIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['follow_user_id'] ?? 0), $list)));
        $userNames = AdminUser::whereIn('id', $userIds)->pluck('real_name', 'id');
        $list = array_map(function ($row) use ($customerNames, $userNames) {
            $row['customer_name'] = (string) ($customerNames[(int) ($row['customer_id'] ?? 0)] ?? '');
            $row['follow_user_name'] = (string) ($userNames[(int) ($row['follow_user_id'] ?? 0)] ?? '');
            return $row;
        }, $list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建跟进记录
     */
#[\erikwang2013\apidoc\annotation\Title("创建跟进记录")]
#[\erikwang2013\apidoc\annotation\Desc("新增跟进记录记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/crm/follow")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"跟进记录名称，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 校验真实表列（表无 name/code/status 列，页面幻键经 $fillable 静默过滤）
        $data = $request->all();
        // customer_id/follow_user_id 均 NOT NULL 无默认：hashid/原生数字双模解码，垃圾串 422 拒绝
        foreach (['customer_id' => '客户ID', 'follow_user_id' => '跟进人ID'] as $field => $label) {
            $raw = $data[$field] ?? '';
            if ($field === 'follow_user_id' && ($raw === '' || $raw === null)) {
                // 客户端未指定跟进人时默认当前登录管理员
                $data[$field] = (int) ($request->adminId ?? 0);
                continue;
            }
            $decoded = $this->decodeFlexibleId((string) $raw);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $data[$field] = $decoded;
        }
        // 可空/可缺省列：空串按缺省处理（'' 不直插 DATE/TEXT/VARCHAR）
        foreach (['contact_id', 'opportunity_id', 'method', 'content', 'next_plan', 'next_follow_at', 'followed_at'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->create(CrmFollowRecord::class, $data);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'contact_id', 'opportunity_id', 'follow_user_id']), '创建成功');
    }

    /**
     * 跟进记录详情
     */
#[\erikwang2013\apidoc\annotation\Title("跟进记录详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看跟进记录详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"跟进记录ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmFollowRecord::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'contact_id', 'opportunity_id', 'follow_user_id']));
    }

    /**
     * 更新跟进记录
     */
#[\erikwang2013\apidoc\annotation\Title("更新跟进记录")]
#[\erikwang2013\apidoc\annotation\Desc("修改跟进记录信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"跟进记录ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmFollowRecord::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $request->all();
        // 跟进人仅在显式传值时允许修改（hashid/原生数字双模解码）
        foreach (['customer_id' => '客户ID', 'follow_user_id' => '跟进人ID'] as $field => $label) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $decoded = $this->decodeFlexibleId((string) $data[$field]);
                if ($decoded === null || $decoded < 1) {
                    return $this->fail($label . '无效', 422);
                }
                $data[$field] = $decoded;
            } else {
                unset($data[$field]);
            }
        }
        foreach (['contact_id', 'opportunity_id', 'method', 'content', 'next_plan', 'next_follow_at', 'followed_at'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->update(CrmFollowRecord::class, $id, $data);

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'contact_id', 'opportunity_id', 'follow_user_id']), '更新成功');
    }

    /**
     * 删除跟进记录
     */
#[\erikwang2013\apidoc\annotation\Title("删除跟进记录")]
#[\erikwang2013\apidoc\annotation\Desc("删除跟进记录记录，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("CRM")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"跟进记录ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmFollowRecord::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmFollowRecord::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
