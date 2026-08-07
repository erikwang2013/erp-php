<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmPoolRecord;
use app\model\CrmPoolRule;
use app\model\Customer;
use support\Request;
use support\Response;

class PoolController extends BaseController
{
    /**
     * 公海池入口
     * @Apidoc\Title("公海池客户列表")
     * @Apidoc\Desc("分页查询公海池客户记录(status=0或无归属人)")
     * @Apidoc\Url("/admin/crm/pool")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="level_id", type="int", desc="客户等级ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $uri = $request->uri();
        if (str_contains($uri, '/pool/rules')) {
            return $this->rules($request);
        }

        return $this->poolCustomers($request);
    }

    /**
     * 公海池客户列表
     */
    private function poolCustomers(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $levelId = $request->input('level_id');

        $query = Customer::where(function ($q) {
            $q->where('status', 0)->orWhere('owner_user_id', 0);
        });

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($levelId !== null && $levelId !== '') {
            $query->where('level_id', (int) $levelId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 领取客户
     * @Apidoc\Title("领取客户")
     * @Apidoc\Desc("从公海池领取客户到当前用户名下")
     * @Apidoc\Url("/admin/crm/pool/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="客户ID")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function claim(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $customer = Customer::find($id);
        if (!$customer) {
            return $this->fail('客户不存在', 404);
        }

        $adminId = $request->adminId ?? 0;

        if ($customer->status !== 0 && $customer->owner_user_id !== 0) {
            return $this->fail('该客户不在公海池中', 422);
        }

        $rule = CrmPoolRule::where('level_id', $customer->level_id)
            ->where('enabled', 1)
            ->first();
        if ($rule) {
            $claimed = Customer::where('owner_user_id', $adminId)
                ->where('status', '>', 0)
                ->count();
            if ($claimed >= $rule->max_claims) {
                return $this->fail('已达到最大领取数量限制(' . $rule->max_claims . ')', 422);
            }
        }

        $customer->owner_user_id = $adminId;
        $customer->status = 1;
        $customer->save();

        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $id;
        $record->action = 1;
        $record->from_user_id = 0;
        $record->to_user_id = $adminId;
        $record->remark = $request->input('remark', '');
        $record->save();

        return $this->success($this->encodeIds($customer->toArray()), '领取成功');
    }

    /**
     * 释放客户到公海池
     * @Apidoc\Title("释放客户")
     * @Apidoc\Desc("将客户释放回公海池")
     * @Apidoc\Url("/admin/crm/pool/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="客户ID")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function release(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $customer = Customer::find($id);
        if (!$customer) {
            return $this->fail('客户不存在', 404);
        }

        $adminId = $request->adminId ?? 0;

        $fromUserId = $customer->owner_user_id;
        $customer->owner_user_id = 0;
        $customer->status = 0;
        $customer->save();

        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $id;
        $record->action = 2;
        $record->from_user_id = $fromUserId;
        $record->to_user_id = 0;
        $record->remark = $request->input('remark', '');
        $record->save();

        return $this->success($this->encodeIds($customer->toArray()), '释放成功');
    }

    /**
     * 公海池规则列表
     */
    public function rules(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = CrmPoolRule::query();
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建公海池规则
     * @Apidoc\Title("创建公海池规则")
     * @Apidoc\Desc("新增公海池规则记录")
     * @Apidoc\Url("/admin/crm/pool")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="level_id", type="int", desc="客户等级ID，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['level_id' => 'required|integer']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new CrmPoolRule();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 公海池规则详情
     * @Apidoc\Title("公海池规则详情")
     * @Apidoc\Desc("查看公海池规则详细信息")
     * @Apidoc\Url("/admin/crm/pool/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="规则ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新公海池规则
     * @Apidoc\Title("更新公海池规则")
     * @Apidoc\Desc("修改公海池规则信息")
     * @Apidoc\Url("/admin/crm/pool/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="规则ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除公海池规则
     * @Apidoc\Title("删除公海池规则")
     * @Apidoc\Desc("删除公海池规则记录，需密码确认")
     * @Apidoc\Url("/admin/crm/pool/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="规则ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }
}
