<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\wms;

use app\common\SnowflakeService;
use app\model\OmsFulfillment;
use app\model\OmsOrder;
use app\model\WmsPackTask;
use app\model\WmsPickItem;
use app\model\WmsPickTask;
use app\service\inventory\InventoryService;
use app\service\oms\AllocationService;
use Illuminate\Database\Capsule\Manager as DB;

class WmsOutboundService
{
    private InventoryService $inventory;

    public function __construct()
    {
        $this->inventory = new InventoryService();
    }

    /** 开始拣货 */
    public function startPick(int $pickTaskId, int $assigneeId = 0): void
    {
        $pick = WmsPickTask::find($pickTaskId);
        if (!$pick) {
            throw new \RuntimeException('拣货任务不存在');
        }
        if ($pick->status !== 0) {
            throw new \RuntimeException('当前状态不允许开始拣货');
        }
        $pick->status = 1;
        $pick->assigned_to = $assigneeId;
        $pick->started_at = date('Y-m-d H:i:s');
        $pick->save();
    }

    /** 确认拣货 */
    public function confirmPick(int $pickTaskId, array $actuals): void
    {
        DB::transaction(function () use ($pickTaskId, $actuals) {
            $pick = WmsPickTask::find($pickTaskId);
            if (!$pick) {
                throw new \RuntimeException('拣货任务不存在');
            }
            if ($pick->status !== 1) {
                throw new \RuntimeException('请先开始拣货');
            }

            foreach ($actuals as $item) {
                WmsPickItem::where('pick_task_id', $pickTaskId)
                    ->where('product_id', $item['product_id'])
                    ->where('location_id', $item['location_id'])
                    ->update([
                        'picked_quantity' => $item['picked_quantity'],
                        'status' => 1,
                        'picked_at' => date('Y-m-d H:i:s'),
                    ]);
            }

            $pick->status = 2;
            $pick->completed_at = date('Y-m-d H:i:s');
            $pick->save();
        });
    }

    /** 开始打包 */
    public function startPack(int $warehouseId, array $options = []): WmsPackTask
    {
        $pack = new WmsPackTask();
        $pack->id = SnowflakeService::generate();
        $pack->code = 'PACK' . $this->generateId();
        $pack->warehouse_id = $warehouseId;
        $pack->status = 1;
        $pack->package_type = $options['package_type'] ?? '';
        $pack->assigned_to = $options['assigned_to'] ?? 0;
        $pack->save();

        return $pack;
    }

    /** 完成打包 */
    public function completePack(int $packTaskId, array $packageInfo): WmsPackTask
    {
        $pack = WmsPackTask::find($packTaskId);
        if (!$pack) {
            throw new \RuntimeException('打包任务不存在');
        }
        if ($pack->status !== 1) {
            throw new \RuntimeException('请先开始打包');
        }

        $pack->status = 2;
        $pack->weight_kg = $packageInfo['weight_kg'] ?? 0;
        $pack->length_cm = $packageInfo['length_cm'] ?? 0;
        $pack->width_cm = $packageInfo['width_cm'] ?? 0;
        $pack->height_cm = $packageInfo['height_cm'] ?? 0;
        $pack->completed_at = date('Y-m-d H:i:s');
        $pack->save();

        return $pack;
    }

    /** 发货确认 — 消耗预占 + stockOut + 更新履约 */
    public function confirmShip(int $fulfillmentId, int $omsOrderId): void
    {
        DB::transaction(function () use ($fulfillmentId, $omsOrderId) {
            $allocSvc = new AllocationService();
            $allocSvc->consume($omsOrderId);

            $fulfillment = OmsFulfillment::find($fulfillmentId);
            if ($fulfillment) {
                $fulfillment->status = 5;
                $fulfillment->save();
            }

            OmsOrder::where('id', $omsOrderId)->update(['fulfillment_status' => 4]);
        });
    }
}
