<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrSalary;
use app\model\HrSalaryItem;
use app\service\hr\BankPayrollService;
use app\service\hr\HrService;
use app\service\hr\PayslipService;
use app\service\hr\SalaryEngineService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 薪资与薪资项管理
 */#[\erikwang2013\apidoc\annotation\Tag("人力资源")]

class SalaryController extends BaseController
{
    // 薪资管理

    /**
     * 薪资列表（分页）
     */#[\erikwang2013\apidoc\annotation\Title("薪资列表")]
#[\erikwang2013\apidoc\annotation\Desc("分页查询薪资记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"薪资年度")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"薪资月份")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0未发放1已发放")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $employeeId = $request->input('employee_id');
        $periodYear = $request->input('period_year');
        $periodMonth = $request->input('period_month');
        $status = $request->input('status');

        $result = $this->hr()->list(HrSalary::class, [
            'employee_id' => $employeeId,
            'period_year' => $periodYear,
            'period_month' => $periodMonth,
            'status' => $status,
        ], $page, $limit, [
            'eqFilters' => ['status'],
            'truthyFilters' => ['employee_id', 'period_year', 'period_month'],
            'with' => ['employee'],
            'orderBy' => [['period_year', 'desc'], ['period_month', 'desc']],
        ]);
        $list = array_map(function ($data) {
            $data['employee'] = !empty($data['employee']) ? $this->encodeIds($data['employee']) : null;

            return $this->encodeIds($data);
        }, $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建薪资记录
     */#[\erikwang2013\apidoc\annotation\Title("创建薪资记录")]
#[\erikwang2013\apidoc\annotation\Desc("新增薪资记录，自动计算实发金额")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"employee_id", type:"int", desc:"员工ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"薪资年度，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"薪资月份，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_salary", type:"float", desc:"基本工资")]
#[\erikwang2013\apidoc\annotation\Param(name:"performance", type:"float", desc:"绩效工资")]
#[\erikwang2013\apidoc\annotation\Param(name:"overtime", type:"float", desc:"加班费")]
#[\erikwang2013\apidoc\annotation\Param(name:"deduction", type:"float", desc:"扣款")]
#[\erikwang2013\apidoc\annotation\Param(name:"tax", type:"float", desc:"个税")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

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

        try {
            $item = $this->hr()->createSalary($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 薪资详情
     */#[\erikwang2013\apidoc\annotation\Title("薪资详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看薪资记录详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrSalary::class, $id, ['employee']);
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
     */#[\erikwang2013\apidoc\annotation\Title("更新薪资")]
#[\erikwang2013\apidoc\annotation\Desc("修改薪资记录，自动重新计算实发，已发放不可修改")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);

        try {
            $item = $this->hr()->updateSalary($id, $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除薪资记录
     */#[\erikwang2013\apidoc\annotation\Title("删除薪资记录")]
#[\erikwang2013\apidoc\annotation\Desc("删除薪资记录，已发放不可删除，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrSalary::class, $id);
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

        $this->hr()->delete(HrSalary::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 薪资发放确认
     */#[\erikwang2013\apidoc\annotation\Title("薪资发放")]
#[\erikwang2013\apidoc\annotation\Desc("确认薪资已发放，将状态更新为已发放")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function pay(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);

        try {
            $item = $this->hr()->paySalary($id);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '薪资已发放');
    }

    /**
     * 批量生成薪资
     */#[\erikwang2013\apidoc\annotation\Title("批量生成薪资")]
#[\erikwang2013\apidoc\annotation\Desc("按部门和期间为所有在职员工批量生成初始薪资记录")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_year", type:"int", desc:"薪资年度")]
#[\erikwang2013\apidoc\annotation\Param(name:"period_month", type:"int", desc:"薪资月份")]
#[\erikwang2013\apidoc\annotation\Param(name:"department_id", type:"int", desc:"部门ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function batchGenerate(Request $request): Response
    {
        $periodYear = (int) $request->input('period_year', (int) date('Y'));
        $periodMonth = (int) $request->input('period_month', (int) date('m'));
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;

        $created = $this->hr()->batchGenerateSalaries($periodYear, $periodMonth, $departmentId);

        return $this->success(['created' => $created], "批量生成完成，共 {$created} 条");
    }

    /**
     * 薪资试算
     */#[\erikwang2013\apidoc\annotation\Title("薪资试算")]
#[\erikwang2013\apidoc\annotation\Desc("按基本工资/绩效/加班/计件/扣款试算个税与实发金额")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary/calculate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_salary", type:"float", desc:"基本工资")]
#[\erikwang2013\apidoc\annotation\Param(name:"performance", type:"float", desc:"绩效工资")]
#[\erikwang2013\apidoc\annotation\Param(name:"overtime", type:"float", desc:"加班费")]
#[\erikwang2013\apidoc\annotation\Param(name:"piece_wage", type:"float", desc:"计件工资（报工审核自动归集，P1-M1b）")]
#[\erikwang2013\apidoc\annotation\Param(name:"deduction", type:"float", desc:"扣款")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"试算结果")]

    public function calculate(Request $request): Response
    {
        $baseSalary = (float) $request->input('base_salary', 0);
        if ($baseSalary < 0) {
            return $this->fail('base_salary 不能为负数', 422);
        }
        $pieceWage = bc_norm((string) $request->input('piece_wage', '0'));
        if (bccomp($pieceWage, '0', 2) < 0) {
            return $this->fail('piece_wage 不能为负数', 422);
        }
        $result = (new SalaryEngineService())->calculate(
            $baseSalary,
            (float) $request->input('performance', 0),
            (float) $request->input('overtime', 0),
            (float) $request->input('deduction', 0),
            $pieceWage
        );

        return $this->success($result, '试算完成');
    }

    /**
     * 银行代发文件
     */#[\erikwang2013\apidoc\annotation\Title("银行代发文件")]
#[\erikwang2013\apidoc\annotation\Desc("校验员工银行账号并生成代发CSV")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary/payroll-file")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"bank_code", type:"string", desc:"银行代码: ICBC/BOC/CCB/CMB")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"代发文件内容与校验结果")]

    public function payrollFile(Request $request): Response
    {
        $records = $request->input('records', []);
        if (!is_array($records)) {
            return $this->fail('records 必须为数组', 422);
        }
        $service = new BankPayrollService();
        $validation = $service->validateAccounts($records);
        if (!$validation['valid']) {
            return $this->success(['valid' => false, 'errors' => $validation['errors'], 'file' => ''], '账号校验未通过');
        }
        $file = $service->generatePayrollFile($records, (string) $request->input('bank_code', 'ICBC'));

        return $this->success(['valid' => true, 'file' => $file], '代发文件已生成');
    }

    // 薪资项管理

    /**
     * 薪资项列表
     */#[\erikwang2013\apidoc\annotation\Title("薪资项列表")]
#[\erikwang2013\apidoc\annotation\Desc("查询全部薪资项配置")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function itemIndex(Request $request): Response
    {
        $list = $this->hr()->all(HrSalaryItem::class, [], ['orderBy' => 'id', 'orderDir' => 'asc']);
        $list = array_map(fn ($item) => $this->encodeIds($item), $list);

        return $this->success(['list' => $list]);
    }

    /**
     * 创建薪资项
     */#[\erikwang2013\apidoc\annotation\Title("创建薪资项")]
#[\erikwang2013\apidoc\annotation\Desc("新增薪资项配置")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/salary")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"薪资项编码，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"薪资项名称，必填")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function itemStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = $this->hr()->create(HrSalaryItem::class, $request->all());

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 薪资项详情
     */#[\erikwang2013\apidoc\annotation\Title("薪资项详情")]
#[\erikwang2013\apidoc\annotation\Desc("查看薪资项详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资项ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function itemShow(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrSalaryItem::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新薪资项
     */#[\erikwang2013\apidoc\annotation\Title("更新薪资项")]
#[\erikwang2013\apidoc\annotation\Desc("修改薪资项配置")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资项ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function itemUpdate(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->update(HrSalaryItem::class, $id, $request->all());
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除薪资项
     */#[\erikwang2013\apidoc\annotation\Title("删除薪资项")]
#[\erikwang2013\apidoc\annotation\Desc("删除薪资项配置，需密码确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资项ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function itemDestroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->hr()->find(HrSalaryItem::class, $id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->hr()->delete(HrSalaryItem::class, $id);

        return $this->success([], '删除成功');
    }

    /**
     * 工资条视图
     */#[\erikwang2013\apidoc\annotation\Title("工资条视图")]
#[\erikwang2013\apidoc\annotation\Desc("头行+薪资项行+社保补充（只读，不改动薪资数据）")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"薪资ID")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"salary/items/social")]

    public function payslipView(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $payload = Container::get(PayslipService::class)->view($id);
        if ($payload === null) {
            return $this->fail('记录不存在', 404);
        }
        $payload['salary'] = $this->encodeIds($payload['salary']);
        $payload['salary']['employee'] = !empty($payload['salary']['employee']) ? $this->encodeIds($payload['salary']['employee']) : null;

        return $this->success($payload);
    }

    /**
     * HR 薄服务层实例（Container::get 走 class_exists 回退，见 config/dependence.php 注释）
     */
    private function hr(): HrService
    {
        return Container::get(HrService::class);
    }
}
