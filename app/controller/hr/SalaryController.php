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
    // 薪资管理

    /**
     * 薪资列表（分页）
     * @Apidoc\Title("薪资列表")
     * @Apidoc\Desc("分页查询薪资记录")
     * @Apidoc\Url("/admin/hr/salary")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID")
     * @Apidoc\Param(name="period_year", type="int", desc="薪资年度")
     * @Apidoc\Param(name="period_month", type="int", desc="薪资月份")
     * @Apidoc\Param(name="status", type="int", desc="状态:0未发放1已发放")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
     * @Apidoc\Title("创建薪资记录")
     * @Apidoc\Desc("新增薪资记录，自动计算实发金额")
     * @Apidoc\Url("/admin/hr/salary")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID，必填")
     * @Apidoc\Param(name="period_year", type="int", desc="薪资年度，必填")
     * @Apidoc\Param(name="period_month", type="int", desc="薪资月份，必填")
     * @Apidoc\Param(name="base_salary", type="float", desc="基本工资")
     * @Apidoc\Param(name="performance", type="float", desc="绩效工资")
     * @Apidoc\Param(name="overtime", type="float", desc="加班费")
     * @Apidoc\Param(name="deduction", type="float", desc="扣款")
     * @Apidoc\Param(name="tax", type="float", desc="个税")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'employee_id' => 'required|integer',
            'period_year' => 'required|integer',
            'period_month' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $exists = HrSalary::where('employee_id', (int) $request->input('employee_id'))
            ->where('period_year', (int) $request->input('period_year'))
            ->where('period_month', (int) $request->input('period_month'))
            ->exists();
        if ($exists) {
            return $this->fail('该员工当月薪资记录已存在', 422);
        }

        $item = new HrSalary();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);

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
     * @Apidoc\Title("薪资详情")
     * @Apidoc\Desc("查看薪资记录详细信息")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::with(['employee'])->find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $data = $item->toArray();
        if ($item->relationLoaded('employee') && $item->employee) {
            $data['employee'] = $this->encodeIds($item->employee->toArray());
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新薪资
     * @Apidoc\Title("更新薪资")
     * @Apidoc\Desc("修改薪资记录，自动重新计算实发，已发放不可修改")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 1) {
            return $this->fail('已发放的薪资不可修改', 422);
        }

        $this->fillModelFromRequest($item, $request);

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
     * @Apidoc\Title("删除薪资记录")
     * @Apidoc\Desc("删除薪资记录，已发放不可删除，需密码确认")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 1) {
            return $this->fail('已发放的薪资不可删除', 422);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 薪资发放确认
     * @Apidoc\Title("薪资发放")
     * @Apidoc\Desc("确认薪资已发放，将状态更新为已发放")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function pay(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalary::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        if ($item->status === 1) {
            return $this->fail('该薪资已发放', 422);
        }

        $item->status = 1;
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '薪资已发放');
    }

    /**
     * 批量生成薪资
     * @Apidoc\Title("批量生成薪资")
     * @Apidoc\Desc("按部门和期间为所有在职员工批量生成初始薪资记录")
     * @Apidoc\Url("/admin/hr/salary")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="period_year", type="int", desc="薪资年度")
     * @Apidoc\Param(name="period_month", type="int", desc="薪资月份")
     * @Apidoc\Param(name="department_id", type="int", desc="部门ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
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
            if ($exists) {
                continue;
            }

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

    // 薪资项管理

    /**
     * 薪资项列表
     * @Apidoc\Title("薪资项列表")
     * @Apidoc\Desc("查询全部薪资项配置")
     * @Apidoc\Url("/admin/hr/salary")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function itemIndex(Request $request): Response
    {
        $list = HrSalaryItem::orderBy('id', 'asc')->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * 创建薪资项
     * @Apidoc\Title("创建薪资项")
     * @Apidoc\Desc("新增薪资项配置")
     * @Apidoc\Url("/admin/hr/salary")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="code", type="string", desc="薪资项编码，必填")
     * @Apidoc\Param(name="name", type="string", desc="薪资项名称，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function itemStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new HrSalaryItem();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 薪资项详情
     * @Apidoc\Title("薪资项详情")
     * @Apidoc\Desc("查看薪资项详细信息")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资项ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function itemShow(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新薪资项
     * @Apidoc\Title("更新薪资项")
     * @Apidoc\Desc("修改薪资项配置")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资项ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function itemUpdate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除薪资项
     * @Apidoc\Title("删除薪资项")
     * @Apidoc\Desc("删除薪资项配置，需密码确认")
     * @Apidoc\Url("/admin/hr/salary/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("人力资源")
     * @Apidoc\Param(name="id", type="string", desc="薪资项ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function itemDestroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = HrSalaryItem::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], '删除成功');
    }
}
