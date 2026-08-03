<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
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
     * @Apidoc\Title("合同列表")
     * @Apidoc\Desc("分页查询合同记录")
     * @Apidoc\Url("/admin/crm/contract")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="customer_id", type="int", desc="客户ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建合同
     * @Apidoc\Title("创建合同")
     * @Apidoc\Desc("新增合同记录，含合同明细")
     * @Apidoc\Url("/admin/crm/contract")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="name", type="string", desc="合同名称，必填")
     * @Apidoc\Param(name="customer_id", type="int", desc="客户ID，必填")
     * @Apidoc\Param(name="items", type="array", desc="合同明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'customer_id' => 'required|integer']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new CrmContract();
        $item->id = $this->generateId();
        $item->status = 0;
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') {
                $item->$k = $v;
            }
        }
        $item->save();

        $items = $request->input('items', []);
        foreach ($items as $it) {
            $detail = new CrmContractItem();
            $detail->id = $this->generateId();
            $detail->contract_id = $item->id;
            foreach ($it as $k => $v) {
                if ($k !== 'id') {
                    $detail->$k = $v;
                }
            }
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 合同详情
     * @Apidoc\Title("合同详情")
     * @Apidoc\Desc("查看合同详细信息，含合同明细")
     * @Apidoc\Url("/admin/crm/contract/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="合同ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::with('items')->find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新合同
     * @Apidoc\Title("更新合同")
     * @Apidoc\Desc("修改合同信息，仅草稿状态可编辑")
     * @Apidoc\Url("/admin/crm/contract/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="合同ID")
     * @Apidoc\Param(name="items", type="array", desc="合同明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') {
                $item->$k = $v;
            }
        }
        $item->save();

        $items = $request->input('items', []);
        if (!empty($items)) {
            CrmContractItem::where('contract_id', $id)->delete();
            foreach ($items as $it) {
                $detail = new CrmContractItem();
                $detail->id = $this->generateId();
                $detail->contract_id = $id;
                foreach ($it as $k => $v) {
                    if ($k !== 'id') {
                        $detail->$k = $v;
                    }
                }
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除合同
     * @Apidoc\Title("删除合同")
     * @Apidoc\Desc("删除合同记录，需密码确认")
     * @Apidoc\Url("/admin/crm/contract/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="合同ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
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

    /**
     * 合同状态流转
     * @Apidoc\Title("合同状态流转")
     * @Apidoc\Desc("推进合同状态: 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止")
     * @Apidoc\Url("/admin/crm/contract/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="合同ID")
     * @Apidoc\Param(name="to_status", type="int", desc="目标状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function transition(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmContract::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $toStatus = (int) $request->input('to_status', -1);
        $currentStatus = (int) $item->status;

        $allowedTransitions = [
            0 => [1],
            1 => [2, 0],
            2 => [3],
            3 => [4, 5],
            4 => [],
            5 => [],
        ];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($toStatus, $allowedTransitions[$currentStatus])) {
            return $this->fail("不允许从状态 {$currentStatus} 流转到 {$toStatus}", 422);
        }

        $item->status = $toStatus;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '状态更新成功');
    }
}
