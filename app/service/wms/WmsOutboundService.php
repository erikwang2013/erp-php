<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\service\wms;

use app\common\SnowflakeService;
use app\model\OmsFulfillment;
use app\model\OmsInventoryReservation;
use app\model\OmsOrder;
use app\model\WmsPackTask;
use app\model\WmsPickItem;
use app\model\WmsPickTask;
use app\model\WmsWaveOrder;
use app\service\inventory\InventoryService;
use Illuminate\Database\Capsule\Manager as DB;

class WmsOutboundService
{
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

            // 一次取回任务全部明细，按 (product_id, location_id) 内存索引
            // （同键多明细时与原逐行 first() 语义一致：取第一行）
            $itemsByKey = [];
            foreach (WmsPickItem::where('pick_task_id', $pickTaskId)->get() as $pi) {
                $key = $pi->product_id . ':' . $pi->location_id;
                if (!isset($itemsByKey[$key])) {
                    $itemsByKey[$key] = $pi;
                }
            }

            foreach ($actuals as $item) {
                // 明细行归属校验：必须属于该拣货任务（pick_task_id 唯一界定）
                $pickItem = $itemsByKey[$item['product_id'] . ':' . $item['location_id']] ?? null;
                if (!$pickItem) {
                    throw new \RuntimeException('拣货明细不存在或不属于该拣货任务: product_id=' . $item['product_id']);
                }

                // 超拣拒绝：实拣数量不得超过该行应拣（预占）数量
                $picked = (float)$item['picked_quantity'];
                if ($picked < 0 || $picked > (float)$pickItem->ordered_quantity) {
                    throw new \RuntimeException(
                        "实拣数量超限: product_id={$item['product_id']}, 应拣{$pickItem->ordered_quantity}, 实拣{$picked}"
                    );
                }

                $pickItem->picked_quantity = $picked;
                $pickItem->status = 1;
                $pickItem->picked_at = date('Y-m-d H:i:s');
                $pickItem->save();
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
        $pack->code = 'PACK' . SnowflakeService::generate();
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

    /** 发货确认 — 按实际拣货数量扣物理库存 + 更新履约 */
    public function confirmShip(int $fulfillmentId, int $omsOrderId): void
    {
        DB::transaction(function () use ($fulfillmentId, $omsOrderId) {
            $order = OmsOrder::where('id', $omsOrderId)->lockForUpdate()->first();
            if (!$order) {
                throw new \RuntimeException('OMS订单不存在');
            }
            if (in_array($order->fulfillment_status, [4, 5], true)) {
                throw new \RuntimeException('订单已发货或已签收，禁止重复发货');
            }

            $fulfillment = OmsFulfillment::where('id', $fulfillmentId)->lockForUpdate()->first();
            if (!$fulfillment) {
                throw new \RuntimeException('履约单不存在');
            }
            if (in_array($fulfillment->status, [5], true)) {
                throw new \RuntimeException('该履约单已发货');
            }

            $this->consumeByPickedQuantity($omsOrderId);

            $fulfillment->status = 5;
            $fulfillment->save();
            $order->fulfillment_status = 4;
            $order->save();
        });
    }

    /**
     * 按实际拣货数量出库并结算预占（修复按预占全额 stockOut 的超扣问题）：
     *  - 实拣 ≥ 预占：按预占数量出库，预占标记消耗（status=3）；
     *  - 部分实拣：按实拣数量出库，未拣部分释放回可用库存（status=2）；
     *  - 未拣：整行释放（status=2），不动物理库存。
     */
    private function consumeByPickedQuantity(int $omsOrderId): void
    {
        // 订单 → 波次 → 拣货任务 → 实拣明细（status=1 且实拣 > 0）
        $pickTasks = WmsPickTask::whereIn(
            'wave_id',
            WmsWaveOrder::where('oms_order_id', $omsOrderId)->pluck('wave_id')
        )->get(['id', 'warehouse_id']);
        $pickItems = WmsPickItem::whereIn('pick_task_id', $pickTasks->pluck('id'))
            ->where('status', 1)
            ->where('picked_quantity', '>', 0)
            ->get();
        $warehouseByTask = $pickTasks->pluck('warehouse_id', 'id');

        // 按库存维度（商品/SKU/仓库/库位/批次）聚合实拣数量
        $pickedByKey = [];
        foreach ($pickItems as $pi) {
            $key = $this->reservationKey(
                (int)$pi->product_id,
                (int)$pi->sku_id,
                (int)($warehouseByTask[$pi->pick_task_id] ?? 0),
                (int)$pi->location_id,
                (string)$pi->batch_code
            );
            $pickedByKey[$key] = ((float)($pickedByKey[$key] ?? 0)) + (float)$pi->picked_quantity;
        }

        $inventory = new InventoryService();
        $reservations = OmsInventoryReservation::where('source_type', 'oms_order')
            ->where('source_id', $omsOrderId)
            ->where('status', 1)
            ->get();

        foreach ($reservations as $r) {
            $key = $this->reservationKey(
                (int)$r->product_id,
                (int)$r->sku_id,
                (int)$r->warehouse_id,
                (int)$r->location_id,
                (string)$r->batch_code
            );
            $picked = (float)($pickedByKey[$key] ?? 0);
            $reserved = (float)$r->reserved_quantity;

            if ($picked >= $reserved) {
                $inventory->stockOut($r->product_id, $r->sku_id, $r->warehouse_id, $r->location_id, $r->batch_code, $reserved, 'oms_order', $omsOrderId);
                $r->status = 3; // 全部实拣 → 预占消耗
                $pickedByKey[$key] = $picked - $reserved;
            } elseif ($picked > 0) {
                $inventory->stockOut($r->product_id, $r->sku_id, $r->warehouse_id, $r->location_id, $r->batch_code, $picked, 'oms_order', $omsOrderId);
                $r->reserved_quantity = round($reserved - $picked, 2); // 保留未拣部分记录
                $r->status = 2; // 部分实拣 → 未拣部分释放
                $pickedByKey[$key] = 0;
            } else {
                $r->status = 2; // 未实拣 → 整行释放
            }
            $r->save();
        }
    }

    /** 库存维度聚合键 */
    private function reservationKey(int $productId, int $skuId, int $warehouseId, int $locationId, string $batchCode): string
    {
        return implode(':', [$productId, $skuId, $warehouseId, $locationId, $batchCode]);
    }
}
