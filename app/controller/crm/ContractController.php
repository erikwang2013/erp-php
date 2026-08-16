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
use app\service\crm\CrmService;
use InvalidArgumentException;
use support\Container;
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

        $result = $this->crm()->list(CrmContract::class, [
            'keyword' => $keyword,
            'status' => $status,
            'customer_id' => $customerId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status', 'customer_id'],
            'with' => ['items'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
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

        $item = $this->crm()->create(CrmContract::class, $request->all(), ['status' => 0], false);

        $items = $request->input('items', []);
        if (is_array($items)) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $item->id, $items);
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
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id, ['items']);
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
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        $item = $this->crm()->update(CrmContract::class, $id, $request->all());

        $items = $request->input('items', []);
        if (!empty($items)) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $id, $items);
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
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmContract::class, $id);

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
    public function transition(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $toStatus = (int) $request->input('to_status', -1);

        try {
            $item = $this->crm()->transitionContract($id, $toStatus);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '状态更新成功');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
