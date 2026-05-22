<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceBudget;
use app\model\FinanceBudgetItem;
use support\Request;
use support\Response;

class BudgetController extends BaseController
{
    /**
     * 预算列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $periodYear = $request->input('period_year');

        $query = FinanceBudget::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($periodYear) {
            $query->where('period_year', (int) $periodYear);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建预算
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'period_year' => 'required|integer',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinanceBudget();
        $item->id = $this->generateId();
        $item->status = 0; // 草稿
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') $item->$k = $v;
        }
        $item->save();

        // 保存预算明细
        $items = $request->input('items', []);
        foreach ($items as $it) {
            $detail = new FinanceBudgetItem();
            $detail->id = $this->generateId();
            $detail->budget_id = $item->id;
            foreach ($it as $k => $v) {
                if ($k !== 'id') $detail->$k = $v;
            }
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 预算详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $items = FinanceBudgetItem::where('budget_id', $id)->orderBy('period_month', 'asc')->get()
            ->map(fn($it) => $this->encodeIds($it->toArray()));
        $data = $this->encodeIds($item->toArray());
        $data['items'] = $items;

        return $this->success($data);
    }

    /**
     * 更新预算（仅草稿可编辑）
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        if ((int) $item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') $item->$k = $v;
        }
        $item->save();

        // 更新明细：先删后建
        $items = $request->input('items', []);
        if (!empty($items)) {
            FinanceBudgetItem::where('budget_id', $id)->delete();
            foreach ($items as $it) {
                $detail = new FinanceBudgetItem();
                $detail->id = $this->generateId();
                $detail->budget_id = $id;
                foreach ($it as $k => $v) {
                    if ($k !== 'id') $detail->$k = $v;
                }
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除预算
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        FinanceBudgetItem::where('budget_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 预算执行对比 — 预算 vs 实际
     * GET /admin/finance/budget/{id}/comparison
     */
    public function comparison(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $budget = FinanceBudget::find($id);
        if (!$budget) return $this->fail('预算不存在', 404);

        $items = FinanceBudgetItem::where('budget_id', $id)
            ->orderBy('period_month', 'asc')
            ->get();

        $totalBudget = '0';
        $totalActual = '0';
        $rows = [];

        foreach ($items as $it) {
            $row = $this->encodeIds($it->toArray());
            $variance = bcsub((string) $it->actual_amount, (string) $it->budget_amount, 2);
            $percent = $it->budget_amount > 0
                ? round(bcdiv((string) $it->actual_amount, (string) $it->budget_amount, 4) * 100, 2)
                : 0;
            $row['variance'] = $variance;
            $row['execution_rate'] = $percent;
            $rows[] = $row;

            $totalBudget = bcadd($totalBudget, (string) $it->budget_amount, 2);
            $totalActual = bcadd($totalActual, (string) $it->actual_amount, 2);
        }

        $totalVariance = bcsub($totalActual, $totalBudget, 2);
        $totalRate = (float) $totalBudget > 0
            ? round(bcdiv($totalActual, $totalBudget, 4) * 100, 2)
            : 0;

        return $this->success([
            'budget' => $this->encodeIds($budget->toArray()),
            'items' => $rows,
            'summary' => [
                'total_budget' => $totalBudget,
                'total_actual' => $totalActual,
                'total_variance' => $totalVariance,
                'execution_rate' => $totalRate,
            ],
        ]);
    }
}
