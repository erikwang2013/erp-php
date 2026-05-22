<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmContract;
use app\model\CrmContractItem;
use support\Request;
use support\Response;

class ContractController extends BaseController
{
    /**
     * 合同列表（分页）
     * GET /admin/crm/contract
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $customerId = $request->input('customer_id');

        $query = CrmContract::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($customerId !== null && $customerId !== '') {
            $query->where('customer_id', (int) $customerId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->with('items')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建合同
     * POST /admin/crm/contract
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'customer_id' => 'required|integer']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new CrmContract();
        $item->id = $this->generateId();
        $item->status = 0; // 草稿
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') $item->$k = $v;
        }
        $item->save();

        // 保存合同明细
        $items = $request->input('items', []);
        foreach ($items as $it) {
            $detail = new CrmContractItem();
            $detail->id = $this->generateId();
            $detail->contract_id = $item->id;
            foreach ($it as $k => $v) {
                if ($k !== 'id') $detail->$k = $v;
            }
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 合同详情
     * GET /admin/crm/contract/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::with('items')->find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新合同
     * PUT /admin/crm/contract/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        // 草稿状态才能编辑
        if ($item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') $item->$k = $v;
        }
        $item->save();

        // 更新明细：先删后建
        $items = $request->input('items', []);
        if (!empty($items)) {
            CrmContractItem::where('contract_id', $id)->delete();
            foreach ($items as $it) {
                $detail = new CrmContractItem();
                $detail->id = $this->generateId();
                $detail->contract_id = $id;
                foreach ($it as $k => $v) {
                    if ($k !== 'id') $detail->$k = $v;
                }
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除合同
     * DELETE /admin/crm/contract/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 状态流转
     * POST /admin/crm/contract/{id}/transition
     * body: { "to_status": 1 }
     *
     * 状态定义: 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止
     */
    public function transition(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $toStatus = (int) $request->input('to_status', -1);
        $currentStatus = (int) $item->status;

        // 允许的状态流转
        $allowedTransitions = [
            0 => [1],           // 草稿 -> 待审批
            1 => [2, 0],        // 待审批 -> 已审批/退回草稿
            2 => [3],           // 已审批 -> 执行中
            3 => [4, 5],        // 执行中 -> 已完成/已终止
            4 => [],            // 已完成，不可流转
            5 => [],            // 已终止，不可流转
        ];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($toStatus, $allowedTransitions[$currentStatus])) {
            return $this->fail("不允许从状态 {$currentStatus} 流转到 {$toStatus}", 422);
        }

        $item->status = $toStatus;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '状态更新成功');
    }
}
