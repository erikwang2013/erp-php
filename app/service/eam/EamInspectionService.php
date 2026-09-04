<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\eam;

use app\model\EamEquipment;
use app\model\EamInspectionResult;
use app\model\EamInspectionTask;
use app\model\EamMaintenancePlan;
use app\model\EamRepairOrder;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * 设备点检执行闭环（E1）
 *
 * 状态机：task.status 0=待执行 1=已完成 2=异常待维修 3=已取消（3 为终态）。
 * scanExecute 幂等语义：
 *  - (equipment_id, task_date) 无未完成任务 → 自动创建 status=0 的临时点检任务（source_plan_id=0）；
 *  - 状态 0 且有异常项 → 置 2 并创建 erp_eam_repair_order（同任务仅首次进入异常时创建一次，
 *    维修单无来源任务列，靠"仅 0→2 转变时建单"去重，见 database/e1p1.sql 注释）；
 *  - 状态 2 复扫：全部正常 → 置 1；仍有异常 → 维持 2（刷新结果行，不再建单）；
 *  - 状态 1 再扫 → 抛「设备当日点检已完成」（复扫须先取消再重新生成任务）。
 *
 * 所有变更包裹在 DB::transaction 内；结果行采用"整删重插"刷新，天然幂等。
 */
class EamInspectionService extends AbstractCrudService
{
    public const STATUS_PENDING = 0;   // 待执行
    public const STATUS_DONE = 1;      // 已完成
    public const STATUS_ABNORMAL = 2;  // 异常待维修
    public const STATUS_CANCELLED = 3; // 已取消

    public const STATUS_NAMES = [
        self::STATUS_PENDING => '待执行',
        self::STATUS_DONE => '已完成',
        self::STATUS_ABNORMAL => '异常待维修',
        self::STATUS_CANCELLED => '已取消',
    ];

    /**
     * 生成点检任务（计划生成或人工补单；扫码自动生成走 scanExecute 内部）
     *
     * @throws InvalidArgumentException 日期格式无效
     * @throws RuntimeException 设备/计划不存在，或当日已有未完成任务
     */
    public function createTask(int $equipmentId, string $taskDate, int $sourcePlanId = 0, int $assigneeId = 0, string $remark = ''): EamInspectionTask
    {
        $this->assertDate($taskDate);
        if (!EamEquipment::query()->where('id', $equipmentId)->exists()) {
            throw new RuntimeException('设备不存在');
        }
        if ($sourcePlanId > 0) {
            $plan = EamMaintenancePlan::query()->where('id', $sourcePlanId)->first();
            if (!$plan || (int) $plan->equipment_id !== $equipmentId) {
                throw new RuntimeException('保养计划不存在');
            }
        }
        $dup = $this->pendingTask($equipmentId, $taskDate);
        if ($dup) {
            throw new RuntimeException('该设备当日已有未完成的点检任务');
        }

        /** @var EamInspectionTask $task */
        $task = new EamInspectionTask();
        $task->id = $this->generateId();
        $task->equipment_id = $equipmentId;
        $task->source_plan_id = $sourcePlanId;
        $task->task_date = $taskDate;
        $task->assignee_id = $assigneeId;
        $task->remark = $remark;
        $task->status = self::STATUS_PENDING;
        $task->save();

        return $task;
    }

