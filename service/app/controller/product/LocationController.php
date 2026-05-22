<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\product;

use app\admin\controller\BaseController;
use app\model\Location;
use support\Request;
use support\Response;

class LocationController extends BaseController
{
    /**
     * 库位列表（分页）
     * GET /product/location
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = Location::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray(), ['id', 'warehouse_id']));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 按仓库获取库位列表
     * GET /product/location/by-warehouse/{warehouse_hashid}
     */
    public function byWarehouse(Request $request, string $warehouseHashid): Response
    {
        $warehouseId = $this->decodeId($warehouseHashid);
        $list = Location::where('warehouse_id', $warehouseId)
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($item) => $this->encodeIds($item->toArray(), ['id', 'warehouse_id']));

        return $this->success(['list' => $list]);
    }

    /**
     * 创建库位
     * POST /product/location
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['name' => 'required|string|max:200']);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new Location();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '创建成功');
    }

    /**
     * 库位详情
     * GET /product/location/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Location::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']));
    }

    /**
     * 更新库位
     * PUT /product/location/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Location::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray(), ['id', 'warehouse_id']), '更新成功');
    }

    /**
     * 删除库位（软删除，需密码确认）
     * DELETE /product/location/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = Location::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
