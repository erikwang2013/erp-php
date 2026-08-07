<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\inventory;

use app\common\SnowflakeService;
use app\model\CostRecord;
use app\model\Inventory;
use app\model\InventoryBatch;
use app\model\InventoryFlow;
use app\model\InventorySerial;
use app\model\OmsInventoryReservation;
use Illuminate\Database\Capsule\Manager as DB;

class InventoryService
{
    /**
     * 入库操作 — 创建库存流水 + 更新实时库存 + 重算加权平均成本
     */
    public function stockIn(
        int $productId,
        int $skuId,
        int $warehouseId,
        int $locationId,
        string $batchCode,
        float $quantity,
        float $unitCost,
        string $sourceType,
        int $sourceId,
        array $serials = []
    ): int {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('数量必须大于0');
        }
        if ($unitCost < 0) {
            throw new \InvalidArgumentException('单价不能为负数');
        }

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $quantity,
            $unitCost,
            $sourceType,
            $sourceId,
            $serials
        ) {
            // 1. 创建出入库流水
            $flow = new InventoryFlow();
            $flow->id = SnowflakeService::generate();
            $flow->product_id = $productId;
            $flow->sku_id = $skuId;
            $flow->warehouse_id = $warehouseId;
            $flow->location_id = $locationId;
            $flow->batch_code = $batchCode;
            $flow->direction = 1;
            $flow->quantity = $quantity;
            $flow->cost_price = $unitCost;
            $flow->source_type = $sourceType;
            $flow->source_id = $sourceId;
            $flow->save();

            // 2. 更新/创建实时库存（悲观行锁防止并发覆盖）
            $inv = Inventory::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ])->lockForUpdate()->first();
            if (!$inv) {
                $inv = new Inventory();
                $inv->id = SnowflakeService::generate();
                $inv->product_id = $productId;
                $inv->sku_id = $skuId;
                $inv->warehouse_id = $warehouseId;
                $inv->location_id = $locationId;
                $inv->batch_code = $batchCode;
                $inv->quantity = 0;
            }
            $inv->quantity += $quantity;

            // 3. 移动加权平均成本重算（必须在save之前，把加权均价写回库存记录）
            $afterAvg = $this->recalcMovingAverageCost($productId, $skuId, $quantity, $unitCost, 1, $flow->id);
            $inv->cost_price = $afterAvg;
            $inv->save();

            // 4. 记录批次
            if (!empty($batchCode)) {
                InventoryBatch::firstOrCreate(
                    ['product_id' => $productId, 'sku_id' => $skuId, 'batch_code' => $batchCode],
                    ['id' => SnowflakeService::generate()]
                );
            }

            // 5. 记录序列号（可选）
            $this->recordSerialsIn($productId, $skuId, $serials, $flow->id);

            return $flow->id;
        });
    }

    /**
     * 出库操作 — 校验库存 + 创建流水 + 扣减库存
     */
    public function stockOut(
        int $productId,
        int $skuId,
        int $warehouseId,
        int $locationId,
        string $batchCode,
        float $quantity,
        string $sourceType,
        int $sourceId,
        array $serials = []
    ): int {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('数量必须大于0');
        }

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $quantity,
            $sourceType,
            $sourceId,
            $serials
        ) {
            // 1. 校验库存（悲观行锁防止并发超卖）
            $inv = Inventory::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ])->lockForUpdate()->first();

            if (!$inv || $inv->quantity < $quantity) {
                throw new \RuntimeException("库存不足: product_id={$productId}, sku_id={$skuId}, 需要{$quantity}, 可用" . ($inv->quantity ?? 0));
            }

            $currentCost = $inv->cost_price;

            // 2. 创建流水
            $flow = new InventoryFlow();
            $flow->id = SnowflakeService::generate();
            $flow->product_id = $productId;
            $flow->sku_id = $skuId;
            $flow->warehouse_id = $warehouseId;
            $flow->location_id = $locationId;
            $flow->batch_code = $batchCode;
            $flow->direction = 2;
            $flow->quantity = $quantity;
            $flow->cost_price = $currentCost;
            $flow->source_type = $sourceType;
            $flow->source_id = $sourceId;
            $flow->save();

            // 3. 扣减库存
            $inv->quantity -= $quantity;
            $inv->save();

            // 4. 记录出库成本（出库不改变加权均价）
            $this->recordCostRecord($productId, $skuId, $flow->id, 2, $quantity, $currentCost, $currentCost, $currentCost);

            // 5. 序列号出库标记（可选）
            $this->recordSerialsOut($serials, $flow->id);

            return $flow->id;
        });
    }

    /**
     * 移动加权平均成本计算
     */
    private function recalcMovingAverageCost(
        int $productId,
        int $skuId,
        float $quantity,
        float $unitCost,
        int $type,
        int $flowId
    ): float {
        // 按SKU聚合全部库存行的数量与成本，避免跨仓加权成本串算
        $rows = Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->get(['quantity', 'cost_price']);
        $totalQty = 0;
        $totalValue = 0.0;
        foreach ($rows as $row) {
            $qty = (float)$row->quantity;
            $totalQty += $qty;
            $totalValue += $qty * (float)($row->cost_price ?? 0);
        }

        $beforeAvg = $totalQty > 0 ? round($totalValue / $totalQty, 2) : $unitCost;
        $afterAvg = ($totalQty + $quantity) > 0
            ? round(($totalValue + $quantity * $unitCost) / ($totalQty + $quantity), 2)
            : $unitCost;

        // 同步该SKU所有库存行的成本价，保证出库成本一致
        Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->update(['cost_price' => $afterAvg]);

        $this->recordCostRecord($productId, $skuId, $flowId, 1, $quantity, $unitCost, $beforeAvg, $afterAvg);

        return $afterAvg;
    }

    /**
     * 库存预占 — 逻辑层锁定，不改动物理库存
     */
    public function reserveQuantity(
        int $productId,
        int $skuId,
        int $warehouseId,
        int $locationId,
        string $batchCode,
        float $quantity,
        string $sourceType,
        int $sourceId,
        int $sourceItemId = 0
    ): int {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('预占数量必须大于0');
        }

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $quantity,
            $sourceType,
            $sourceId,
            $sourceItemId
        ) {
            $inv = Inventory::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
            ])->lockForUpdate()->first();

            $physicalQty = $inv ? $inv->quantity : 0;
            $reserved = OmsInventoryReservation::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
                'status' => 1,
            ])->sum('reserved_quantity');

            if (($physicalQty - $reserved) < $quantity) {
                throw new \RuntimeException("库存不足: 需要{$quantity}, 可用" . ($physicalQty - $reserved));
            }

            $reservation = new OmsInventoryReservation();
            $reservation->id = SnowflakeService::generate();
            $reservation->product_id = $productId;
            $reservation->sku_id = $skuId;
            $reservation->warehouse_id = $warehouseId;
            $reservation->location_id = $locationId;
            $reservation->batch_code = $batchCode;
            $reservation->source_type = $sourceType;
            $reservation->source_id = $sourceId;
            $reservation->source_item_id = $sourceItemId;
            $reservation->reserved_quantity = $quantity;
            $reservation->status = 1;
            $reservation->save();

            return $reservation->id;
        });
    }

    /**
     * 释放库存预占
     */
    public function releaseReservation(string $sourceType, int $sourceId): void
    {
        OmsInventoryReservation::where([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 1,
        ])->update(['status' => 2]);
    }

    /**
     * 消耗库存预占（预占转出库）
     */
    public function consumeReservation(string $sourceType, int $sourceId): void
    {
        DB::transaction(function () use ($sourceType, $sourceId) {
            $reservations = OmsInventoryReservation::where([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 1,
            ])->get();

            foreach ($reservations as $r) {
                $this->stockOut(
                    $r->product_id,
                    $r->sku_id,
                    $r->warehouse_id,
                    $r->location_id,
                    $r->batch_code,
                    $r->reserved_quantity,
                    $sourceType,
                    $sourceId
                );
                $r->status = 3;
                $r->save();
            }
        });
    }

    /**
     * ATP可承诺量 = 物理库存 - SUM(status=1的预占)
     */
    public function getAvailableQuantity(int $productId, int $skuId, int $warehouseId = 0, int $locationId = 0): float
    {
        $query = Inventory::where('product_id', $productId)->where('sku_id', $skuId);
        if ($warehouseId > 0) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($locationId > 0) {
            $query->where('location_id', $locationId);
        }
        $physicalQty = $query->sum('quantity');

        $resQuery = OmsInventoryReservation::where('product_id', $productId)
            ->where('sku_id', $skuId)->where('status', 1);
        if ($warehouseId > 0) {
            $resQuery->where('warehouse_id', $warehouseId);
        }
        if ($locationId > 0) {
            $resQuery->where('location_id', $locationId);
        }
        $reserved = $resQuery->sum('reserved_quantity');

        return round($physicalQty - $reserved, 2);
    }

    private function recordSerialsIn(int $productId, int $skuId, array $serials, int $flowId): void
    {
        foreach ($serials as $serialCode) {
            $serialCode = trim((string)$serialCode);
            if ($serialCode === '') {
                continue;
            }
            if (InventorySerial::where('serial_code', $serialCode)->exists()) {
                throw new \RuntimeException("序列号已存在: {$serialCode}");
            }
            $serial = new InventorySerial();
            $serial->id = SnowflakeService::generate();
            $serial->product_id = $productId;
            $serial->sku_id = $skuId;
            $serial->serial_code = $serialCode;
            $serial->status = 0;
            $serial->in_flow_id = $flowId;
            $serial->save();
        }
    }

    private function recordSerialsOut(array $serials, int $flowId): void
    {
        foreach ($serials as $serialCode) {
            $code = trim((string)$serialCode);
            $serial = InventorySerial::where('serial_code', $code)->first();
            if (!$serial || $serial->status !== 0) {
                throw new \RuntimeException("序列号不在库，禁止出库: {$code}");
            }
            $serial->status = 1;
            $serial->out_flow_id = $flowId;
            $serial->save();
        }
    }

    private function recordCostRecord(
        int $productId,
        int $skuId,
        int $flowId,
        int $type,
        float $quantity,
        float $unitCost,
        float $beforeAvg,
        float $afterAvg
    ): void {
        $cost = new CostRecord();
        $cost->id = SnowflakeService::generate();
        $cost->product_id = $productId;
        $cost->sku_id = $skuId;
        $cost->flow_id = $flowId;
        $cost->type = $type;
        $cost->quantity = $quantity;
        $cost->unit_cost = $unitCost;
        $cost->before_avg_cost = $beforeAvg;
        $cost->after_avg_cost = $afterAvg;
        $cost->save();
    }
}
