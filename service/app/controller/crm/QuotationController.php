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
use app\model\CrmQuotation;
use app\model\CrmQuotationItem;
use support\Request;
use support\Response;

class QuotationController extends BaseController
{
    /**
     * CRM报价列表（分页）
     * GET /admin/crm/quotation
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $customerId = $request->input('customer_id');

        $query = CrmQuotation::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建CRM报价
     * POST /admin/crm/quotation
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['customer_id' => 'required|integer']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new CrmQuotation();
        $item->id = $this->generateId();
        $item->status = 0; // 草稿
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') $item->$k = $v;
        }
        $item->save();

        // 保存报价明细
        $items = $request->input('items', []);
        foreach ($items as $it) {
            $detail = new CrmQuotationItem();
            $detail->id = $this->generateId();
            $detail->quotation_id = $item->id;
            foreach ($it as $k => $v) {
                if ($k !== 'id') $detail->$k = $v;
            }
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * CRM报价详情
     * GET /admin/crm/quotation/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmQuotation::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新CRM报价
     * PUT /admin/crm/quotation/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmQuotation::find($id);
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
            CrmQuotationItem::where('quotation_id', $id)->delete();
            foreach ($items as $it) {
                $detail = new CrmQuotationItem();
                $detail->id = $this->generateId();
                $detail->quotation_id = $id;
                foreach ($it as $k => $v) {
                    if ($k !== 'id') $detail->$k = $v;
                }
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除CRM报价
     * DELETE /admin/crm/quotation/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = CrmQuotation::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 报价转合同
     * POST /admin/crm/quotation/{id}/to-contract
     */
    public function toContract(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $quotation = CrmQuotation::find($id);
        if (!$quotation) return $this->fail('报价不存在', 404);

        // 创建合同
        $contract = new CrmContract();
        $contract->id = $this->generateId();
        $contract->code = $request->input('code', '') ?: 'CT' . date('YmdHis');
        $contract->name = $request->input('name', '') ?: '合同-' . $quotation->code;
        $contract->customer_id = $quotation->customer_id;
        $contract->opportunity_id = $quotation->opportunity_id;
        $contract->quotation_id = $id;
        $contract->total_amount = $quotation->total_amount;
        $contract->status = 0; // 草稿
        $contract->owner_user_id = $quotation->owner_user_id;
        $contract->remark = $request->input('remark', '');
        $contract->save();

        // 复制报价明细到合同明细
        $qItems = CrmQuotationItem::where('quotation_id', $id)->get();
        foreach ($qItems as $qItem) {
            $cItem = new CrmContractItem();
            $cItem->id = $this->generateId();
            $cItem->contract_id = $contract->id;
            $cItem->product_id = $qItem->product_id;
            $cItem->sku_id = $qItem->sku_id;
            $cItem->quantity = $qItem->quantity;
            $cItem->price = $qItem->price;
            $cItem->amount = $qItem->amount;
            $cItem->unit = $qItem->unit;
            $cItem->save();
        }

        // 更新报价状态为已转合同
        $quotation->status = 3; // 已转合同
        $quotation->save();

        return $this->success([
            'quotation' => $this->encodeIds($quotation->toArray()),
            'contract' => $this->encodeIds($contract->toArray()),
        ], '报价已转为合同');
    }
}
