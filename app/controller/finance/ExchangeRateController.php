<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceExchangeRate;
use support\Request;
use support\Response;

class ExchangeRateController extends BaseController
{
    /**
     * 汇率列表（分页）
     * @Apidoc\Title("汇率列表")
     * @Apidoc\Desc("分页查询汇率记录")
     * @Apidoc\Url("/admin/finance/exchange-rate")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="from_currency_id", type="int", desc="来源币种ID")
     * @Apidoc\Param(name="to_currency_id", type="int", desc="目标币种ID")
     * @Apidoc\Param(name="effective_date", type="string", desc="生效日期")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $fromCurrencyId = $request->input('from_currency_id');
        $toCurrencyId = $request->input('to_currency_id');
        $effectiveDate = $request->input('effective_date', '');

        $query = FinanceExchangeRate::query();
        if ($fromCurrencyId) {
            $query->where('from_currency_id', (int) $fromCurrencyId);
        }
        if ($toCurrencyId) {
            $query->where('to_currency_id', (int) $toCurrencyId);
        }
        if ($effectiveDate !== '') {
            $query->where('effective_date', $effectiveDate);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建汇率
     * @Apidoc\Title("创建汇率")
     * @Apidoc\Desc("新增汇率记录")
     * @Apidoc\Url("/admin/finance/exchange-rate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="from_currency_id", type="int", desc="来源币种ID，必填")
     * @Apidoc\Param(name="to_currency_id", type="int", desc="目标币种ID，必填")
     * @Apidoc\Param(name="rate", type="float", desc="汇率值，必填")
     * @Apidoc\Param(name="effective_date", type="string", desc="生效日期，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'from_currency_id' => 'required|integer',
            'to_currency_id' => 'required|integer',
            'rate' => 'required|numeric',
            'effective_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceExchangeRate();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 汇率详情
     * @Apidoc\Title("汇率详情")
     * @Apidoc\Desc("查看汇率详细信息")
     * @Apidoc\Url("/admin/finance/exchange-rate/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="汇率ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceExchangeRate::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新汇率
     * @Apidoc\Title("更新汇率")
     * @Apidoc\Desc("修改汇率信息")
     * @Apidoc\Url("/admin/finance/exchange-rate/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="汇率ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceExchangeRate::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除汇率
     * @Apidoc\Title("删除汇率")
     * @Apidoc\Desc("删除汇率记录，需密码确认")
     * @Apidoc\Url("/admin/finance/exchange-rate/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="汇率ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceExchangeRate::find($id);
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
