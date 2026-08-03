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
        int $sourceId
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
            $sourceId
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
        int $sourceId
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
            $sourceId
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
        $totalInventory = Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->sum('quantity');

        $lastCost = CostRecord::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->where('type', 1)
            ->orderByDesc('id')
            ->first();

        $beforeAvg = $lastCost ? $lastCost->after_avg_cost : $unitCost;
        $beforeTotalQty = $totalInventory - $quantity;
        $beforeTotalValue = $beforeTotalQty * $beforeAvg;
        $newValue = $quantity * $unitCost;
        $afterAvg = $totalInventory > 0
            ? round(($beforeTotalValue + $newValue) / $totalInventory, 2)
            : $unitCost;

        $this->recordCostRecord($productId, $skuId, $flowId, 1, $quantity, $unitCost, $beforeAvg, $afterAvg);

        return round($afterAvg, 2);
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
