<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
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

#[\erikwang2013\apidoc\annotation\Title('合同')]
#[\erikwang2013\apidoc\annotation\Group('CRM')]

class ContractController extends BaseController
{
    /**
     * 合同列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('合同列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询合同记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/contract')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'int', desc:'客户ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
            'customer_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        // 筛选值来自前端客户下拉（hashid）：不解码则 eqFilters 里 (int)hashid=0，筛选恒不命中
        $customerId = $request->input('customer_id');
        if ($customerId !== null && $customerId !== '') {
            $customerId = $this->decodeFlexibleId($customerId);
            if ($customerId === null || $customerId < 1) {
                return $this->fail('客户ID' . $this->trans('Invalid'), 422);
            }
        }

        $result = $this->crm()->list(CrmContract::class, [
            'keyword' => $keyword,
            'status' => $status,
            'customer_id' => $customerId,
        ], $page, $limit, [
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['status', 'customer_id'],
            'with' => ['items'],
        ]);
        // 白名单须含嵌套明细外键（contract_id/product_id/sku_id）：$fields 非空时不再自动识别 id/*_id，
        // 漏列即逐字返回裸数字（含 items[].*）
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'customer_id', 'owner_user_id', 'quotation_id', 'opportunity_id', 'contract_id', 'product_id', 'sku_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建合同
     */
    #[\erikwang2013\apidoc\annotation\Title('创建合同')]
    #[\erikwang2013\apidoc\annotation\Desc('新增合同记录，含合同明细')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/contract')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'合同名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'int', desc:'客户ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'合同明细列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200', 'customer_id' => 'required|string', 'items' => 'array']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $data = $this->normalizeFkData($request->all());
        if ($data === null) {
            return $this->fail('客户ID/负责人ID' . $this->trans('Invalid'), 422);
        }
        // erp_crm_contract.owner_user_id NOT NULL 无默认；请求未指定负责人时归属当前操作人
        $data['owner_user_id'] = $data['owner_user_id'] ?? ($request->adminId ?? 0);
        // code uk_code 唯一；留空自动生成，避免空串二次插入 1062
        if (trim((string) ($data['code'] ?? '')) === '') {
            $data['code'] = 'CT' . $this->generateId();
        }
        // 明细外键在 create 之前校验（非法则 422 且不落主表，避免半写）
        $items = $request->input('items', []);
        $items = is_array($items) && $items !== [] ? $this->decodeItemIds($items, ['product_id', 'sku_id']) : [];
        if ($items === null) {
            return $this->fail('明细商品ID' . $this->trans('Invalid'), 422);
        }
        $item = $this->crm()->create(CrmContract::class, $data, ['status' => 0], false);

        if ($items !== []) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $item->id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'owner_user_id', 'quotation_id', 'opportunity_id', 'contract_id', 'product_id', 'sku_id']), $this->trans('Created successfully'));
    }

    /**
     * 合同详情
     */
    #[\erikwang2013\apidoc\annotation\Title('合同详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看合同详细信息，含合同明细')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'合同ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id, ['items']);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'owner_user_id', 'quotation_id', 'opportunity_id', 'contract_id', 'product_id', 'sku_id']));
    }

    /**
     * 更新合同
     */
    #[\erikwang2013\apidoc\annotation\Title('更新合同')]
    #[\erikwang2013\apidoc\annotation\Desc('修改合同信息，仅草稿状态可编辑')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'合同ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'合同明细列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'items' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        if ($item->status !== 0) {
            return $this->fail($this->trans('Only draft records can be edited'), 422);
        }

        $data = $this->normalizeFkData($request->all());
        if ($data === null) {
            return $this->fail('客户ID/负责人ID' . $this->trans('Invalid'), 422);
        }
        // 明细外键先校验再落库，避免主表已改、明细未改的半写
        $items = $request->input('items', []);
        if (!empty($items)) {
            $items = $this->decodeItemIds((array) $items, ['product_id', 'sku_id']);
            if ($items === null) {
                return $this->fail('明细商品ID' . $this->trans('Invalid'), 422);
            }
        }

        $item = $this->crm()->update(CrmContract::class, $id, $data);

        if (!empty($items)) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'owner_user_id', 'quotation_id', 'opportunity_id', 'contract_id', 'product_id', 'sku_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除合同
     */
    #[\erikwang2013\apidoc\annotation\Title('删除合同')]
    #[\erikwang2013\apidoc\annotation\Desc('删除合同记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'合同ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = $this->crm()->find(CrmContract::class, $id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->crm()->delete(CrmContract::class, $id);

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 合同状态流转
     */
    #[\erikwang2013\apidoc\annotation\Title('合同状态流转')]
    #[\erikwang2013\apidoc\annotation\Desc('推进合同状态: 0草稿 1待审批 2已审批 3执行中 4已完成 5已终止')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'合同ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to_status', type:'int', desc:'目标状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function transition(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'to_status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $toStatus = (int) $request->input('to_status', -1);

        try {
            $item = $this->crm()->transitionContract($id, $toStatus);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'quotation_id']), $this->trans('Status updated successfully'));
    }

    /**
     * CRM 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function crm(): CrmService
    {
        return Container::get(CrmService::class);
    }

    /**
     * FK 兼容解码归一：customer_id/owner_user_id 接受 hashid 或裸 int → int 落库；
     * 非法值（非空但解不出）返回 null 由调用方 422（原 decodeIdSafe ?? (int) 会把垃圾串静默写成 0）；
     * 空串/0 按"未指定"移除（'' 直插 BIGINT 严格模式 1366 → 500）。
     * code 留空时移除该键（保留库内原值，避免空串覆写 uk_code）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function normalizeFkData(array $data): ?array
    {
        foreach (['customer_id', 'owner_user_id'] as $fk) {
            if (!isset($data[$fk]) || $data[$fk] === '') {
                unset($data[$fk]);
                continue;
            }
            $decoded = $this->decodeFlexibleId($data[$fk]);
            if ($decoded === null) {
                return null;
            }
            if ($decoded < 1) {
                unset($data[$fk]);
                continue;
            }
            $data[$fk] = $decoded;
        }
        if (isset($data['code']) && trim((string) $data['code']) === '') {
            unset($data['code']);
        }

        return $data;
    }
}
