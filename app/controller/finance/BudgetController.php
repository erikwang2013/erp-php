<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
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
     * @Apidoc\Title("预算列表")
     * @Apidoc\Desc("分页查询预算记录")
     * @Apidoc\Url("/admin/finance/budget")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="period_year", type="int", desc="预算年度")
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建预算
     * @Apidoc\Title("创建预算")
     * @Apidoc\Desc("新增预算记录，含预算明细")
     * @Apidoc\Url("/admin/finance/budget")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="name", type="string", desc="预算名称，必填")
     * @Apidoc\Param(name="period_year", type="int", desc="预算年度，必填")
     * @Apidoc\Param(name="items", type="array", desc="预算明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'period_year' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceBudget();
        $item->id = $this->generateId();
        $item->status = 0; // 草稿
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') {
                $item->$k = $v;
            }
        }
        $item->save();

        // 保存预算明细
        $items = $request->input('items', []);
        foreach ($items as $it) {
            $detail = new FinanceBudgetItem();
            $detail->id = $this->generateId();
            $detail->budget_id = $item->id;
            foreach ($it as $k => $v) {
                if ($k !== 'id') {
                    $detail->$k = $v;
                }
            }
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 预算详情
     * @Apidoc\Title("预算详情")
     * @Apidoc\Desc("查看预算详细信息，含预算明细")
     * @Apidoc\Url("/admin/finance/budget/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="预算ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $items = FinanceBudgetItem::where('budget_id', $id)->orderBy('period_month', 'asc')->get()
            ->map(fn ($it) => $this->encodeIds($it->toArray()));
        $data = $this->encodeIds($item->toArray());
        $data['items'] = $items;

        return $this->success($data);
    }

    /**
     * 更新预算
     * @Apidoc\Title("更新预算")
     * @Apidoc\Desc("修改预算记录，仅草稿状态可编辑")
     * @Apidoc\Url("/admin/finance/budget/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="预算ID")
     * @Apidoc\Param(name="name", type="string", desc="预算名称")
     * @Apidoc\Param(name="items", type="array", desc="预算明细列表")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        if ((int) $item->status !== 0) {
            return $this->fail('仅草稿状态可编辑', 422);
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id' && $k !== 'items') {
                $item->$k = $v;
            }
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
                    if ($k !== 'id') {
                        $detail->$k = $v;
                    }
                }
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除预算
     * @Apidoc\Title("删除预算")
     * @Apidoc\Desc("删除预算记录，需密码确认，连明细一起删除")
     * @Apidoc\Url("/admin/finance/budget/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="预算ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        FinanceBudgetItem::where('budget_id', $id)->delete();
        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 预算执行对比
     * @Apidoc\Title("预算执行对比")
     * @Apidoc\Desc("预算 vs 实际执行对比分析")
     * @Apidoc\Url("/admin/finance/budget/{id}/comparison")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="预算ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="对比分析数据")
     */
    public function comparison(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $budget = FinanceBudget::find($id);
        if (!$budget) {
            return $this->fail('预算不存在', 404);
        }

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
