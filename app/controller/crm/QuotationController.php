<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\crm;

use app\admin\controller\BaseController;
use app\model\CrmQuotation;
use app\model\CrmQuotationItem;
use app\model\Customer;
use app\service\crm\CrmService;
use support\Container;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('报价')]
#[\erikwang2013\apidoc\annotation\Group('CRM')]

class QuotationController extends BaseController
{
    /**
     * CRM报价列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('报价列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询CRM报价记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/quotation')]
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

        $result = $this->crm()->list(CrmQuotation::class, [
            'keyword' => $keyword,
            'status' => $status,
            'customer_id' => $customerId,
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status', 'customer_id'],
        ]);
        // FK 编码为 hashid（与客户下拉选项同源，供编辑弹窗回填）+ 客户名称展示（表无 name 列）
        $list = array_map(fn ($item) => $this->encodeIds($item, ['id', 'customer_id', 'opportunity_id']), $result['list']);
        $customerIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['customer_id'] ?? 0), $list)));
        $customerNames = Customer::whereIn('id', $customerIds)->pluck('name', 'id');
        $list = array_map(function ($row) use ($customerNames) {
            $row['customer_name'] = (string) ($customerNames[(int) ($row['customer_id'] ?? 0)] ?? '');

            return $row;
        }, $list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建CRM报价
     */
    #[\erikwang2013\apidoc\annotation\Title('创建报价')]
    #[\erikwang2013\apidoc\annotation\Desc('新增CRM报价记录，含报价明细')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/crm/quotation')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'int', desc:'客户ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'报价明细列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        // 校验真实表列（表无 name 列；页面幻键经 $fillable 静默过滤）。
        // customer_id 必填且为 hashid/原生数字双模（原 required|integer 拒绝 hashid → 422）
        $data = $request->all();
        $validator = validator($data, ['customer_id' => 'required|string', 'items' => 'array']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        foreach (['customer_id' => '客户ID', 'opportunity_id' => '商机ID'] as $field => $label) {
            $raw = (string) ($data[$field] ?? '');
            if ($raw === '') {
                if ($field === 'customer_id') {
                    return $this->fail($label . '无效', 422);
                }
                unset($data[$field]);
                continue;
            }
            $decoded = $this->decodeFlexibleId($raw);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $data[$field] = $decoded;
        }
        // 单号留空自动生成（uk_code 唯一）；负责人 NOT NULL 无默认 → 当前登录管理员
        $code = (string) ($data['code'] ?? '');
        if ($code === '') {
            $data['code'] = 'QT' . date('YmdHis');
        }
        $data['owner_user_id'] = (int) ($request->adminId ?? 0);
        // 可空/可缺省列：空串按缺省处理（'' 不直插 DATE/DECIMAL）
        foreach (['total_amount', 'remark', 'quoted_at', 'valid_until'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->create(CrmQuotation::class, $data, ['status' => 0]);

        $items = $request->input('items', []);
        if (is_array($items)) {
            $this->crm()->replaceItems(CrmQuotationItem::class, 'quotation_id', $item->id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'opportunity_id']), '创建成功');
    }

    /**
     * CRM报价详情
     */
    #[\erikwang2013\apidoc\annotation\Title('报价详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看CRM报价详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'报价ID')]
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
        $item = $this->crm()->find(CrmQuotation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'opportunity_id']));
    }

    /**
     * 更新CRM报价
     */
    #[\erikwang2013\apidoc\annotation\Title('更新报价')]
    #[\erikwang2013\apidoc\annotation\Desc('修改CRM报价信息，仅草稿状态可编辑')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'报价ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'报价明细列表')]
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
        $item = $this->crm()->find(CrmQuotation::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ((int) $item->status !== 0) {
            return $this->fail($this->trans('Only draft records can be edited'), 422);
        }

        $data = $request->all();
        foreach (['customer_id' => '客户ID', 'opportunity_id' => '商机ID'] as $field => $label) {
            $raw = $data[$field] ?? '';
            if ($raw === '') {
                unset($data[$field]);
                continue;
            }
            $decoded = $this->decodeFlexibleId((string) $raw);
            if ($decoded === null || $decoded < 1) {
                return $this->fail($label . '无效', 422);
            }
            $data[$field] = $decoded;
        }
        foreach (['total_amount', 'remark', 'quoted_at', 'valid_until'] as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                unset($data[$field]);
            }
        }

        $item = $this->crm()->update(CrmQuotation::class, $id, $data);

        $items = $request->input('items', []);
        if (!empty($items)) {
            $this->crm()->replaceItems(CrmQuotationItem::class, 'quotation_id', $id, $items);
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'customer_id', 'opportunity_id']), '更新成功');
    }

    /**
     * 删除CRM报价
     */
    #[\erikwang2013\apidoc\annotation\Title('删除报价')]
    #[\erikwang2013\apidoc\annotation\Desc('删除CRM报价记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'报价ID')]
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
     */
    #[\erikwang2013\apidoc\annotation\Title('报价转合同')]
    #[\erikwang2013\apidoc\annotation\Desc('将CRM报价转为正式合同，复制报价明细到合同明细')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('CRM')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'报价ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'合同编号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'合同名称')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', desc:'备注')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'报价和合同数据')]

    public function toContract(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'code' => 'string',
            'name' => 'string',
            'remark' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $quotation = $this->crm()->find(CrmQuotation::class, $id);
        if (!$quotation) {
            return $this->fail($this->trans('Quotation not found'), 404);
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
