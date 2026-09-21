<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\Company;
use app\model\FinanceLedger;
use app\service\finance\LedgerService;
use support\Request;
use support\Response;

/**
 * 组织/公司管理（F1）——多组织与账套的入口。
 */
#[\erikwang2013\apidoc\annotation\Title('公司')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]
class CompanyController extends BaseController
{
    /**
     * 公司列表
     */
    #[\erikwang2013\apidoc\annotation\Title('公司列表')]
    #[\erikwang2013\apidoc\annotation\Desc('全量公司列表，含各自默认账套摘要')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/company/list')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'公司列表')]

    public function list(Request $request): Response
    {
        $companies = Company::orderByDesc('id')->get();
        $names = $companies->pluck('name', 'id')->all();
        $items = [];
        foreach ($companies as $company) {
            $row = $this->encodeIds($company->toArray(), ['id', 'parent_id']);
            // 上级名称：前端 inferColumns 用 parent_name 兄弟列渲染，否则上级列直接显示 hashid
            $row['parent_name'] = $names[$company->parent_id] ?? '';
            $ledger = FinanceLedger::where('company_id', (int) $company->id)
                ->where('is_default', 1)->first();
            if ($ledger) {
                $row['default_ledger'] = $this->encodeIds($ledger->toArray(), ['id', 'company_id']);
            } else {
                $row['default_ledger'] = null;
            }
            $items[] = $row;
        }

        return $this->success(['list' => $items, 'total' => count($items)]);
    }

    /**
     * 新增公司（含默认账套与当期开账，一事务）
     */
    #[\erikwang2013\apidoc\annotation\Title('新增公司')]
    #[\erikwang2013\apidoc\annotation\Desc('创建组织并自动创建默认账套、开启当前自然月期间')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/company/create')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'公司名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', desc:'公司编码(2-50位字母/数字/_-)，必填且全局唯一')]
    #[\erikwang2013\apidoc\annotation\Param(name:'base_currency', type:'string', desc:'本位币，默认CNY')]
    #[\erikwang2013\apidoc\annotation\Param(name:'parent_id', type:'string', desc:'上级组织ID(hashid或数字)，0=顶级')]
    #[\erikwang2013\apidoc\annotation\Param(name:'remark', type:'string', desc:'备注')]

    public function create(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'string',
            'code' => 'string',
            'base_currency' => 'string',
            'parent_id' => 'string',
            'remark' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $name = trim((string) $request->input('name', ''));
        $code = trim((string) $request->input('code', ''));
        if ($name === '' || $code === '') {
            return $this->fail($this->trans('name and code are required'), 422);
        }
        // 上级组织收 hashid 串或原生数字；未传/空串＝顶级。解不出→422
        // （原 (int) 兜底把垃圾父级静默写成 0，挂错层级且无任何提示）
        $parentInput = $request->input('parent_id', 0);
        $parentId = $this->decodeFlexibleId($parentInput === null || $parentInput === '' ? 0 : $parentInput);
        if ($parentId === null) {
            return $this->fail($this->trans('Invalid parent_id'), 422);
        }

        try {
            $company = (new LedgerService())->createCompany([
                'name' => $name,
                'code' => $code,
                'base_currency' => (string) $request->input('base_currency', 'CNY'),
                'parent_id' => $parentId,
                'remark' => (string) $request->input('remark', ''),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $row = $this->encodeIds($company->toArray(), ['id', 'parent_id']);
        $ledger = FinanceLedger::where('company_id', (int) $company->id)->where('is_default', 1)->first();
        if ($ledger) {
            $row['default_ledger'] = $this->encodeIds($ledger->toArray(), ['id', 'company_id']);
        }

        return $this->success($row, $this->trans('Company created successfully'));
    }

    /**
     * 启用/停用公司
     */
    #[\erikwang2013\apidoc\annotation\Title('启用/停用公司')]
    #[\erikwang2013\apidoc\annotation\Desc('status 0=停用 1=启用')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/company/toggle')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'公司ID(hashid)，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'0=停用 1=启用')]

    public function toggle(Request $request): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // decodeIdSafe 只认 hashid：原生数字 ID（数字 ID 也是合法入参）会被静默拒成 422
        $id = $this->decodeFlexibleId($request->input('id', ''));
        $status = (int) $request->input('status', -1);
        if ($id === null || $id < 1 || $status < 0 || $status > 1) {
            return $this->fail($this->trans('id and status(0/1) are required'), 422);
        }
        $company = Company::find($id);
        if (!$company) {
            return $this->fail($this->trans('Company not found'), 404);
        }
        $company->status = $status;
        $company->save();

        return $this->success(
            $this->encodeIds($company->toArray(), ['id', 'parent_id']),
            $status === 1 ? $this->trans('Company enabled') : $this->trans('Company disabled')
        );
    }
}
