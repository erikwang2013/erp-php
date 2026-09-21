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

#[\erikwang2013\apidoc\annotation\Title('预算')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class BudgetController extends BaseController
{
    /**
     * 预算列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('预算列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询预算记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/budget')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', desc:'关键词')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Param(name:'period_year', type:'int', desc:'预算年度')]
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
            'period_year' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'cost_center_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建预算
     */
    #[\erikwang2013\apidoc\annotation\Title('创建预算')]
    #[\erikwang2013\apidoc\annotation\Desc('新增预算记录，含预算明细')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/budget')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'预算名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'period_year', type:'int', desc:'预算年度，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'预算明细列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:200',
            'period_year' => 'required|integer',
            'items' => 'array',
            // 明细逐项校验：period_month 是明细表唯一 NOT NULL 无默认的非 id 列，缺了直接 422
            // （不用 between/min/max：本仓 zh_CN 翻译只有扁平的 max 键，Laravel 取的是
            //  validation.max.string 这类嵌套键，回显出来是键名，越界判断放循环里手写文案）
            'items.*.period_month' => 'required|integer',
            'items.*.budget_amount' => 'nullable|numeric',
            'items.*.actual_amount' => 'nullable|numeric',
            'items.*.remark' => 'nullable|string|max:200',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceBudget();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 新建一律草稿，客户端不可直建已审批状态
        $item->status = 0;
        $item->save();

        // 保存预算明细（白名单写入，见 prepareItems）
        [$rows, $error] = $this->prepareItems($request->input('items', []));
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        foreach ($rows as $row) {
            $detail = new FinanceBudgetItem();
            $detail->id = $this->generateId();
            $detail->fill(['budget_id' => $item->id] + $row);
            $detail->save();
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 校验并规范化预算明细：原实现是 `$detail->$k = $v` 对客户端任意键直写模型（只挡了 id），
     * 既可能写幻列（未知列 1054），也可能让 NOT NULL 无默认列落空（1364）。
     * 返回 [可写入的行数组, 错误消息|null]，校验全部通过前调用方不得删旧明细。
     */
    private function prepareItems(array $items): array
    {
        $rows = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                return [[], $this->trans('Invalid budget item')];
            }
            // period_month 是明细表唯一 NOT NULL 无默认的非 id 列
            $month = (int) ($it['period_month'] ?? 0);
            if ($month < 1 || $month > 12) {
                return [[], $this->trans('Invalid period_month (1-12)')];
            }
            // account_id 可缺省（列 NOT NULL DEFAULT 0）；传了就是 hashid/原生数字双模
            $accountRaw = $it['account_id'] ?? '';
            $accountId = $accountRaw === '' ? 0 : $this->decodeFlexibleId($accountRaw);
            if ($accountId === null) {
                return [[], $this->trans('Invalid account')];
            }
            $rows[] = [
                'account_id' => $accountId,
                'period_month' => $month,
                'budget_amount' => $it['budget_amount'] ?? 0,
                'actual_amount' => $it['actual_amount'] ?? 0,
                'remark' => (string) ($it['remark'] ?? ''),
            ];
        }

        return [$rows, null];
    }

    /**
     * 预算详情
     */
    #[\erikwang2013\apidoc\annotation\Title('预算详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看预算详细信息，含预算明细')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'预算ID')]
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
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $items = FinanceBudgetItem::where('budget_id', $id)->orderBy('period_month', 'asc')->get()
            ->map(fn ($it) => $this->encodeIds($it->toArray()));
        $data = $this->encodeIds($item->toArray());
        $data['items'] = $items;

        return $this->success($data);
    }

    /**
     * 更新预算
     */
    #[\erikwang2013\apidoc\annotation\Title('更新预算')]
    #[\erikwang2013\apidoc\annotation\Desc('修改预算记录，仅草稿状态可编辑')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'预算ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'预算名称')]
    #[\erikwang2013\apidoc\annotation\Param(name:'items', type:'array', desc:'预算明细列表')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'name' => 'string',
            'items' => 'array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        if ((int) $item->status !== 0) {
            return $this->fail($this->trans('Only draft records can be edited'), 422);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        // 更新明细：先删后建（同一份白名单；校验在删除之前完成，非法明细不会先清空旧数据）
        $items = $request->input('items', []);
        if (!empty($items)) {
            [$rows, $error] = $this->prepareItems($items);
            if ($error !== null) {
                return $this->fail($error, 422);
            }
            FinanceBudgetItem::where('budget_id', $id)->delete();
            foreach ($rows as $row) {
                $detail = new FinanceBudgetItem();
                $detail->id = $this->generateId();
                $detail->fill(['budget_id' => $id] + $row);
                $detail->save();
            }
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除预算
     */
    #[\erikwang2013\apidoc\annotation\Title('删除预算')]
    #[\erikwang2013\apidoc\annotation\Desc('删除预算记录，需密码确认，连明细一起删除')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'预算ID')]
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
        $item = FinanceBudget::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        FinanceBudgetItem::where('budget_id', $id)->delete();
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 预算执行对比
     */
    #[\erikwang2013\apidoc\annotation\Title('预算执行对比')]
    #[\erikwang2013\apidoc\annotation\Desc('预算 vs 实际执行对比分析')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'预算ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'对比分析数据')]

    public function comparison(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $budget = FinanceBudget::find($id);
        if (!$budget) {
            return $this->fail($this->trans('Budget not found'), 404);
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
            $percent = bccomp(bc_norm($it->budget_amount), '0', 4) > 0
                ? bc_round(bcmul(bcdiv(bc_norm($it->actual_amount), bc_norm($it->budget_amount), 4), '100', 6), 2)
                : '0';
            $row['variance'] = $variance;
            $row['execution_rate'] = $percent;
            $rows[] = $row;

            $totalBudget = bcadd($totalBudget, (string) $it->budget_amount, 2);
            $totalActual = bcadd($totalActual, (string) $it->actual_amount, 2);
        }

        $totalVariance = bcsub($totalActual, $totalBudget, 2);
        $totalRate = bccomp($totalBudget, '0', 4) > 0
            ? bc_round(bcmul(bcdiv($totalActual, $totalBudget, 4), '100', 6), 2)
            : '0';

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
