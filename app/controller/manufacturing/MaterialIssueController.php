<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgMaterialIssue;
use app\model\MfgMaterialIssueItem;
use app\model\MfgProductionOrder;
use app\model\ProductSku;
use app\service\manufacturing\MfgCostService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 领料单管理 — CRUD + 审核（出库）
 */
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Title("领料单")]
#[\erikwang2013\apidoc\annotation\Group("生产制造")]

class MaterialIssueController extends BaseController
{
    /**
     * 领料单列表（分页）
     */
#[\erikwang2013\apidoc\annotation\Title("领料单列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/material-issue")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", desc:"编码关键词")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态 0草稿/1已审核")]
#[\erikwang2013\apidoc\annotation\Param(name:"order_id", type:"int", desc:"生产工单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->cost()->list(MfgMaterialIssue::class, [
            'keyword' => $request->input('keyword', ''),
            'status' => $request->input('status'),
            'order_id' => $request->input('order_id'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['order_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建领料单（草稿，含明细）
     */
#[\erikwang2013\apidoc\annotation\Title("创建领料单")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/mfg/material-issue")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"领料单编码，必填，唯一")]
#[\erikwang2013\apidoc\annotation\Param(name:"order_id", type:"int", desc:"生产工单ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"issue_date", type:"string", desc:"领料日期 Y-m-d，默认当天")]
#[\erikwang2013\apidoc\annotation\Param(name:"warehouse_id", type:"int", desc:"出库仓库ID，缺省取工单仓库")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'order_id' => 'required|integer',
            'issue_date' => 'nullable|date',
            'warehouse_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.sku_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 数量>0 与 SKU 归属前置校验（bcmath，出库前不允许负数/不存在物料）
        $items = [];
        foreach ((array) $request->input('items', []) as $i => $row) {
            $qty = bc_norm((string) ($row['quantity'] ?? '0'));
            if (bccomp($qty, '0', 4) <= 0) {
                return $this->fail('明细第' . ($i + 1) . '行领料数量必须大于0', 422);
            }
            $sku = ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->first();
            if (!$sku) {
                return $this->fail('明细第' . ($i + 1) . '行SKU不存在', 422);
            }
            $items[] = ['sku_id' => (int) $sku->id, 'product_id' => (int) $sku->product_id, 'quantity' => $qty];
        }
        $order = MfgProductionOrder::query()->where('id', (int) $request->input('order_id'))->first();
        if (!$order) {
            return $this->fail('生产工单不存在', 422);
        }
        $warehouseId = (int) ($request->input('warehouse_id') !== null ? $request->input('warehouse_id') : $order->warehouse_id);

        $id = $this->generateId();
        try {
            DB::transaction(function () use ($request, $items, $warehouseId, $id, $order) {
                $doc = new MfgMaterialIssue();
                $doc->id = $id;
                $doc->code = trim((string) $request->input('code'));
                $doc->order_id = (int) $order->id;
                $doc->warehouse_id = $warehouseId;
                $doc->issue_date = $request->input('issue_date') ?: date('Y-m-d');
                $doc->remark = (string) $request->input('remark', '');
                $doc->status = 0;
                $doc->total_cost = '0';
                $doc->save();
                foreach ($items as $row) {
                    $item = new MfgMaterialIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->sku_id = $row['sku_id'];
                    $item->product_id = $row['product_id'];
                    $item->quantity = $row['quantity'];
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        } catch (QueryException $e) {
            if ($this->cost()->isDuplicateKey($e)) {
                return $this->fail('领料单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), '创建成功');
    }

    /**
     * 领料单详情
     */
#[\erikwang2013\apidoc\annotation\Title("领料单详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"领料单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->cost()->find(MfgMaterialIssue::class, $id, ['items', 'order']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }
        if ($item->relationLoaded('order') && $item->order) {
            $data['order'] = $this->encodeIds($item->order->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新领料单（仅草稿：表头字段 + 整单替换明细）
     */
#[\erikwang2013\apidoc\annotation\Title("更新领料单")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"领料单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgMaterialIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的领料单不可修改', 422);
        }
        $data = $request->all();
        unset($data['code'], $data['order_id'], $data['status']);
        $item = $this->cost()->update(MfgMaterialIssue::class, $id, $data, ['status', 'total_cost', 'audit_at']);

        $rawItems = $request->input('items');
        if (is_array($rawItems)) {
            $rows = [];
            foreach ($rawItems as $i => $row) {
                $qty = bc_norm((string) ($row['quantity'] ?? '0'));
                if (bccomp($qty, '0', 4) <= 0) {
                    return $this->fail('明细第' . ($i + 1) . '行领料数量必须大于0', 422);
                }
                $sku = ProductSku::query()->where('id', (int) ($row['sku_id'] ?? 0))->first();
                if (!$sku) {
                    return $this->fail('明细第' . ($i + 1) . '行SKU不存在', 422);
                }
                $rows[] = ['sku_id' => (int) $sku->id, 'product_id' => (int) $sku->product_id, 'quantity' => $qty];
            }
            if ($rows === []) {
                return $this->fail('明细不能为空', 422);
            }
            DB::transaction(function () use ($rows, $id) {
                MfgMaterialIssueItem::query()->where('issue_id', $id)->delete();
                foreach ($rows as $row) {
                    $item = new MfgMaterialIssueItem();
                    $item->id = $this->generateId();
                    $item->issue_id = $id;
                    $item->sku_id = $row['sku_id'];
                    $item->product_id = $row['product_id'];
                    $item->quantity = $row['quantity'];
                    $item->unit_cost = '0';
                    $item->amount = '0';
                    $item->save();
                }
            });
        }

        $doc = MfgMaterialIssue::query()->with('items')->where('id', $id)->first();
        $data = $doc->toArray();
        $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items'] ?? []);

        return $this->success($this->encodeIds($data), '更新成功');
    }

    /**
     * 删除领料单（仅草稿，需密码确认）
     */
#[\erikwang2013\apidoc\annotation\Title("删除领料单")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"领料单ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgMaterialIssue::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的领料单不可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        DB::transaction(function () use ($id) {
            MfgMaterialIssueItem::query()->where('issue_id', $id)->delete();
            MfgMaterialIssue::query()->where('id', $id)->delete();
        });

        return $this->success([], '删除成功');
    }

    /**
     * 审核领料单（出库扣减库存并归集 WIP 材料成本）
     */
#[\erikwang2013\apidoc\annotation\Title("审核领料单")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("生产制造")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"领料单ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]

    public function audit(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $item = $this->cost()->auditIssue($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '审核成功，已出库');
    }

    /** 成本核算服务 */
    private function cost(): MfgCostService
    {
        return Container::get(MfgCostService::class);
    }
}
