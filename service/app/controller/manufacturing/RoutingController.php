<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgRouting;
use support\Request;
use support\Response;

/**
 * 工艺路线管理
 */
class RoutingController extends BaseController
{
    /**
     * 工艺路线列表（按产品分组，按seq排序）
     * GET /admin/mfg/routing
     */
    public function index(Request $request): Response
    {
        $productId = $request->input('product_id');

        $query = MfgRouting::query();
        if ($productId) {
            $query->where('product_id', (int) $productId);
        }

        $list = $query->orderBy('product_id', 'asc')
            ->orderBy('seq', 'asc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list]);
    }

    /**
     * 添加工序
     * POST /admin/mfg/routing
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'product_id' => 'required|integer',
            'name' => 'required|string|max:100',
            'seq' => 'required|integer',
            'workstation_id' => 'required|integer',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new MfgRouting();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->created_at = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工序详情
     * GET /admin/mfg/routing/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgRouting::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新工序
     * PUT /admin/mfg/routing/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgRouting::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工序
     * DELETE /admin/mfg/routing/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgRouting::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        $item->delete();
        return $this->success([], '删除成功');
    }
}
