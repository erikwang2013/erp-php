<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\Customer;
use app\model\CrmPoolRecord;
use app\model\CrmPoolRule;
use support\Request;
use support\Response;

class PoolController extends BaseController
{
    /**
     * 入口分发：根据 URI 判断是公海池客户列表还是规则列表
     * GET /admin/crm/pool        — 公海池客户
     * GET /admin/crm/pool/rules  — 公海池规则（resource 路由复用此方法）
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

        // 公海池客户：status=0 或 owner_user_id=0（无归属人）
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 领取客户
     * POST /admin/crm/pool/claim/{id}
     */
    public function claim(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $customer = Customer::find($id);
        if (!$customer) return $this->fail('客户不存在', 404);

        $adminId = $request->adminId ?? 0;

        // 检查客户是否在公海池
        if ($customer->status !== 0 && $customer->owner_user_id !== 0) {
            return $this->fail('该客户不在公海池中', 422);
        }

        // 检查领取上限
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

        // 记录操作
        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $id;
        $record->action = 1; // 领取
        $record->from_user_id = 0;
        $record->to_user_id = $adminId;
        $record->remark = $request->input('remark', '');
        $record->save();

        return $this->success($this->encodeIds($customer->toArray()), '领取成功');
    }

    /**
     * 释放客户到公海池
     * POST /admin/crm/pool/release/{id}
     */
    public function release(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $customer = Customer::find($id);
        if (!$customer) return $this->fail('客户不存在', 404);

        $adminId = $request->adminId ?? 0;

        $fromUserId = $customer->owner_user_id;
        $customer->owner_user_id = 0;
        $customer->status = 0;
        $customer->save();

        // 记录操作
        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $id;
        $record->action = 2; // 释放
        $record->from_user_id = $fromUserId;
        $record->to_user_id = 0;
        $record->remark = $request->input('remark', '');
        $record->save();

        return $this->success($this->encodeIds($customer->toArray()), '释放成功');
    }

    /**
     * 公海池规则列表
     * 由 index() 根据 URI 派发调用
     */
    private function rules(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $query = CrmPoolRule::query();
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建公海池规则
     * POST /admin/crm/pool/rules
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['level_id' => 'required|integer']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new CrmPoolRule();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 公海池规则详情
     * GET /admin/crm/pool/rules/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新公海池规则
     * PUT /admin/crm/pool/rules/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除公海池规则
     * DELETE /admin/crm/pool/rules/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmPoolRule::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
