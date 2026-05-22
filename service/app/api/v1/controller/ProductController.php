<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\api\v1\controller;

use app\admin\controller\BaseController;
use app\model\Product;
use support\Request;
use support\Response;

class ProductController extends BaseController
{
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
            ->map(fn($p) => $this->encodeIds($p->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $product = Product::with([
            'skus' => function ($q) { $q->where('status', 1)->select('id', 'product_id', 'sku_code', 'barcode', 'spec_attrs'); },
            'prices' => function ($q) { $q->whereIn('price_type', ['wholesale', 'retail']); }
        ])->find($id);

        if (!$product) return $this->fail('商品不存在', 404);
        return $this->success($this->encodeIds($product->toArray()));
    }
}
