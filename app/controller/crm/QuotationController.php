<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("CRM")
 */
declare(strict_types=1);

namespace app\controller\crm;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\CrmQuotation;
use app\model\CrmQuotationItem;
use app\service\crm\CrmService;
use support\Container;
use support\Request;
use support\Response;

class QuotationController extends BaseController
{
    /**
     * CRM报价列表（分页）
     * @Apidoc\Title("报价列表")
     * @Apidoc\Desc("分页查询CRM报价记录")
     * @Apidoc\Url("/admin/v1/crm/quotation")
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

        $result = $this->crm()->list(CrmQuotation::class, [
            'keyword' => $keyword,
            'status' => $status,
            'customer_id' => $customerId,
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status', 'customer_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建CRM报价
     * @Apidoc\Title("创建报价")
     * @Apidoc\Desc("新增CRM报价记录，含报价明细")
     * @Apidoc\Url("/admin/v1/crm/quotation")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="customer_id", type="int", desc="客户ID，必填")
     * @Apidoc\Param(name="items", type="array", desc="报价明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['customer_id' => 'required|integer']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->crm()->create(CrmQuotation::class, $request->all(), ['status' => 0]);

        $items = $request->input('items', []);
        if (is_array($items)) {
            $this->crm()->replaceItems(CrmQuotationItem::class, 'quotation_id', $item->id, $items);
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * CRM报价详情
     * @Apidoc\Title("报价详情")
     * @Apidoc\Desc("查看CRM报价详细信息")
     * @Apidoc\Url("/admin/v1/crm/quotation/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="报价ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmQuotation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新CRM报价
     * @Apidoc\Title("更新报价")
     * @Apidoc\Desc("修改CRM报价信息，仅草稿状态可编辑")
     * @Apidoc\Url("/admin/v1/crm/quotation/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="报价ID")
     * @Apidoc\Param(name="items", type="array", desc="报价明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmQuotation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ($item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        $item = $this->crm()->update(CrmQuotation::class, $id, $request->all());

        $items = $request->input('items', []);
        if (!empty($items)) {
            $this->crm()->replaceItems(CrmQuotationItem::class, 'quotation_id', $id, $items);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除CRM报价
     * @Apidoc\Title("删除报价")
     * @Apidoc\Desc("删除CRM报价记录，需密码确认")
     * @Apidoc\Url("/admin/v1/crm/quotation/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="报价ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmQuotation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmQuotation::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 报价转合同
     * @Apidoc\Title("报价转合同")
     * @Apidoc\Desc("将CRM报价转为正式合同，复制报价明细到合同明细")
     * @Apidoc\Url("/admin/v1/crm/quotation/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("CRM")
     * @Apidoc\Param(name="id", type="string", desc="报价ID")
     * @Apidoc\Param(name="code", type="string", desc="合同编号")
     * @Apidoc\Param(name="name", type="string", desc="合同名称")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="报价和合同数据")
     */
    public function toContract(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $quotation = $this->crm()->find(CrmQuotation::class, $id);
        if (!$quotation) {
            return $this->fail('报价不存在', 404);
        }

        $result = $this->crm()->convertQuotationToContract(
            $quotation,
            (string) $request->input('code', ''),
            (string) $request->input('name', ''),
            (string) $request->input('remark', '')
        );

        return $this->success([
            'quotation' => $this->encodeIds($result['quotation']->toArray()),
            'contract' => $this->encodeIds($result['contract']->toArray()),
        ], '报价已转为合同');
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }
}
