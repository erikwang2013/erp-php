<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\Inventory;
use app\model\MfgBom;
use app\model\MfgMrpItem;
use app\model\MfgMrpPlan;
use support\Request;
use support\Response;

/**
 * MRP计划管理 — 计划生成 + 列表
  * @Apidoc\Tag("生产制造")
 */
class MrpController extends BaseController
{
    /**
     * MRP计划列表（分页）
     * @Apidoc\Title("MRP计划列表")
     * @Apidoc\Desc("分页查询MRP计划记录")
     * @Apidoc\Url("/admin/mfg/mrp")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="period_year", type="int", desc="计划年度")
     * @Apidoc\Param(name="period_month", type="int", desc="计划月份")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $periodYear = $request->input('period_year');
        $periodMonth = $request->input('period_month');
        $status = $request->input('status');

        $query = MfgMrpPlan::query();
        if ($periodYear) {
            $query->where('period_year', (int) $periodYear);
        }
        if ($periodMonth) {
            $query->where('period_month', (int) $periodMonth);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建MRP计划头
     * @Apidoc\Title("创建MRP计划")
     * @Apidoc\Desc("新增MRP计划头记录")
     * @Apidoc\Url("/admin/mfg/mrp")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="计划编码，必填")
     * @Apidoc\Param(name="period_year", type="int", desc="计划年度，必填")
     * @Apidoc\Param(name="period_month", type="int", desc="计划月份，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'period_year' => 'required|integer',
            'period_month' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new MfgMrpPlan();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * MRP计划详情
     * @Apidoc\Title("MRP计划详情")
     * @Apidoc\Desc("查看MRP计划详细信息，含明细")
     * @Apidoc\Url("/admin/mfg/mrp/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::with(['items'])->find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn ($i) => $this->encodeIds($i), $data['items']);
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新MRP计划
     * @Apidoc\Title("更新MRP计划")
     * @Apidoc\Desc("修改MRP计划，已确认不可修改")
     * @Apidoc\Url("/admin/mfg/mrp/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 2) {
            return $this->fail('已确认的计划不可修改', 422);
        }

        $originalStatus = $item->status;
        $this->fillModelFromRequest($item, $request);
        $item->status = $originalStatus;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除MRP计划
     * @Apidoc\Title("删除MRP计划")
     * @Apidoc\Desc("删除MRP计划，连明细一起删除，需密码确认")
     * @Apidoc\Url("/admin/mfg/mrp/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        MfgMrpItem::where('plan_id', $id)->delete();
        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 生成MRP计划明细
     * @Apidoc\Title("生成MRP明细")
     * @Apidoc\Desc("基于各产品BOM与库存计算净需求，生成MRP计划明细")
     * @Apidoc\Url("/admin/mfg/mrp/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="计划ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function generate(Request $request, string $hashid): Response
    {
        $planId = $this->decodeId($hashid);
        $plan = MfgMrpPlan::find($planId);
        if (!$plan) {
            return $this->fail('计划不存在', 404);
        }
        if ($plan->status === 2) {
            return $this->fail('已确认的计划不可重新生成', 422);
        }

        MfgMrpItem::where('plan_id', $planId)->delete();

        $boms = MfgBom::where('status', 1)->with(['items'])->get();

        foreach ($boms as $bom) {
            foreach ($bom->items as $bomItem) {
                $grossRequirement = (float) $bomItem->quantity;

                $inventory = Inventory::where('product_id', $bomItem->component_product_id)->first();
                $onHand = $inventory ? (float) $inventory->quantity : 0.00;

                $netRequirement = $grossRequirement - $onHand;
                if ($netRequirement < 0) {
                    $netRequirement = 0;
                }

                $item = new MfgMrpItem();
                $item->id = $this->generateId();
                $item->plan_id = $planId;
                $item->product_id = $bomItem->component_product_id;
                $item->gross_requirement = $grossRequirement;
                $item->scheduled_receipt = 0;
                $item->on_hand = $onHand;
                $item->net_requirement = $netRequirement;
                $item->planned_order_qty = $netRequirement;
                $item->planned_start = date('Y-m-d');
                $item->planned_end = date('Y-m-d', strtotime('+7 days'));
                $item->created_at = date('Y-m-d H:i:s');
                $item->save();
            }
        }

        $plan->status = 1;
        $plan->generated_at = date('Y-m-d H:i:s');
        $plan->save();

        $itemCount = MfgMrpItem::where('plan_id', $planId)->count();

        return $this->success(['items_count' => $itemCount], "MRP计划生成完成，共 {$itemCount} 条明细");
    }
}
