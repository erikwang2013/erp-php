<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("财务管理")
 */
declare(strict_types=1);

namespace app\controller\finance;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\FinanceAsset;
use app\model\FinanceAssetDepreciation;
use support\Request;
use support\Response;

class AssetController extends BaseController
{
    /**
     * 固定资产列表（分页）
     * @Apidoc\Title("固定资产列表")
     * @Apidoc\Desc("分页查询固定资产记录")
     * @Apidoc\Url("/admin/v1/finance/asset")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态")
     * @Apidoc\Param(name="category", type="string", desc="资产类别")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("固定资产列表")]
#[Apidoc\Desc("分页查询固定资产记录")]
#[Apidoc\Url("/admin/v1/finance/asset")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"page", type:"int", desc:"页码")]
#[Apidoc\Param(name:"limit", type:"int", desc:"每页条数")]
#[Apidoc\Param(name:"keyword", type:"string", desc:"关键词")]
#[Apidoc\Param(name:"status", type:"int", desc:"状态")]
#[Apidoc\Param(name:"category", type:"string", desc:"资产类别")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

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
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建固定资产
     * @Apidoc\Title("创建固定资产")
     * @Apidoc\Desc("新增固定资产，支持直线法自动计算月折旧额")
     * @Apidoc\Url("/admin/v1/finance/asset")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="name", type="string", desc="资产名称")
     * @Apidoc\Param(name="code", type="string", desc="资产编码")
     * @Apidoc\Param(name="category", type="string", desc="资产类别")
     * @Apidoc\Param(name="purchase_date", type="string", desc="购置日期")
     * @Apidoc\Param(name="purchase_amount", type="float", desc="购置金额")
     * @Apidoc\Param(name="salvage_value", type="float", desc="残值")
     * @Apidoc\Param(name="useful_life", type="int", desc="使用年限")
     * @Apidoc\Param(name="depreciation_method", type="int", desc="折旧方法:1=直线法")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("创建固定资产")]
#[Apidoc\Desc("新增固定资产，支持直线法自动计算月折旧额")]
#[Apidoc\Url("/admin/v1/finance/asset")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"name", type:"string", desc:"资产名称")]
#[Apidoc\Param(name:"code", type:"string", desc:"资产编码")]
#[Apidoc\Param(name:"category", type:"string", desc:"资产类别")]
#[Apidoc\Param(name:"purchase_date", type:"string", desc:"购置日期")]
#[Apidoc\Param(name:"purchase_amount", type:"float", desc:"购置金额")]
#[Apidoc\Param(name:"salvage_value", type:"float", desc:"残值")]
#[Apidoc\Param(name:"useful_life", type:"int", desc:"使用年限")]
#[Apidoc\Param(name:"depreciation_method", type:"int", desc:"折旧方法:1=直线法")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new FinanceAsset();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
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
     * 固定资产详情
     * @Apidoc\Title("固定资产详情")
     * @Apidoc\Desc("查看固定资产详细信息")
     * @Apidoc\Url("/admin/v1/finance/asset/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="资产ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("固定资产详情")]
#[Apidoc\Desc("查看固定资产详细信息")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"资产ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceAsset::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新固定资产
     * @Apidoc\Title("更新固定资产")
     * @Apidoc\Desc("修改固定资产信息，自动重新计算净值和月折旧额")
     * @Apidoc\Url("/admin/v1/finance/asset/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="资产ID")
     * @Apidoc\Param(name="name", type="string", desc="资产名称")
     * @Apidoc\Param(name="code", type="string", desc="资产编码")
     * @Apidoc\Param(name="category", type="string", desc="资产类别")
     * @Apidoc\Param(name="purchase_amount", type="float", desc="购置金额")
     * @Apidoc\Param(name="salvage_value", type="float", desc="残值")
     * @Apidoc\Param(name="useful_life", type="int", desc="使用年限")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("更新固定资产")]
#[Apidoc\Desc("修改固定资产信息，自动重新计算净值和月折旧额")]
#[Apidoc\Method("PUT")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"资产ID")]
#[Apidoc\Param(name:"name", type:"string", desc:"资产名称")]
#[Apidoc\Param(name:"code", type:"string", desc:"资产编码")]
#[Apidoc\Param(name:"category", type:"string", desc:"资产类别")]
#[Apidoc\Param(name:"purchase_amount", type:"float", desc:"购置金额")]
#[Apidoc\Param(name:"salvage_value", type:"float", desc:"残值")]
#[Apidoc\Param(name:"useful_life", type:"int", desc:"使用年限")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceAsset::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->net_value = bcsub((string) $item->purchase_amount, (string) $item->accumulated_depreciation, 2);

        if ((int) $item->depreciation_method === 1 && (int) $item->useful_life > 0) {
            $depreciable = bcsub((string) $item->purchase_amount, (string) $item->salvage_value, 2);
            $item->monthly_depreciation = bcdiv($depreciable, (string) $item->useful_life, 2);
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除固定资产
     * @Apidoc\Title("删除固定资产")
     * @Apidoc\Desc("删除固定资产，需密码确认")
     * @Apidoc\Url("/admin/v1/finance/asset/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="资产ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */#[Apidoc\Title("删除固定资产")]
#[Apidoc\Desc("删除固定资产，需密码确认")]
#[Apidoc\Method("DELETE")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"资产ID")]
#[Apidoc\Param(name:"password", type:"string", desc:"管理员密码")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"业务数据")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = FinanceAsset::find($id);
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

