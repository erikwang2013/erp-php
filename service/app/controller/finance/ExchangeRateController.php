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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建汇率
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'from_currency_id' => 'required|integer',
            'to_currency_id' => 'required|integer',
            'rate' => 'required|numeric',
            'effective_date' => 'required|date',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinanceExchangeRate();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 汇率详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceExchangeRate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新汇率
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceExchangeRate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除汇率
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceExchangeRate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
