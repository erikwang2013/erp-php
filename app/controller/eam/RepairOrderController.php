<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\eam;

use app\admin\controller\BaseController;
use app\model\EamRepairOrder;
use support\Request;
use support\Response;

/**
 * 维修工单管理
 * @Apidoc\Tag("设备管理")
 */
class RepairOrderController extends BaseController
{
    /**
     * 允许的状态流转: open(待处理) -> in_progress(维修中) -> completed(已完成) / cancelled(已取消)
     */
    private const STATUS_TRANSITIONS = [
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = EamRepairOrder::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) $query->where(function ($q) use ($keyword) {
            $q->where('code', 'like', "%{$keyword}%")
              ->orWhere('fault_description', 'like', "%{$keyword}%");
        });
        $status = $request->input('status');
        if ($status !== null && $status !== '') $query->where('status', $status);
        $equipmentId = $request->input('equipment_id');
        if ($equipmentId) $query->where('equipment_id', $this->decodeId($equipmentId));
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn($i) => $this->encodeIds($i->toArray()));
        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'equipment_id' => 'required|integer',
            'fault_description' => 'required|string|max:1000',
            'repair_type' => 'required|string|max:50',
        ]);
        if ($validator->fails()) return $this->fail($validator->errors()->first(), 422);
        $item = new EamRepairOrder();
        $item->id = $this->generateId();
        $item->status = 'open';
        $this->fillModelFromRequest($item, $request);
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = EamRepairOrder::find($id);
        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = EamRepairOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        // 已完成/已取消的工单不允许编辑
        if (in_array($item->status, ['completed', 'cancelled'], true)) {
            return $this->fail('已完成或已取消的工单不允许编辑', 422);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = EamRepairOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) return $this->fail($error, 422);
        $item->delete();
        return $this->success([], '删除成功');
    }

    /**
     * 状态流转
     * @Apidoc\Title("维修工单状态流转")
     * @Apidoc\Url("/admin/eam/repair/{id}/transition")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="status", type="string", require=true, desc="目标状态: in_progress/completed/cancelled")
     */
    public function transition(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = EamRepairOrder::find($id);
        if (!$item) return $this->fail('记录不存在', 404);

        $target = $request->input('status', '');
        if (!isset(self::STATUS_TRANSITIONS[$item->status])) {
            return $this->fail('当前状态无效', 422);
        }
        if (!in_array($target, self::STATUS_TRANSITIONS[$item->status], true)) {
            return $this->fail("不允许从「{$item->status}」流转到「{$target}」", 422);
        }

        $item->status = $target;
        if ($target === 'completed' && empty($item->end_date)) {
            $item->end_date = date('Y-m-d H:i:s');
        }
        $item->save();
        return $this->success($this->encodeIds($item->toArray()), '状态更新成功');
    }
}
