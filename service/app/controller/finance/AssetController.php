<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use app\admin\controller\BaseController;
use app\model\FinanceAsset;
use app\model\FinanceAssetDepreciation;
use support\Request;
use support\Response;

class AssetController extends BaseController
{
    /**
     * 固定资产列表（分页）
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $category = $request->input('category', '');

        $query = FinanceAsset::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($category !== '') {
            $query->where('category', $category);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建资产
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new FinanceAsset();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->net_value = bcsub((string) $item->purchase_amount, (string) $item->accumulated_depreciation, 2);

        // 直线法自动计算月折旧额
        if ((int) $item->depreciation_method === 1 && (int) $item->useful_life > 0) {
            $depreciable = bcsub((string) $item->purchase_amount, (string) $item->salvage_value, 2);
            $item->monthly_depreciation = bcdiv($depreciable, (string) $item->useful_life, 2);
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 资产详情
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceAsset::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新资产
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceAsset::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->net_value = bcsub((string) $item->purchase_amount, (string) $item->accumulated_depreciation, 2);

        if ((int) $item->depreciation_method === 1 && (int) $item->useful_life > 0) {
            $depreciable = bcsub((string) $item->purchase_amount, (string) $item->salvage_value, 2);
            $item->monthly_depreciation = bcdiv($depreciable, (string) $item->useful_life, 2);
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除资产
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = FinanceAsset::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 计提折旧 — 为指定资产创建一条折旧记录
     * POST /admin/finance/asset/{id}/depreciate
     */
    public function depreciate(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $asset = FinanceAsset::find($id);
        if (!$asset) return $this->fail('资产不存在', 404);

        if ((int) $asset->status !== 1) {
            return $this->fail('仅使用中资产可计提折旧', 422);
        }

        $year = (int) $request->input('period_year', (int) date('Y'));
        $month = (int) $request->input('period_month', (int) date('m'));

        // 检查是否已提
        $exists = FinanceAssetDepreciation::where('asset_id', $id)
            ->where('period_year', $year)->where('period_month', $month)->exists();
        if ($exists) return $this->fail('该期间已计提折旧', 422);

        $amount = (float) $asset->monthly_depreciation;
        $newAccumulated = bcadd((string) $asset->accumulated_depreciation, (string) $amount, 2);
        $newNet = bcsub((string) $asset->purchase_amount, $newAccumulated, 2);

        $depr = new FinanceAssetDepreciation();
        $depr->id = $this->generateId();
        $depr->asset_id = $id;
        $depr->period_year = $year;
        $depr->period_month = $month;
        $depr->depreciation_amount = $amount;
        $depr->accumulated_amount = $newAccumulated;
        $depr->net_value = max((float) $newNet, (float) $asset->salvage_value);
        $depr->save();

        $asset->accumulated_depreciation = $newAccumulated;
        $asset->net_value = $depr->net_value;
        $asset->save();

        return $this->success($this->encodeIds($depr->toArray()), '折旧计提成功');
    }

    /**
     * 折旧记录列表
     * GET /admin/finance/asset/{id}/depreciation
     */
    public function depreciation(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $list = FinanceAssetDepreciation::where('asset_id', $id)
            ->orderBy('period_year', 'desc')->orderBy('period_month', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }
}
