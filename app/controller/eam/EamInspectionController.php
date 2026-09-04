<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\eam;

use app\admin\controller\BaseController;
use app\model\EamInspectionResult;
use app\model\EamInspectionTask;
use app\service\eam\EamInspectionService;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 设备点检执行（扫码闭环）
 * @Apidoc\Tag("设备管理")
 */
class EamInspectionController extends BaseController
{
    /**
     * 点检任务列表（分页）
     * @Apidoc\Title("点检任务列表")
     * @Apidoc\Desc("按设备/日期/状态分页查询点检任务")
     * @Apidoc\Url("/admin/v1/eam/inspection")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="equipment_id", type="string", desc="设备ID(hashid)")
     * @Apidoc\Param(name="task_date", type="string", desc="点检日期 Y-m-d")
     * @Apidoc\Param(name="status", type="int", desc="状态: 0待执行 1已完成 2异常待维修 3已取消")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $query = EamInspectionTask::query();

        $equipmentId = $request->input('equipment_id', '');
        if ($equipmentId !== '') {
            $query->where('equipment_id', $this->decodeId($equipmentId));
        }
        $taskDate = $request->input('task_date', '');
        if ($taskDate !== '') {
            $query->where('task_date', $taskDate);
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('task_date', 'desc')->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 生成点检任务
     * @Apidoc\Title("生成点检任务")
     * @Apidoc\Desc("按计划或人工补单生成点检任务；扫码自动生成请走扫码执行接口")
     * @Apidoc\Url("/admin/v1/eam/inspection")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="equipment_id", type="string", desc="设备ID(hashid)，必填")
     * @Apidoc\Param(name="task_date", type="string", desc="点检日期 Y-m-d，必填")
     * @Apidoc\Param(name="source_plan_id", type="string", desc="来源保养计划ID(hashid)，选填")
     * @Apidoc\Param(name="assignee_id", type="string", desc="负责人ID(hashid)，选填")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'equipment_id' => 'required|string',
            'task_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $task = $this->inspection()->createTask(
                $this->decodeId((string) $request->input('equipment_id')),
                (string) $request->input('task_date'),
                $request->input('source_plan_id') ? $this->decodeId((string) $request->input('source_plan_id')) : 0,
                $request->input('assignee_id') ? $this->decodeId((string) $request->input('assignee_id')) : 0,
                (string) $request->input('remark', ''),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($task->toArray()), '创建成功');
    }

    /**
     * 点检任务详情（含结果明细）
     * @Apidoc\Title("点检任务详情")
     * @Apidoc\Desc("查看点检任务及扫码结果明细")
     * @Apidoc\Url("/admin/v1/eam/inspection/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $task = EamInspectionTask::query()->find($id);
        if (!$task) {
            return $this->fail('点检任务不存在', 404);
        }
        $data = $this->encodeIds($task->toArray());
        $data['results'] = EamInspectionResult::query()
            ->where('task_id', $id)->orderBy('id')->get()
            ->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->success($data);
    }

    /**
     * 改期/改派/改备注（仅待执行任务可改）
     * @Apidoc\Title("修改点检任务")
     * @Apidoc\Desc("改期、更换负责人或备注；仅待执行任务可修改")
     * @Apidoc\Url("/admin/v1/eam/inspection/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID(hashid)")
     * @Apidoc\Param(name="task_date", type="string", desc="点检日期 Y-m-d")
     * @Apidoc\Param(name="assignee_id", type="string", desc="负责人ID(hashid)")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function update(Request $request, string $id): Response
    {
        $data = $request->all();
        if (array_key_exists('assignee_id', $data) && $data['assignee_id'] !== '') {
            $data['assignee_id'] = $this->decodeId((string) $data['assignee_id']);
        }
        try {
            $task = $this->inspection()->updateTask($this->decodeId($id), $data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($task->toArray()), '更新成功');
    }

    /**
     * 取消点检任务
     * @Apidoc\Title("取消点检任务")
     * @Apidoc\Desc("仅待执行任务可取消；已完成的点检不可取消")
     * @Apidoc\Url("/admin/v1/eam/inspection/{id}/cancel")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="id", type="string", desc="任务ID(hashid)")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function cancel(Request $request, string $id): Response
    {
        try {
            $task = $this->inspection()->cancelTask($this->decodeId($id));
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($task->toArray()), '取消成功');
    }

    /**
     * 扫码点检执行
     * @Apidoc\Title("扫码点检执行")
     * @Apidoc\Desc("扫码提交当日点检结果；无任务自动生成，异常项自动创建维修单")
     * @Apidoc\Url("/admin/v1/eam/inspection/scan-execute")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("设备管理")
     * @Apidoc\Param(name="equipment_id", type="string", desc="设备ID(hashid)，必填")
     * @Apidoc\Param(name="task_date", type="string", desc="点检日期 Y-m-d，必填")
     * @Apidoc\Param(name="items", type="array", desc="点检项数组，必填，元素含 item_name/result(0正常1异常)/remark")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function scanExecute(Request $request): Response
    {
        $validator = validator($request->all(), [
            'equipment_id' => 'required|string',
            'task_date' => 'required|date',
            'items' => 'required|array|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $result = $this->inspection()->scanExecute(
                $this->decodeId((string) $request->input('equipment_id')),
                (string) $request->input('task_date'),
                (array) $request->input('items', []),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }
        if ($result['task_id'] > 0) {
            $result['task_id'] = $this->encodeId((int) $result['task_id']);
        }
        if ($result['repair_order_id'] > 0) {
            $result['repair_order_id'] = $this->encodeId((int) $result['repair_order_id']);
        }

        return $this->success($result, $result['abnormal'] ? '点检完成，存在异常项，已生成维修单' : '点检完成');
    }

    /**
     * 点检服务实例
     */
    private function inspection(): EamInspectionService
    {
        return Container::get(EamInspectionService::class);
    }
}
