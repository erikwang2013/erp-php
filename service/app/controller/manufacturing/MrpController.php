<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\Inventory;
use app\model\MfgBom;
use app\model\MfgBomItem;
use app\model\MfgMrpPlan;
use app\model\MfgMrpItem;
use support\Request;
use support\Response;

/**
 * MRP计划管理 — 计划生成 + 列表
 */
class MrpController extends BaseController
{
    /**
     * MRP计划列表（分页）
     * GET /admin/mfg/mrp
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建MRP计划头
     * POST /admin/mfg/mrp
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'period_year' => 'required|integer',
            'period_month' => 'required|integer',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new MfgMrpPlan();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * MRP计划详情（含明细）
     * GET /admin/mfg/mrp/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::with(['items'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn($i) => $this->encodeIds($i), $data['items']);
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新MRP计划
     * PUT /admin/mfg/mrp/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 2) return $this->fail('已确认的计划不可修改', 422);

        $originalStatus = $item->status;
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = $originalStatus; // Status can only change via generate()
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除MRP计划
     * DELETE /admin/mfg/mrp/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgMrpPlan::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        MfgMrpItem::where('plan_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 生成MRP计划明细 — 基于各产品的BOM与库存计算净需求
     * POST /admin/mfg/mrp/{id}/generate
     */
    public function generate(Request $request, string $hashid): Response
    {
        $planId = $this->decodeId($hashid);
        $plan = MfgMrpPlan::find($planId);
        if (!$plan) return $this->fail('计划不存在', 404);
        if ($plan->status === 2) return $this->fail('已确认的计划不可重新生成', 422);

        // 清除旧明细
        MfgMrpItem::where('plan_id', $planId)->delete();

        // 遍历所有已生效BOM
        $boms = MfgBom::where('status', 1)->with(['items'])->get();

        foreach ($boms as $bom) {
            foreach ($bom->items as $bomItem) {
                // 计算净需求: net = gross - scheduled_receipt - on_hand
                // gross: 按BOM用量计算
                $grossRequirement = (float) $bomItem->quantity;

                // 查询当前库存
                $inventory = Inventory::where('product_id', $bomItem->component_product_id)->first();
                $onHand = $inventory ? (float) $inventory->quantity : 0.00;

                $netRequirement = $grossRequirement - $onHand;
                if ($netRequirement < 0) $netRequirement = 0;

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

        $plan->status = 1; // 已生成
        $plan->generated_at = date('Y-m-d H:i:s');
        $plan->save();

        $itemCount = MfgMrpItem::where('plan_id', $planId)->count();
        return $this->success(['items_count' => $itemCount], "MRP计划生成完成，共 {$itemCount} 条明细");
    }
}
