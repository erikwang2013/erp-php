<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrEmployee;
use app\model\HrSalary;
use app\model\HrSalaryItem;
use support\Request;
use support\Response;

/**
 * 薪资与薪资项管理
  * @Apidoc\Tag("人力资源")
 */
class SalaryController extends BaseController
{
    // ============================================================
    // 薪资管理
    // ============================================================

    /**
     * 薪资列表（分页）
     * GET /admin/hr/salary
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $periodYear = $request->input('period_year');
        $periodMonth = $request->input('period_month');
        $status = $request->input('status');

        $query = HrSalary::with(['employee']);
        if ($employeeId) {
            $query->where('employee_id', (int) $employeeId);
        }
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
            ->limit($limit)->orderBy('period_year', 'desc')
            ->orderBy('period_month', 'desc')
            ->get()->map(function ($item) {
                $data = $item->toArray();
                if ($item->relationLoaded('employee') && $item->employee) {
                    $data['employee'] = $this->encodeIds($item->employee->toArray());
                }
                return $this->encodeIds($data);
            });

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建薪资记录
     * POST /admin/hr/salary
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'employee_id' => 'required|integer',
            'period_year' => 'required|integer',
            'period_month' => 'required|integer',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        // 检查是否已存在
        $exists = HrSalary::where('employee_id', (int) $request->input('employee_id'))
            ->where('period_year', (int) $request->input('period_year'))
            ->where('period_month', (int) $request->input('period_month'))
            ->exists();
        if ($exists) return $this->fail('该员工当月薪资记录已存在', 422);

        $item = new HrSalary();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }

        // 自动计算实发: base + performance + overtime - deduction - tax
        $item->net_salary = ($item->base_salary ?? 0)
            + ($item->performance ?? 0)
            + ($item->overtime ?? 0)
            - ($item->deduction ?? 0)
            - ($item->tax ?? 0);

        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 薪资详情
     * GET /admin/hr/salary/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::with(['employee'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if ($item->relationLoaded('employee') && $item->employee) {
            $data['employee'] = $this->encodeIds($item->employee->toArray());
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新薪资
     * PUT /admin/hr/salary/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 1) return $this->fail('已发放的薪资不可修改', 422);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }

        // 重新计算实发
        $item->net_salary = ($item->base_salary ?? 0)
            + ($item->performance ?? 0)
            + ($item->overtime ?? 0)
            - ($item->deduction ?? 0)
            - ($item->tax ?? 0);

        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除薪资记录
     * DELETE /admin/hr/salary/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 1) return $this->fail('已发放的薪资不可删除', 422);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 薪资发放确认
     * POST /admin/hr/salary/{id}/pay
     */
    public function pay(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status === 1) return $this->fail('该薪资已发放', 422);

        $item->status = 1;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '薪资已发放');
    }

    /**
     * 批量生成薪资
     * POST /admin/hr/salary（带 batch=1 参数）
     */
    public function batchGenerate(Request $request): Response
    {
        $periodYear = (int) $request->input('period_year', (int) date('Y'));
        $periodMonth = (int) $request->input('period_month', (int) date('m'));
        $departmentId = $request->input('department_id');

        $employees = HrEmployee::where('status', 1);
        if ($departmentId) {
            $employees->where('department_id', (int) $departmentId);
        }
        $employees = $employees->get();

        $created = 0;
        foreach ($employees as $emp) {
            $exists = HrSalary::where('employee_id', $emp->id)
                ->where('period_year', $periodYear)
                ->where('period_month', $periodMonth)
                ->exists();
            if ($exists) continue;

            $salary = new HrSalary();
            $salary->id = $this->generateId();
            $salary->employee_id = $emp->id;
            $salary->period_year = $periodYear;
            $salary->period_month = $periodMonth;
            $salary->status = 0;
            $salary->net_salary = 0;
            $salary->save();
            $created++;
        }

        return $this->success(['created' => $created], "批量生成完成，共 {$created} 条");
    }

    // ============================================================
    // 薪资项管理
    // ============================================================

    /**
     * 薪资项列表
     * GET /admin/hr/salary-item（通过请求路径判断）
     */
    public function itemIndex(Request $request): Response
    {
        $list = HrSalaryItem::orderBy('id', 'asc')->get()->map(fn($item) => $this->encodeIds($item->toArray()));
        return $this->success(['list' => $list]);
    }

    /**
     * 创建薪资项
     * POST /admin/hr/salary-item
     */
    public function itemStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new HrSalaryItem();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 薪资项详情
     * GET /admin/hr/salary-item/{id}
     */
    public function itemShow(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新薪资项
     * PUT /admin/hr/salary-item/{id}
     */
    public function itemUpdate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除薪资项
     * DELETE /admin/hr/salary-item/{id}
     */
    public function itemDestroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
