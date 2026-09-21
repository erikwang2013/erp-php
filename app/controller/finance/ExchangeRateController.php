<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceExchangeRate;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('汇率')]
#[\erikwang2013\apidoc\annotation\Group('财务管理')]

class ExchangeRateController extends BaseController
{
    /**
     * 汇率列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('汇率列表')]
    #[\erikwang2013\apidoc\annotation\Desc('分页查询汇率记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/exchange-rate')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from_currency_id', type:'int', desc:'来源币种ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to_currency_id', type:'int', desc:'目标币种ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'effective_date', type:'string', desc:'生效日期')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'from_currency_id' => 'string',
            'to_currency_id' => 'string',
            'effective_date' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $fromCurrencyId = $request->input('from_currency_id');
        $toCurrencyId = $request->input('to_currency_id');
        $effectiveDate = $request->input('effective_date', '');

        $query = FinanceExchangeRate::query();
        // 币种筛选收 hashid 串（前端 source 下拉下发）：`(int)` 强转 hashid 恒为 0 → 筛选静默失效；
        // 非空但解不出 → 422，不静默退化成"不过滤"
        foreach (['from_currency_id' => $fromCurrencyId, 'to_currency_id' => $toCurrencyId] as $field => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $currencyId = $this->decodeFlexibleId($raw);
            if ($currencyId === null || $currencyId < 1) {
                return $this->fail($this->trans('Invalid ' . $field), 422);
            }
            $query->where($field, $currencyId);
        }
        if ($effectiveDate !== '') {
            $query->where('effective_date', $effectiveDate);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'from_currency_id', 'to_currency_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建汇率
     */
    #[\erikwang2013\apidoc\annotation\Title('创建汇率')]
    #[\erikwang2013\apidoc\annotation\Desc('新增汇率记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/finance/exchange-rate')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from_currency_id', type:'int', desc:'来源币种ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to_currency_id', type:'int', desc:'目标币种ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'rate', type:'float', desc:'汇率值，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'effective_date', type:'string', desc:'生效日期，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            // 币种 FK 是 hashid 串（前端 source 下拉下发），integer 规则会把它整类挡回 422
            'from_currency_id' => 'required|string',
            'to_currency_id' => 'required|string',
            'rate' => 'required|numeric',
            'effective_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $currencyIds = [];
        foreach (['from_currency_id', 'to_currency_id'] as $field) {
            $currencyId = $this->decodeFlexibleId((string) $request->input($field, ''));
            if ($currencyId === null || $currencyId < 1) {
                return $this->fail($this->trans('Invalid ' . $field), 422);
            }
            $currencyIds[$field] = $currencyId;
        }

        $item = new FinanceExchangeRate();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 覆盖回填：fillModelFromRequest 落的是请求原文（hashid 串直灌 BIGINT 报 1366 → 500）
        $item->fill($currencyIds);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 汇率详情
     */
    #[\erikwang2013\apidoc\annotation\Title('汇率详情')]
    #[\erikwang2013\apidoc\annotation\Desc('查看汇率详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'汇率ID')]
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
        $item = FinanceExchangeRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新汇率
     */
    #[\erikwang2013\apidoc\annotation\Title('更新汇率')]
    #[\erikwang2013\apidoc\annotation\Desc('修改汇率信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'汇率ID')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = FinanceExchangeRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        // 币种 FK 同 store：未传/空串＝不改动，非空但解不出 → 422
        $currencyIds = [];
        foreach (['from_currency_id', 'to_currency_id'] as $field) {
            $raw = $request->input($field);
            if ($raw === null || $raw === '') {
                continue;
            }
            $currencyId = $this->decodeFlexibleId($raw);
            if ($currencyId === null || $currencyId < 1) {
                return $this->fail($this->trans('Invalid ' . $field), 422);
            }
            $currencyIds[$field] = $currencyId;
        }

        $this->fillModelFromRequest($item, $request);
        if ($currencyIds) {
            $item->fill($currencyIds);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除汇率
     */
    #[\erikwang2013\apidoc\annotation\Title('删除汇率')]
    #[\erikwang2013\apidoc\annotation\Desc('删除汇率记录，需密码确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('财务管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', desc:'汇率ID')]
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
        $item = FinanceExchangeRate::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