    /**
     * 计提折旧
     * @Apidoc\Title("计提折旧")
     * @Apidoc\Desc("为指定资产创建一条折旧记录")
     * @Apidoc\Url("/admin/v1/finance/asset/{id}/depreciate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="资产ID")
     * @Apidoc\Param(name="period_year", type="int", desc="折旧年份")
     * @Apidoc\Param(name="period_month", type="int", desc="折旧月份")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="折旧记录")
     */#[Apidoc\Title("计提折旧")]
#[Apidoc\Desc("为指定资产创建一条折旧记录")]
#[Apidoc\Method("POST")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"资产ID")]
#[Apidoc\Param(name:"period_year", type:"int", desc:"折旧年份")]
#[Apidoc\Param(name:"period_month", type:"int", desc:"折旧月份")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"折旧记录")]

    public function depreciate(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $asset = FinanceAsset::find($id);
        if (!$asset) {
            return $this->fail('资产不存在', 404);
        }

        if ((int) $asset->status !== 1) {
            return $this->fail('仅使用中资产可计提折旧', 422);
        }

        $year = (int) $request->input('period_year', (int) date('Y'));
        $month = (int) $request->input('period_month', (int) date('m'));

        // 检查是否已提
        $exists = FinanceAssetDepreciation::where('asset_id', $id)
            ->where('period_year', $year)->where('period_month', $month)->exists();
        if ($exists) {
            return $this->fail('该期间已计提折旧', 422);
        }

        $amount = bc_norm($asset->monthly_depreciation ?? '0');
        $newAccumulated = bcadd(bc_norm($asset->accumulated_depreciation ?? '0'), $amount, 2);
        $newNet = bcsub(bc_norm($asset->purchase_amount ?? '0'), $newAccumulated, 2);

        $depr = new FinanceAssetDepreciation();
        $depr->id = $this->generateId();
        $depr->asset_id = $id;
        $depr->period_year = $year;
        $depr->period_month = $month;
        $depr->depreciation_amount = $amount;
        $depr->accumulated_amount = $newAccumulated;
        $depr->net_value = bccomp($newNet, bc_norm($asset->salvage_value ?? '0'), 4) >= 0
            ? $newNet
            : bc_norm($asset->salvage_value ?? '0');
        $depr->save();

        $asset->accumulated_depreciation = $newAccumulated;
        $asset->net_value = $depr->net_value;
        $asset->save();

        return $this->success($this->encodeIds($depr->toArray()), '折旧计提成功');
    }

    /**
     * 折旧记录列表
     * @Apidoc\Title("折旧记录列表")
     * @Apidoc\Desc("查看指定资产的折旧记录")
     * @Apidoc\Url("/admin/v1/finance/asset/{id}/depreciation")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("财务管理")
     * @Apidoc\Param(name="id", type="string", desc="资产ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="折旧记录列表")
     */#[Apidoc\Title("折旧记录列表")]
#[Apidoc\Desc("查看指定资产的折旧记录")]
#[Apidoc\Method("GET")]
#[Apidoc\Author("erik")]
#[Apidoc\Tag("财务管理")]
#[Apidoc\Param(name:"id", type:"string", desc:"资产ID")]
#[Apidoc\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[Apidoc\Returned("message", type:"string", desc:"业务信息")]
#[Apidoc\Returned("data", type:"object", desc:"折旧记录列表")]

    public function depreciation(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $list = FinanceAssetDepreciation::where('asset_id', $id)
            ->orderBy('period_year', 'desc')->orderBy('period_month', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }
}
