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
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'customer_id', 'owner_user_id', 'quotation_id']), $result['list']);

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
        // erp_crm_contract.owner_user_id NOT NULL 无默认；请求未指定负责人时归属当前操作人
        $data['owner_user_id'] = $data['owner_user_id'] ?? ($request->adminId ?? 0);
        // code uk_code 唯一；留空自动生成，避免空串二次插入 1062
        if (trim((string) ($data['code'] ?? '')) === '') {
            $data['code'] = 'CT' . $this->generateId();
        }
        $item = $this->crm()->create(CrmContract::class, $data, ['status' => 0], false);

        $items = $request->input('items', []);
        if (is_array($items)) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $item->id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'owner_user_id', 'quotation_id']), '创建成功');
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
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'owner_user_id', 'quotation_id']));
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
            return $this->fail('记录不存在', 404);
        }

        if ($item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        $item = $this->crm()->update(CrmContract::class, $id, $this->normalizeFkData($request->all()));

        $items = $request->input('items', []);
        if (!empty($items)) {
            $this->crm()->replaceItems(CrmContractItem::class, 'contract_id', $id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'quotation_id']), '更新成功');
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
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'quotation_id']), '状态更新成功');
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
     * code 留空时移除该键（保留库内原值，避免空串覆写 uk_code）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeFkData(array $data): array
    {
        foreach (['customer_id', 'owner_user_id'] as $fk) {
            if (isset($data[$fk]) && $data[$fk] !== '') {
                $data[$fk] = $this->decodeIdSafe((string) $data[$fk]) ?? (int) $data[$fk];
            }
        }
        if (isset($data['code']) && trim((string) $data['code']) === '') {
            unset($data['code']);
        }

        return $data;
    }
}