    /**
     * 修改点检任务（改期/换负责人/改备注；仅待执行任务可改，改期校验同日冲突）
     *
     * @throws RuntimeException 记录不存在 / 非待执行状态 / 改期冲突
     */
    public function updateTask(int $id, array $data): EamInspectionTask
    {
        $task = EamInspectionTask::query()->find($id);
        if (!$task) {
            throw new RuntimeException('点检任务不存在');
        }
        if ((int) $task->status !== self::STATUS_PENDING) {
            throw new RuntimeException('仅待执行的点检任务可修改');
        }
        $newDate = isset($data['task_date']) ? (string) $data['task_date'] : (string) $task->task_date;
        $this->assertDate($newDate);
        if ($newDate !== (string) $task->task_date) {
            $dup = EamInspectionTask::query()
                ->where('equipment_id', $task->equipment_id)
                ->where('task_date', $newDate)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ABNORMAL])
                ->where('id', '!=', $id)
                ->first();
            if ($dup) {
                throw new RuntimeException('该设备当日已有未完成的点检任务');
            }
        }

        // 仅允许改期/换人/备注，其余字段（equipment_id/status 等）不可经此修改
        if (array_key_exists('assignee_id', $data)) {
            $data['assignee_id'] = (int) $data['assignee_id'];
        }
        $fill = array_intersect_key($data, array_flip(['task_date', 'assignee_id', 'remark']));
        $task->fill($fill);
        $task->save();

        return $task;
    }

    /**
     * 取消点检任务（仅待执行可取消，取消为终态；任务为 0 时允许再扫自动重建）
     *
     * @throws RuntimeException 记录不存在 / 状态不可取消
     */
    public function cancelTask(int $id): EamInspectionTask
    {
        $task = EamInspectionTask::query()->find($id);
        if (!$task) {
            throw new RuntimeException('点检任务不存在');
        }
        $status = (int) $task->status;
        if ($status === self::STATUS_DONE) {
            throw new RuntimeException('已完成的点检任务不能取消');
        }
        if ($status === self::STATUS_ABNORMAL) {
            throw new RuntimeException('异常待维修的点检任务不能取消，请先维修完成');
        }
        if ($status === self::STATUS_CANCELLED) {
            throw new RuntimeException('点检任务已取消');
        }
        $task->status = self::STATUS_CANCELLED;
        $task->save();

        return $task;
    }

    /**
     * 扫码点检执行（核心闭环，事务 + 幂等）
     *
     * @param int $equipmentId 设备ID
     * @param string $taskDate 点检日期 Y-m-d
     * @param array<int, array{item_name: string, result: int, remark?: string}> $items 点检项列表
     * @return array{task_id: int, task_status: int, abnormal: bool, repair_order_id: int, task_created: bool, item_count: int}
     * @throws InvalidArgumentException 日期/点检项格式无效
     * @throws RuntimeException 设备不存在 / 当日点检已完成
     */
    public function scanExecute(int $equipmentId, string $taskDate, array $items): array
    {
        $this->assertDate($taskDate);
        if (!EamEquipment::query()->where('id', $equipmentId)->exists()) {
            throw new RuntimeException('设备不存在');
        }
        if ($items === []) {
            throw new InvalidArgumentException('点检项不能为空');
        }
        $normalized = [];
        foreach ($items as $i => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('点检项格式无效');
            }
            $name = trim((string) ($row['item_name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('点检项名称不能为空');
            }
            $result = (int) ($row['result'] ?? -1);
            if (!in_array($result, [0, 1], true)) {
                throw new InvalidArgumentException('点检结果无效');
            }
            $remark = trim((string) ($row['remark'] ?? ''));
            if (mb_strlen($remark) > 500) {
                throw new InvalidArgumentException('点检备注过长');
            }
            $normalized[] = ['item_name' => mb_substr($name, 0, 100), 'result' => $result, 'remark' => $remark];
        }

        $result = DB::transaction(function () use ($equipmentId, $taskDate, $normalized): array {
            // 已完成（status=1）→ 明确异常，防止覆盖当日已完成记录
            $done = EamInspectionTask::query()
                ->where('equipment_id', $equipmentId)
                ->where('task_date', $taskDate)
                ->where('status', self::STATUS_DONE)
                ->first();
            if ($done) {
                throw new RuntimeException('设备当日点检已完成');
            }
            $task = EamInspectionTask::query()
                ->where('equipment_id', $equipmentId)
                ->where('task_date', $taskDate)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ABNORMAL])
                ->orderBy('id')
                ->first();
            $created = false;
            if (!$task) {
                /** @var EamInspectionTask $task */
                $task = new EamInspectionTask();
                $task->id = $this->generateId();
                $task->equipment_id = $equipmentId;
                $task->source_plan_id = 0;
                $task->task_date = $taskDate;
                $task->assignee_id = 0;
                $task->status = self::STATUS_PENDING;
                $task->remark = '扫码自动生成';
                $task->save();
                $created = true;
            }
            $taskId = (int) $task->id;

            // 结果行整删重插（复扫刷新，幂等）
            EamInspectionResult::query()->where('task_id', $taskId)->delete();
            foreach ($normalized as $row) {
                $item = new EamInspectionResult();
                $item->id = $this->generateId();
                $item->task_id = $taskId;
                $item->item_name = $row['item_name'];
                $item->result = $row['result'];
                $item->remark = $row['remark'];
                $item->save();
            }

            $abnormalItems = array_values(array_filter($normalized, static fn (array $r): bool => $r['result'] === 1));
            $abnormal = $abnormalItems !== [];
            $repairOrderId = 0;
            if ($abnormal) {
                if ((int) $task->status === self::STATUS_PENDING) {
                    // 0→2 转变：仅此一次创建维修单（复扫状态 2 不会二次建单）
                    $repairOrderId = $this->createRepairOrder($equipmentId, $taskDate, $abnormalItems);
                    $task->status = self::STATUS_ABNORMAL;
                }
                // 已是 2：维持异常待维修
            } else {
                $task->status = self::STATUS_DONE;
            }
            $task->save();

            return [
                'task_id' => $taskId,
                'task_status' => (int) $task->status,
                'abnormal' => $abnormal,
                'repair_order_id' => $repairOrderId,
                'task_created' => $created,
                'item_count' => count($normalized),
            ];
        });

        return $result;
    }

    /**
     * 待执行/异常待维修任务查询（供 createTask 冲突校验复用）
     */
    protected function pendingTask(int $equipmentId, string $taskDate): ?EamInspectionTask
    {
        return EamInspectionTask::query()
            ->where('equipment_id', $equipmentId)
            ->where('task_date', $taskDate)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_ABNORMAL])
            ->orderBy('id')
            ->first();
    }

    /**
     * 创建维修单（异常项摘要 → fault_description；状态沿既有维修单约定从 open 开始）
     */
    protected function createRepairOrder(int $equipmentId, string $taskDate, array $abnormalItems): int
    {
        $summary = [];
        foreach ($abnormalItems as $row) {
            $line = $row['item_name'];
            if ($row['remark'] !== '') {
                $line .= '(' . $row['remark'] . ')';
            }
            $summary[] = $line;
        }
        $order = new EamRepairOrder();
        $order->id = $this->generateId();
        $order->code = 'RO' . $this->generateId();
        $order->equipment_id = $equipmentId;
        $order->fault_description = '点检异常项：' . implode('；', $summary);
        $order->repair_type = 'corrective';
        $order->assignee = '';
        $order->start_date = $taskDate;
        $order->cost = '0.00';
        $order->status = 'open';
        $order->save();

        return (int) $order->id;
    }

    /**
     * Y-m-d 严格校验（含真实日期回环，拒绝 2026-02-30 之类）
     */
    protected function assertDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d', (int) strtotime($date)) !== $date) {
            throw new InvalidArgumentException('无效的点检日期');
        }
    }
}
