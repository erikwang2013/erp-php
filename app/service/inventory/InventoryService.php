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
use Illuminate\Database\QueryException;

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
        array $serials = [],
        ?string $productionDate = null,
        ?string $expiryDate = null
    ): int {
        if (bccomp(bc_norm($quantity), '0', 4) <= 0) {
            throw new \InvalidArgumentException('数量必须大于0');
        }
        if (bccomp(bc_norm($unitCost), '0', 4) < 0) {
            throw new \InvalidArgumentException('单价不能为负数');
        }
        $this->assertValidDate($productionDate, 'production_date');
        $this->assertValidDate($expiryDate, 'expiry_date');
        $qty = bc_norm($quantity);
        $cost = bc_norm($unitCost);

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $qty,
            $cost,
            $sourceType,
            $sourceId,
            $serials,
            $productionDate,
            $expiryDate
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
            $flow->quantity = $qty;
            $flow->cost_price = $cost;
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
                try {
                    // 并发首单竞态：两事务首次入库同一 (product_id, sku_id, warehouse_id, location_id, batch_code)
                    // 时都查不到行，均走创建路径；由唯一索引 uk_product_sku_warehouse_location_batch 兜底，
                    // 后到事务在此抛 1062 唯一键冲突
                    $inv->save();
                } catch (QueryException $e) {
                    if (!$this->isDuplicateKey($e)) {
                        throw $e;
                    }
                    // 另一事务已抢先创建该行：重读既有行（行锁串行化后一事务），
                    // 落入下方统一的累加 + 均价重算路径，避免重复行或均价错乱
                    $inv = Inventory::where([
                        'product_id' => $productId,
                        'sku_id' => $skuId,
                        'warehouse_id' => $warehouseId,
                        'location_id' => $locationId,
                        'batch_code' => $batchCode,
                    ])->lockForUpdate()->firstOrFail();
                }
            }
            $inv->quantity = bcadd(bc_norm($inv->quantity), $qty, 6);

            // 3. 移动加权平均成本重算（必须在save之前，把加权均价写回库存记录）
            $afterAvg = $this->recalcMovingAverageCost($productId, $skuId, $qty, $cost, 1, $flow->id);
            $inv->cost_price = $afterAvg;
            $inv->save();

            // 4. 记录批次（id 在 $guarded 中，firstOrCreate 的 values 会被批量赋值保护剥离，
            // 无法携带雪花 id，须显式赋值，否则插入缺 id 报 1364）
            // 生产/效期日期为可选（P1-M6）：创建时写入；已有批次仅在值为空时补写，不覆盖既有效期数据
            if (!empty($batchCode)) {
                $batch = InventoryBatch::where('product_id', $productId)
                    ->where('sku_id', $skuId)
                    ->where('batch_code', $batchCode)
                    ->first();
                $batchDirty = false;
                if (!$batch) {
                    $batch = new InventoryBatch();
                    $batch->id = SnowflakeService::generate();
                    $batch->product_id = $productId;
                    $batch->sku_id = $skuId;
                    $batch->batch_code = $batchCode;
                    $batchDirty = true;
                }
                if ($productionDate !== null && empty($batch->getAttributes()['production_date'] ?? null)) {
                    $batch->production_date = $productionDate;
                    $batchDirty = true;
                }
                if ($expiryDate !== null && empty($batch->getAttributes()['expiry_date'] ?? null)) {
                    $batch->expiry_date = $expiryDate;
                    $batchDirty = true;
                }
                if ($batchDirty) {
                    $batch->save();
                }
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
        if (bccomp(bc_norm($quantity), '0', 4) <= 0) {
            throw new \InvalidArgumentException('数量必须大于0');
        }
        $qty = bc_norm($quantity);

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $qty,
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

            if (!$inv || bccomp(bc_norm($inv->quantity), $qty, 4) < 0) {
                throw new \RuntimeException("库存不足: product_id={$productId}, sku_id={$skuId}, 需要{$qty}, 可用" . ($inv->quantity ?? 0));
            }

            $currentCost = bc_norm($inv->cost_price);

            // 2. 创建流水
            $flow = new InventoryFlow();
            $flow->id = SnowflakeService::generate();
            $flow->product_id = $productId;
            $flow->sku_id = $skuId;
            $flow->warehouse_id = $warehouseId;
            $flow->location_id = $locationId;
            $flow->batch_code = $batchCode;
            $flow->direction = 2;
            $flow->quantity = $qty;
            $flow->cost_price = $currentCost;
            $flow->source_type = $sourceType;
            $flow->source_id = $sourceId;
            $flow->save();

            // 3. 扣减库存
            $inv->quantity = bcsub(bc_norm($inv->quantity), $qty, 6);
            $inv->save();

            // 4. 记录出库成本（出库不改变加权均价）
            $this->recordCostRecord($productId, $skuId, $flow->id, 2, $qty, $currentCost, $currentCost, $currentCost);

            // 5. 序列号出库标记（可选）
            $this->recordSerialsOut($serials, $flow->id);

            return $flow->id;
        });
    }

    /**
     * MySQL 唯一键冲突(1062)判定：仅 1062 走重读重算，其余异常原样抛出
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * 可选日期参数校验（P1-M6 追溯效期）：仅 YYYY-MM-DD 合法日期放行，null 放行
     */
    private function assertValidDate(?string $date, string $label): void
    {
        if ($date === null) {
            return;
        }
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("{$label} 须为合法日期 YYYY-MM-DD");
        }
    }

    /**
     * 移动加权平均成本计算
     */
    private function recalcMovingAverageCost(
        int $productId,
        int $skuId,
        string|int|float $quantity,
        string|int|float $unitCost,
        int $type,
        int $flowId
    ): string {
        // 按SKU聚合全部库存行的数量与成本，避免跨仓加权成本串算
        // 悲观行锁：锁住该 SKU 全部库存行，串行化聚合读与批量成本更新，防止并发入库丢失更新
        // 首次入库无行可锁的竞态由唯一索引 uk_product_sku_warehouse_location_batch 兜底：
        // 后到事务在 stockIn 创建路径捕获 1062 后重读既有行再重算（见 stockIn）。
        $inQty = bc_norm($quantity);
        $inCost = bc_norm($unitCost);
        $rows = Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->lockForUpdate()
            ->get(['quantity', 'cost_price']);
        $totalQty = '0';
        $totalValue = '0';
        foreach ($rows as $row) {
            $qty = bc_norm($row->quantity);
            $totalQty = bcadd($totalQty, $qty, 6);
            $totalValue = bcadd($totalValue, bcmul($qty, bc_norm($row->cost_price ?? 0), 6), 6);
        }

        // 移动加权平均：bc 域内高 scale 相除，避免 float 除法尾噪进入成本列，再舍入到列精度
        $beforeAvg = bccomp($totalQty, '0', 4) > 0 ? bc_round(bcdiv($totalValue, $totalQty, 10), 2) : $inCost;
        $newTotalQty = bcadd($totalQty, $inQty, 6);
        $afterAvg = bccomp($newTotalQty, '0', 4) > 0
            ? bc_round(bcdiv(bcadd($totalValue, bcmul($inQty, $inCost, 6), 6), $newTotalQty, 10), 2)
            : $inCost;

        // 同步该SKU所有库存行的成本价，保证出库成本一致
        Inventory::where('product_id', $productId)
            ->where('sku_id', $skuId)
            ->update(['cost_price' => $afterAvg]);

        $this->recordCostRecord($productId, $skuId, $flowId, 1, $inQty, $inCost, $beforeAvg, $afterAvg);

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
        if (bccomp(bc_norm($quantity), '0', 4) <= 0) {
            throw new \InvalidArgumentException('预占数量必须大于0');
        }
        $qty = bc_norm($quantity);

        return DB::transaction(function () use (
            $productId,
            $skuId,
            $warehouseId,
            $locationId,
            $batchCode,
            $qty,
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

            $physicalQty = bc_norm($inv ? $inv->quantity : 0);
            $reserved = bc_norm(OmsInventoryReservation::where([
                'product_id' => $productId,
                'sku_id' => $skuId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'batch_code' => $batchCode,
                'status' => 1,
            ])->sum('reserved_quantity'));
            $available = bcsub($physicalQty, $reserved, 6);

            if (bccomp($available, $qty, 4) < 0) {
                throw new \RuntimeException("库存不足: 需要{$qty}, 可用" . rtrim(rtrim($available, '0'), '.'));
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
            $reservation->reserved_quantity = $qty;
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

        return (float) bc_round(bcsub(bc_norm($physicalQty), bc_norm($reserved), 6), 2);
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
        string|int|float $quantity,
        string|int|float $unitCost,
        string|int|float $beforeAvg,
        string|int|float $afterAvg
    ): void {
        $cost = new CostRecord();
        $cost->id = SnowflakeService::generate();
        $cost->product_id = $productId;
        $cost->sku_id = $skuId;
        $cost->flow_id = $flowId;
        $cost->type = $type;
        $cost->quantity = bc_norm($quantity);
        $cost->unit_cost = bc_norm($unitCost);
        $cost->before_avg_cost = bc_norm($beforeAvg);
        $cost->after_avg_cost = bc_norm($afterAvg);
        $cost->save();
    }
}
