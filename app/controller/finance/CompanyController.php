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
#[\erikwang2013\apidoc\annotation\Title("公司")]
class CompanyController extends BaseController
{
    /**
     * 公司列表
     */
#[\erikwang2013\apidoc\annotation\Title("公司列表")]
#[\erikwang2013\apidoc\annotation\Desc("全量公司列表，含各自默认账套摘要")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/company/list")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"公司列表")]

    public function list(Request $request): Response
    {
        $companies = Company::orderByDesc('id')->get();
        $items = [];
        foreach ($companies as $company) {
            $row = $this->encodeIds($company->toArray(), ['id', 'parent_id']);
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
#[\erikwang2013\apidoc\annotation\Title("新增公司")]
#[\erikwang2013\apidoc\annotation\Desc("创建组织并自动创建默认账套、开启当前自然月期间")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/company/create")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"公司名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", desc:"公司编码(2-50位字母/数字/_-)，必填且全局唯一")]
#[\erikwang2013\apidoc\annotation\Param(name:"base_currency", type:"string", desc:"本位币，默认CNY")]
#[\erikwang2013\apidoc\annotation\Param(name:"parent_id", type:"string", desc:"上级组织ID(hashid或数字)，0=顶级")]
#[\erikwang2013\apidoc\annotation\Param(name:"remark", type:"string", desc:"备注")]

    public function create(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $code = trim((string) $request->input('code', ''));
        if ($name === '' || $code === '') {
            return $this->fail('name/code 必填', 422);
        }
        $parentInput = $request->input('parent_id', 0);
        $parentId = is_numeric($parentInput)
            ? (int) $parentInput
            : (int) ($this->decodeIdSafe((string) $parentInput) ?? 0);

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

        return $this->success($row, '公司创建成功');
    }

    /**
     * 启用/停用公司
     */
#[\erikwang2013\apidoc\annotation\Title("启用/停用公司")]
#[\erikwang2013\apidoc\annotation\Desc("status 0=停用 1=启用")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/finance/company/toggle")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("财务管理")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", desc:"公司ID(hashid)，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"0=停用 1=启用")]

    public function toggle(Request $request): Response
    {
        $id = $this->decodeIdSafe((string) $request->input('id', ''));
        $status = (int) $request->input('status', -1);
        if ($id === null || $status < 0 || $status > 1) {
            return $this->fail('id 与 status(0/1) 必填', 422);
        }
        $company = Company::find($id);
        if (!$company) {
            return $this->fail('公司不存在', 404);
        }
        $company->status = $status;
        $company->save();

        return $this->success(
            $this->encodeIds($company->toArray(), ['id', 'parent_id']),
            $status === 1 ? '公司已启用' : '公司已停用'
        );
    }
}
