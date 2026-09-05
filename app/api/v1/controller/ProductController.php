<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
  * @Apidoc\Tag("商品")
 */
declare(strict_types=1);

namespace app\api\v1\controller;

use app\admin\controller\BaseController;
use app\model\Product;
use support\Request;
use support\Response;

class ProductController extends BaseController
{
    /**
     * 商品列表
     * @Apidoc\Title("商品列表")
     * @Apidoc\Desc("客户端公开商品列表(仅启用商品)，支持关键词搜索，分页返回")
     * @Apidoc\Url("/api/v1/product")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="page", type="int", default="1", desc="页码")
     * @Apidoc\Param(name="limit", type="int", default="20", desc="每页数量")
     * @Apidoc\Param(name="keyword", type="string", desc="商品名称或编码关键字")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="分页列表(list/total/page/limit),list含id(hashid)/code/name/barcode/spec/unit/image")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 20);
        $keyword = $request->input('keyword', '');

        $query = Product::where('status', 1);
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)
            ->orderBy('id', 'desc')
            ->get(['id', 'code', 'name', 'barcode', 'spec', 'unit', 'image'])
            ->map(fn ($p) => $this->encodeIds($p->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 商品详情
     * @Apidoc\Title("商品详情")
     * @Apidoc\Desc("查询启用商品详情，含启用 SKU 与批发/零售价")
     * @Apidoc\Url("/api/v1/product/{hashid}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("客户端 API")
     * @Apidoc\Param(name="hashid", type="string", require=true, desc="商品ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="商品详情(hashid),含skus与prices(wholesale/retail)")
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with([
            'skus' => function ($q) {
                $q->where('status', 1)->select('id', 'product_id', 'sku_code', 'barcode', 'spec_attrs');
            },
            'prices' => function ($q) {
                $q->whereIn('price_type', ['wholesale', 'retail']);
            },
        ])->find($id);

        if (!$product) {
            return $this->fail('商品不存在', 404);
        }

        return $this->success($this->encodeIds($product->toArray()));
    }
}
