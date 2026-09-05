<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgSubcontract;
use app\model\MfgSubcontractIssue;
use app\model\MfgSubcontractReceive;
use app\model\ProductSku;
use app\model\Supplier;
use app\service\manufacturing\SubcontractService;
use Illuminate\Database\QueryException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 委外加工订单（P1-M2）

 * 状态机：0草稿 → 1已发料 → 2已收货 → 3已核销。
 * 状态推进不设独立审核端点：发料单/收料单审核时联动推进
 * （见 SubcontractService::auditIssue / auditReceive）。
 */
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Title("委外订单")]

class SubcontractController extends BaseController
{
    /**
     * 委外订单列表（分页，按单号/供应商/产品/状态筛选）
     */
#[\erikwang2013\apidoc\annotation\Title("委外订单列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/subcontract")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"单号模糊搜索")]
#[\erikwang2013\apidoc\annotation\Param(name:"supplier_id", type:"int", desc:"供应商ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"product_id", type:"int", desc:"委外产品ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态 0草稿 1已发料 2已收货 3已核销")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->service()->list(MfgSubcontract::class, [
            'keyword' => $request->input('keyword'),
            'supplier_id' => $request->input('supplier_id'),
            'product_id' => $request->input('product_id'),
            'status' => $request->input('status'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['supplier_id', 'product_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建委外订单（草稿）
     */
#[\erikwang2013\apidoc\annotation\Title("创建委外订单")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/subcontract")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"委外单号，必填，唯一")]
#[\erikwang2013\apidoc\annotation\Param(name:"supplier_id", type:"int", desc:"供应商ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"product_id", type:"int", desc:"委外产品ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"int", desc:"收料仓库ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"quantity", type:"number", desc:"委外数量，必填，>0")]
#[\erikwang2013\apidoc\annotation\Param(name:"unit_price", type:"number", desc:"加工单价，必填，≥0")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'supplier_id' => 'required|integer',
            'product_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'quantity' => 'required|numeric',
            'unit_price' => 'required|numeric',
            'remark' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $quantity = bc_norm((string) $request->input('quantity'));
        if (bccomp($quantity, '0', 4) <= 0) {
            return $this->fail('委外数量必须大于0', 422);
        }
        $unitPrice = bc_norm((string) $request->input('unit_price'));
        if (bccomp($unitPrice, '0', 4) < 0) {
            return $this->fail('加工单价不能为负数', 422);
        }
        if (!Supplier::query()->where('id', (int) $request->input('supplier_id'))->exists()) {
            return $this->fail('供应商不存在', 422);
        }
        if (!ProductSku::query()->where('product_id', (int) $request->input('product_id'))->exists()) {
            return $this->fail('委外产品不存在或未建SKU', 422);
        }

        $id = $this->generateId();
        try {
            $doc = new MfgSubcontract();
            $doc->id = $id;
            $doc->code = trim((string) $request->input('code'));
            $doc->supplier_id = (int) $request->input('supplier_id');
            $doc->product_id = (int) $request->input('product_id');
            $doc->warehouse_id = (int) $request->input('warehouse_id');
            $doc->quantity = (float) $quantity;
            $doc->unit_price = (float) bc_round($unitPrice, 2);
            $doc->remark = (string) $request->input('remark', '');
            $doc->status = 0;
            $doc->save();
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('委外单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), '创建成功');
    }

    /**
     * 委外订单详情（含供应商与发料/收料单）
     */
#[\erikwang2013\apidoc\annotation\Title("委外订单详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"委外订单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = $this->service()->find(MfgSubcontract::class, $id, ['supplier', 'issues', 'receives']);
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        $data = $doc->toArray();
        foreach (['issues', 'receives'] as $rel) {
            if (isset($data[$rel])) {
                $data[$rel] = array_map(fn ($i) => $this->encodeIds($i), $data[$rel]);
            }
        }
        if ($doc->relationLoaded('supplier') && $doc->supplier) {
            $data['supplier'] = $this->encodeIds($doc->supplier->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新委外订单（仅草稿）
     */
#[\erikwang2013\apidoc\annotation\Title("更新委外订单")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"委外订单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontract::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('委外订单已发料，不可修改', 422);
        }
        $data = $request->all();
        unset($data['code'], $data['status']);
        if (isset($data['quantity'])) {
            $data['quantity'] = (float) bc_norm((string) $data['quantity']);
        }
        if (isset($data['unit_price'])) {
            $data['unit_price'] = (float) bc_round(bc_norm((string) $data['unit_price']), 2);
        }
        try {
            $updated = $this->service()->update(MfgSubcontract::class, $id, $data, [
                'code', 'status', 'amount', 'issued_amount', 'received_qty', 'consumed_amount', 'audit_at',
            ]);
        } catch (QueryException $e) {
            if ($this->service()->isDuplicateKey($e)) {
                return $this->fail('委外单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds($updated->toArray()), '更新成功');
    }

    /**
     * 删除委外订单（仅草稿且无关联单据，需密码确认）
     */
#[\erikwang2013\apidoc\annotation\Title("删除委外订单")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"委外订单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgSubcontract::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('委外订单已发料，不可删除', 422);
        }
        if (MfgSubcontractIssue::query()->where('subcontract_id', $id)->exists()
            || MfgSubcontractReceive::query()->where('subcontract_id', $id)->exists()) {
            return $this->fail('存在关联发料单或收料单，不可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        MfgSubcontract::query()->where('id', $id)->delete();

        return $this->success([], '删除成功');
    }

    /** 委外服务 */
    private function service(): SubcontractService
    {
        return Container::get(SubcontractService::class);
    }
}
