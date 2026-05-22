<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use app\admin\controller\BaseController;
use app\model\MfgProductionOrder;
use app\model\MfgProductionItem;
use support\Request;
use support\Response;

/**
 * 生产工单管理 — CRUD + 状态流转
  * @Apidoc\Tag("生产制造")
 */
class ProductionController extends BaseController
{
    /**
     * 生产工单列表（分页）
     * GET /admin/mfg/production
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');
        $bomId = $request->input('bom_id');

        $query = MfgProductionOrder::query();
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($bomId) {
            $query->where('bom_id', (int) $bomId);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn($item) => $this->encodeIds($item->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    /**
     * 创建生产工单
     * POST /admin/mfg/production
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'bom_id' => 'required|integer',
            'planned_quantity' => 'required|numeric',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);

        $item = new MfgProductionOrder();
        $item->id = $this->generateId();
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = 0; // 待生产
        $item->completed_quantity = 0;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 工单详情（含明细）
     * GET /admin/mfg/production/{id}
     */
    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::with(['items', 'bom'])->find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $data = $item->toArray();
        if (isset($data['items'])) {
            $data['items'] = array_map(fn($i) => $this->encodeIds($i), $data['items']);
        }
        if ($item->relationLoaded('bom') && $item->bom) {
            $data['bom'] = $this->encodeIds($item->bom->toArray());
        }
        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新工单
     * PUT /admin/mfg/production/{id}
     */
    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('只能修改待生产状态的工单', 422);

        $originalStatus = $item->status;
        $originalCompletedQty = $item->completed_quantity;
        foreach ($request->all() as $k => $v) {
            if ($k !== 'id') $item->$k = $v;
        }
        $item->status = $originalStatus; // Status can only change via start()/complete()
        $item->completed_quantity = $originalCompletedQty;
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除工单（软删除）
     * DELETE /admin/mfg/production/{id}
     */
    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if (in_array($item->status, [1, 2])) return $this->fail('生产中或已完成的工单不可删除', 422);

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);

        MfgProductionItem::where('order_id', $id)->delete();
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 开始生产
     * POST /admin/mfg/production/{id}/start
     */
    public function start(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 0) return $this->fail('只有待生产状态的工单可以开始生产', 422);

        $item->status = 1; // 生产中
        $item->actual_start = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '生产已开始');
    }

    /**
     * 完成生产
     * POST /admin/mfg/production/{id}/complete
     */
    public function complete(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = MfgProductionOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        if ($item->status !== 1) return $this->fail('只有生产中的工单可以完成', 422);

        $completedQty = (float) $request->input('completed_quantity', $item->planned_quantity);
        $item->status = 2; // 已完成
        $item->completed_quantity = $completedQty;
        $item->actual_end = date('Y-m-d H:i:s');
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '生产已完成');
    }
}
