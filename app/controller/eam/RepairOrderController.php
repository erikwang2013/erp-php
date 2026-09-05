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

    /**
     * 维修工单列表（分页）
     * @Apidoc\Title("维修工单列表")
     * @Apidoc\Desc("获取维修工单列表，支持分页、工单号/故障描述关键词搜索及状态/设备筛选")
     * @Apidoc\Url("/admin/v1/eam/repair")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="page", type="int", default=1, desc="页码")
     * @Apidoc\Param(name="limit", type="int", default=15, desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", default="", desc="搜索关键词（工单号/故障描述）")
     * @Apidoc\Param(name="status", type="string", default="", desc="状态筛选: open/in_progress/completed/cancelled")
     * @Apidoc\Param(name="equipment_id", type="string", default="", desc="设备hashid筛选")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="维修工单列表数据")
     */
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = EamRepairOrder::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%")
                  ->orWhere('fault_description', 'like', "%{$keyword}%");
            });
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        $equipmentId = $request->input('equipment_id');
        if ($equipmentId) {
            $query->where('equipment_id', $this->decodeId($equipmentId));
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建维修工单
     * @Apidoc\Title("创建维修工单")
     * @Apidoc\Desc("新增维修工单，工单号/设备ID/故障描述/维修类型必填，初始状态为 open")
     * @Apidoc\Url("/admin/v1/eam/repair")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="code", type="string", default="", desc="维修工单号（必填）")
     * @Apidoc\Param(name="equipment_id", type="int", default="", desc="设备ID（必填）")
     * @Apidoc\Param(name="fault_description", type="string", default="", desc="故障描述（必填）")
     * @Apidoc\Param(name="repair_type", type="string", default="", desc="维修类型（必填）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="创建的维修工单记录")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'equipment_id' => 'required|integer',
            'fault_description' => 'required|string|max:1000',
            'repair_type' => 'required|string|max:50',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new EamRepairOrder();
        $item->id = $this->generateId();
        $item->status = 'open';
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    /**
     * 维修工单详情
     * @Apidoc\Title("维修工单详情")
     * @Apidoc\Desc("根据ID获取维修工单详细信息")
     * @Apidoc\Url("/admin/v1/eam/repair/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="维修工单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="维修工单详情")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamRepairOrder::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    /**
     * 更新维修工单
     * @Apidoc\Title("更新维修工单")
     * @Apidoc\Desc("根据ID更新维修工单信息，已完成/已取消的工单不允许编辑")
     * @Apidoc\Url("/admin/v1/eam/repair/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="维修工单hashid")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="更新后的维修工单记录")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamRepairOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        // 已完成/已取消的工单不允许编辑
        if (in_array($item->status, ['completed', 'cancelled'], true)) {
            return $this->fail('已完成或已取消的工单不允许编辑', 422);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    /**
     * 删除维修工单（软删除）
     * @Apidoc\Title("删除维修工单")
     * @Apidoc\Desc("根据ID软删除维修工单，需管理员密码二次确认")
     * @Apidoc\Url("/admin/v1/eam/repair/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", default="", desc="维修工单hashid")
     * @Apidoc\Param(name="password", type="string", default="", desc="管理员密码（二次确认）")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="array", desc="空数组")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamRepairOrder::find($id);
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
     * 状态流转
     * @Apidoc\Title("维修工单状态流转")
     * @Apidoc\Url("/admin/v1/eam/repair/{id}/transition")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="status", type="string", require=true, desc="目标状态: in_progress/completed/cancelled")
     */
    public function transition(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = EamRepairOrder::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }

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
