<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\FinanceTaxRate;
use app\model\FinanceTaxRecord;
use support\Request;
use support\Response;

class TaxController extends BaseController
{
    // 税率配置

    /**
     * 税率列表
     * @Apidoc\Title("税率列表")
     * @Apidoc\Desc("查询全部税率配置")
     * @Apidoc\Url("/admin/v1/finance/tax-rate")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("税率列表")]
#[Apidoc\Desc("查询全部税率配置")]
#[Apidoc\Url("/admin/v1/finance/tax-rate")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function rates(Request $request): Response
    {
        $list = FinanceTaxRate::query()->orderBy('id', 'asc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * 创建/更新税率
     * @Apidoc\Title("创建或更新税率")
     * @Apidoc\Desc("有id则更新，无id则创建税率记录")
     * @Apidoc\Url("/admin/v1/finance/tax-rate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="name", type="string", desc="税率名称，必填")
     * @Apidoc\Param(name="rate", type="float", desc="税率值，必填")
     * @Apidoc\Param(name="type", type="string", desc="税率类型，必填")
     * @Apidoc\Param(name="id", type="string", desc="记录ID，传则更新")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建或更新税率")]
#[Apidoc\Desc("有id则更新，无id则创建税率记录")]
#[Apidoc\Url("/admin/v1/finance/tax-rate")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"name", type:"string", desc:"税率名称，必填")]
#[Apidoc\Param(name:"rate", type:"float", desc:"税率值，必填")]
#[Apidoc\Param(name:"type", type:"string", desc:"税率类型，必填")]
#[Apidoc\Param(name:"id", type:"string", desc:"记录ID，传则更新")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function storeRate(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric',
            'type' => 'required|string|max:30',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $hashid = $request->input('id', '');
        if ($hashid) {
            $id = $this->decodeId($id);
            $item = FinanceTaxRate::find($id);
            if (!$item) {
                return $this->fail('记录不存在', 404);
            }
        } else {
            $item = new FinanceTaxRate();
            $item->id = $this->generateId();
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $hashid ? '更新成功' : '创建成功');
    }

    /**
     * 删除税率
     * @Apidoc\Title("删除税率")
     * @Apidoc\Desc("删除税率配置记录")
     * @Apidoc\Url("/admin/v1/finance/tax-rate/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="税率ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除税率")]
#[Apidoc\Desc("删除税率配置记录")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"税率ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function destroyRate(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceTaxRate::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }

    // 税务记录

    /**
     * 税务记录列表
     * @Apidoc\Title("税务记录列表")
     * @Apidoc\Desc("分页查询税务记录")
     * @Apidoc\Url("/admin/v1/finance/tax-rate")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="tax_rate_id", type="int", desc="税率ID")
     * @Apidoc\Param(name="source_type", type="string", desc="来源类型")
     * @Apidoc\Param(name="period_year", type="int", desc="会计年度")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("税务记录列表")]
#[Apidoc\Desc("分页查询税务记录")]
#[Apidoc\Url("/admin/v1/finance/tax-rate")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"page", type:"int", desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", desc:"每页条数")]
#[Apidoc\Param(name:"tax_rate_id", type:"int", desc:"税率ID")]
#[Apidoc\Param(name:"source_type", type:"string", desc:"来源类型")]
#[Apidoc\Param(name:"period_year", type:"int", desc:"会计年度")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function records(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $taxRateId = $request->input('tax_rate_id');
        $sourceType = $request->input('source_type', '');
        $periodYear = $request->input('period_year');

        $query = FinanceTaxRecord::query();
        if ($taxRateId) {
            $query->where('tax_rate_id', (int) $taxRateId);
        }
        if ($sourceType !== '') {
            $query->where('source_type', $sourceType);
        }
        if ($periodYear) {
            $query->where('period_year', (int) $periodYear);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }
}
