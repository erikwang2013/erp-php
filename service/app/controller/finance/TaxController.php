<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceTaxRate;
use app\model\FinanceTaxRecord;
use support\Request;
use support\Response;

class TaxController extends BaseController
{
    // ============================================================
    // 税率配置
    // ============================================================

    /**
     * 税率列表
     * GET /admin/finance/tax-rate
     */
    public function rates(Request $request): Response
    {
        $list = FinanceTaxRate::query()->orderBy('id', 'asc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));
        return $this->success(['list' => $list]);
    }

    /**
     * 创建/更新税率
     * POST /admin/finance/tax-rate
     */
    public function storeRate(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric',
            'type' => 'required|string|max:30',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $hashid = $request->input('id', '');
        if ($hashid) {
            $id = $this->decodeId($hashid);
            $item = FinanceTaxRate::find($id);
            if (!$item) return $this->fail('记录不存在', 404);
        } else {
            $item = new FinanceTaxRate();
            $item->id = $this->generateId();
        }

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), $hashid ? '更新成功' : '创建成功');
    }

    /**
     * 删除税率
     * DELETE /admin/finance/tax-rate/{id}
     */
    public function destroyRate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceTaxRate::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $item->delete();
        return $this->success([], '删除成功');
    }

    // ============================================================
    // 税务记录
    // ============================================================

    /**
     * 税务记录列表（分页）
     * GET /admin/finance/tax-record
     */
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
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
}
